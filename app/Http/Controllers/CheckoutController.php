<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\Hardware;
use App\Models\DeviceRegister;
use App\Models\License;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderMail;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller {
    public $licenses = [];

public function index(Request $request) {
    $user = Auth::user();
    $licenses = Cache::get('licenses');
    
    if (!isset($licenses)) {
        $licenses = array('licenses' => [], 'total' => 0);
    }
    $this->licenses = $licenses;
    
    return view('checkout', ['licenses' => $this->licenses, 'user' => $user]);        
}

    public function store(Request $request) {
        $licenses_types = Product::all();
        $req = $request->toArray();
        array_shift($req);
        
        $this->licenses = array('licenses' => [], 'total' => 0);
        $total = 0;
        foreach ($req as $key => $value) {
            $license = $licenses_types->firstWhere('slug', $key);
            $name = $license->name; 
            $price = $license->price; 
            $hardware = Hardware::where('id' , $license->hardware_id)->first();
            $total += $price * $req[$key];
            
            $this->licenses['licenses'][] = array('name' => $name, 'hardware_name' => $hardware->name, 'type' => $key, 'quantity' => intval($req[$key]), 'price' => $price);
        }
        
        $this->licenses['total'] = $total;
        Cache::put('licenses', $this->licenses);
        return view('checkout', ['licenses' => $this->licenses, 'user' => auth()->user()]);        
    }

public function purchase(Request $request)
{
    Log::info('Purchase method called', $request->all());

    try {
        $user = Auth::user();
        if (isset($user)) {
            Log::info('User authenticated', ['user_id' => $user->id, 'user_email' => $user->email]);

            // Get the products off the purchase
            $products = [];
            foreach ($request->products as $key => $value) {
                Log::info('Processing product', ['product_slug' => $key, 'quantity' => $value]);

                $product = Product::where('slug', $key)->first();
                $hardware = Hardware::where('id', $product->hardware_id)->first();

                if ($product) {
                    $products[] = ['product' => $product, 'hardware_name' => $hardware->name, 'quantity' => $value];
                    Log::info('Product added to purchase', ['product_name' => $product->name, 'hardware_name' => $hardware->name, 'quantity' => $value]);
                } else {
                    Log::warning('Product not found', ['product_slug' => $key]);
                }
            }

            $billing_details = [
                'city' => $request->billing_city,
                'country' => $request->billing_country,
                'line1' => $request->billing_address,
                'line2' => $request->billing_address_2,
                'postal_code' => $request->billing_zip_code,
                'state' => $request->billing_state
            ];
            Log::info('Billing details set', $billing_details);

            $card_details = [
                'name' => $request->name_on_card,
                'number' => str_replace('-', '', str_replace(' ', '', (string)$request->card_number)),
                'exp_date' => str_replace('/', '', str_replace(' ', '', (string)$request->card_exp)),
                'code' => (string)$request->card_cvc
            ];
            Log::info('Card details set', $card_details);

            // Log the attempt to purchase
            $log_order = [
                'user' => $user,
                'products' => $products,
                'billing_details' => $billing_details
            ];
            Log::info('Attempting to create order', $log_order);

            $order = Order::purchase($user, $products, $billing_details, $card_details);
            if ($order != null) {
                Log::info('Order created successfully', ['order_id' => $order->id, 'order_total' => $order->total]);

                $products_array = json_decode($order['products'], true);

                $order_data = [
                    'name' => $user->name,
                    'invoice' => sprintf('%06d', $order->id),
                    'total' => $order['total'],
                    'products' => $products_array,
                    'date' => $order['created_at']
                ];
                Log::info('Order data prepared for email', $order_data);

                Cache::flush();
                Log::info('Cache flushed');

                Mail::to($user->email)->send(new OrderMail($order_data));
                Log::info('Order confirmation email sent to user', ['user_email' => $user->email]);

                return view('checkout.success');
            } else {
                Log::error('Order creation failed');
                return view('checkout.error', ['error' => 'Order creation failed.']);
            }
        } else {
            Log::error('User authentication failed');
            return view('checkout.error', ['error' => 'User authentication failed.']);
        }
    } catch (\Exception $e) {
        Log::error('An error occurred during the purchase process: ' . $e->getMessage(), ['exception' => $e]);
        return view('checkout.error', ['error' => 'An error occurred during the purchase process. ' . $e->getMessage()]);
    }
}
    

public function showCheckoutForm(Request $request)
{
    // Retrieve the token from the request
    $token = $request->input('token');
    $user = null;

    try {
        if ($token) {
            $personalAccessToken = PersonalAccessToken::findToken($token);

            // Log the token and result of PersonalAccessToken::findToken for debugging
            Log::info('Received token: ' . $token);
            Log::info('Personal access token: ' . ($personalAccessToken ? 'found' : 'not found'));

            if ($personalAccessToken) {
                // Retrieve the user from the personal access token
                $user = $personalAccessToken->tokenable;

                // Log the user for debugging
                Log::info('User associated with token: ' . ($user ? 'found' : 'not found'));

                if ($user) {
                    // Manually authenticate the user for the current request
                    Auth::setUser($user);
                }
            }
        }

        if (!$user) {
            Log::error('User authentication failed');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $device_id = $request->input('device_id');
        $product_id = $request->input('product_id');

        // Log the received device_id and product_id for debugging
        Log::info('Received device_id: ' . $device_id);
        Log::info('Received product_id: ' . $product_id);

        // Validate input
        if (!$device_id || !$user->id || !$product_id) {
            Log::error('Missing required parameters', ['device_id' => $device_id, 'product_id' => $product_id, 'user_id' => $user->id ?? null]);
            return response()->json(['error' => 'Missing required parameters.'], 400);
        }

        // Find the device
        $device = DeviceRegister::find($device_id);
        if (!$device || $device->user_id != $user->id) {
            Log::error('Device not found or does not belong to the user', ['device_id' => $device_id, 'user_id' => $user->id]);
            return response()->json(['error' => 'Device not found or does not belong to the user.'], 404);
        }

        // Find the product
        $product = Product::find($product_id);
        if (!$product) {
            Log::error('Product not found', ['product_id' => $product_id]);
            return response()->json(['error' => 'Product not found.'], 404);
        }

        // Log the validated device and product
        Log::info('Device and Product found', ['device' => $device, 'product' => $product]);

        // Create a licenses array for the view
        $licenses = [
            'licenses' => [
                [
                    'name' => $product->name,
                    'hardware_name' => $device->hardware->name ?? '',
                    'type' => $product->slug,
                    'quantity' => 1,
                    'price' => $product->price,
                ]
            ],
            'total' => $product->price,
        ];

        // Log the licenses array for debugging
        Log::info('Licenses array: ' . json_encode($licenses));

        // Ensure errors variable is passed
        return view('checkout-with-token', [
            'licenses' => $licenses,
            'user' => $user,
            'errors' => session('errors') ? session('errors') : new \Illuminate\Support\ViewErrorBag,
            'device_id' => $device_id,
            'product_id' => $product_id,
            'token' => $token ?? ''  // Pass the token to the view
        ]);
    } catch (\Exception $e) {
        Log::error('An error occurred during the checkout process: ' . $e->getMessage(), ['exception' => $e]);
        return view('checkout.error', ['error' => 'An error occurred during the checkout process. ' . $e->getMessage()]);
    }
}


public function purchaseApi(Request $request)
{
    Log::info('purchaseApi method called', $request->all());

    try {
        $token = $request->bearerToken();
        $personalAccessToken = PersonalAccessToken::findToken($token);

        if (!$personalAccessToken) {
            Log::error('Access token not found or invalid');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = $personalAccessToken->tokenable;
        if (!$user) {
            Log::error('User authentication failed');
            return response()->json(['error' => 'User authentication failed.'], 401);
        }

        Auth::setUser($user);

        Log::info('User authenticated', ['user_id' => $user->id, 'user_email' => $user->email]);

        // Extract device_id from the request input
        $deviceId = $request->input('device_id');
        Log::info('Extracted device_id', ['device_id' => $deviceId]);

        // Get the products off the purchase
        $products = [];
        foreach ($request->products as $key => $value) {
            Log::info('Processing product', ['product_slug' => $key, 'quantity' => $value]);

            $product = Product::where('slug', $key)->first();
            $hardware = Hardware::where('id', $product->hardware_id)->first();

            if ($product) {
                $products[] = ['product' => $product, 'hardware_name' => $hardware->name, 'quantity' => $value];
                Log::info('Product added to purchase', ['product_name' => $product->name, 'hardware_name' => $hardware->name, 'quantity' => $value]);
            } else {
                Log::warning('Product not found', ['product_slug' => $key]);
            }
        }

        $billing_details = [
            'city' => $request->billing_city,
            'country' => $request->billing_country,
            'line1' => $request->billing_address,
            'line2' => $request->billing_address_2,
            'postal_code' => $request->billing_zip_code,
            'state' => $request->billing_state
        ];
        Log::info('Billing details set', $billing_details);

        $card_details = [
            'name' => $request->name_on_card,
            'number' => str_replace('-', '', str_replace(' ', '', (string)$request->card_number)),
            'exp_date' => str_replace('/', '', str_replace(' ', '', (string)$request->card_exp)),
            'code' => (string)$request->card_cvc
        ];
        Log::info('Card details set', $card_details);

        // Log the attempt to purchase
        $log_order = [
            'user' => $user,
            'products' => $products,
            'billing_details' => $billing_details,
            'device_id' => $deviceId // Include device_id in the logged order details
        ];
        Log::info('Attempting to create order', $log_order);

        // Pass device_id to the purchase method
        $order = Order::purchase($user, $products, $billing_details, $card_details, $deviceId);
        if ($order != null) {
            Log::info('Order created successfully', ['order_id' => $order->id, 'order_total' => $order->total]);

            $products_array = json_decode($order['products'], true);

            $order_data = [
                'name' => $user->name,
                'invoice' => sprintf('%06d', $order->id),
                'total' => $order['total'],
                'products' => $products_array,
                'date' => $order['created_at'],
                'device_id' => $deviceId // Include device_id in the order data
            ];
            Log::info('Order data prepared for email', $order_data);

            Cache::flush();
            Log::info('Cache flushed');

            Mail::to($user->email)->send(new OrderMail($order_data));
            Log::info('Order confirmation email sent to user', ['user_email' => $user->email]);

            return response()->json(['success' => true, 'message' => 'Order created successfully']);
        } else {
            Log::error('Order creation failed');
            return response()->json(['error' => 'Order creation failed.']);
        }
    } catch (\Exception $e) {
        Log::error('An error occurred during the purchase process: ' . $e->getMessage(), ['exception' => $e]);
        return response()->json(['error' => 'An error occurred during the purchase process. ' . $e->getMessage()]);
    }
}







    public function subscribe(Request $request) {
        $device_id = $request->input('device_id');
        $user_id = $request->input('user_id');
        $product_id = $request->input('product_id');

        // Validate input
        if (!$device_id || !$user_id || !$product_id) {
            return response()->json(['error' => 'Missing required parameters.'], 400);
        }

        // Find the user
        $user = User::find($user_id);
        if (!$user) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        // Find the device
        $device = DeviceRegister::find($device_id);
        if (!$device || $device->user_id != $user_id) {
            return response()->json(['error' => 'Device not found or does not belong to the user.'], 404);
        }

        // Create billing details from the user information
        $billing_details = [
            'city' => $user->city,
            'country' => $user->country,
            'line1' => $user->address_1,
            'line2' => $user->address_2,
            'postal_code' => $user->zip_code,
            'state' => $user->state,
        ];

        // Dummy card details for testing (replace with real payment gateway integration)
        $card_details = [
            'name' => $user->name,
            'number' => '4242424242424242', // Example test card number
            'exp_date' => '12/24',
            'code' => '123',
        ];

        // Create order (Replace this with actual order creation logic)
        $order = Order::create([
            'user_id' => $user->id,
            'total' => 100, // Replace with actual product price if necessary
            'products' => json_encode([['product_id' => $product_id, 'quantity' => 1]]),
        ]);

        // For testing purposes, set a default subscription ID
        $subscription_id = 1; // You can change this to any default value or logic

        // Create the license using the createForUserWithKey method
        $license_key = License::createKey();
        $license = License::createForUserWithKey($user, Product::find($product_id), $order->id, $subscription_id, $license_key);

        // Build the products array
        $products = [];
        $product = Product::find($product_id);
        if ($product) {
            $hardware = Hardware::find($product->hardware_id);
            $products[] = [
                'product' => $product->toArray(),
                'hardware_name' => $hardware ? $hardware->name : '',
                'quantity' => 1
            ];
        }

        // Prepare order data for email
        $order_data = [
            'name' => $user->name,
            'invoice' => sprintf('%06d', $order->id),
            'total' => $order->total,
            'products' => $products,
            'date' => $order->created_at,
        ];

        // Send confirmation email
        Mail::to($user->email)->send(new OrderMail($order_data));

        return response()->json(['success' => 'License assigned successfully', 'license' => $license], 200);
    }
}
