<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

define("AUTHORIZENET_LOG_FILE", "../logs/authorizenet_log");

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    // second argument "products" is an array with the Product and quantity
    public static function purchase(User $user, $products, $billing_details, $card_details, $device_id = null)
    {
        $products_json = [];
        // Iterate through the selected products in the checkout cart
        foreach ($products as $product) {    
            // Build the products json that will be stuffed inside the new order
            $product_name = $product['product']['name'];
            $price = $product['product']['price'];
            $duration_days = $product['product']['duration_days'];
            $quantity = $product['product']['quantity'];
            $description = $product['product']['name'];
            $products_json[] = [
                'product' => [
                    'name' => $product_name,
                    'duration_days' => $duration_days,
                    'price' => $price,
                    'quantity' => $quantity,
                    'description' => $product_name
                ],
                'quantity' => $product['quantity']
            ];
            
            // Create the order
            $num_of_licenses = $quantity * $product['quantity'];
            $order = new Order;
            $order->user_id = $user->id;
            $order->products = json_encode($products_json);
            $order->total = $num_of_licenses * $price;
            $order->save();    
            // Log::info('AuthorizeNet Response: '.json_encode($order));
            
            // Devices of the same hardware id as the product
            $devices = DeviceRegister::where('user_id', $user->id)
                ->where('hardware_id', $product['product']['hardware_id'])
                ->get();
            $num_of_devices = sizeof($devices);
            
            // Unset the devices that already are licensed
            for ($j = 0; $j < $num_of_devices; $j++) {
                if (!is_null($devices[$j]->license)) {
                    unset($devices[$j]);
                }
            }    
            // Generate the new licenses
            for ($i = 0; $i < $num_of_licenses; $i++) {
                $key = License::createKey();
                // Create a new subscription
                $res = License::createSubscription($user, $price, $product_name, $description, $duration_days, $billing_details, $card_details, $key, $i);
                if ($res["id"] != null) {
                    // Create a new license
                    $license = License::createForUserWithKey($user, $product['product'], $order->id, /*$res['id']*/ 917899,  $key);
                     
                     // Auto-assign a license
                     // Pop a device from the list and assign it to the license
                     if ($device_id) {
                         $license->device_id = $device_id; // Assign the device_id to the license
                     } else {
                         $device = $devices->pop();
                         if ($device) {
                             $license->device_id = $device->id;
                         }
                     }
                     $license->save(); 
                } else {
                    return null;
                }
            }
        }
        return $order;
    }
}
