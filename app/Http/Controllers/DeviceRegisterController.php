<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Hardware;
use Illuminate\Support\Facades\Validator;



class DeviceRegisterController extends Controller
{
    public function index()
    {
        return view('device-register', ['hardwares' => Hardware::where('id', 1)->select('id', 'name')->get()]);

    }

    public function dataInsert(Request $request)
    {
	//dd($request->all());

        try {
            $validatedData = $request->validate([
                'address_1' => 'required',
                'address_2' => 'nullable', // It's okay if it's empty
                'city' => 'required',
                'state' => 'required',
                'zip_code' => 'required|regex:/[0-9]+/',
                'hardware_id' => 'required|integer|in:1',
                'alias' => 'required|string|max:255',
				'serial_no' => 'required',
				'sku' => 'required',
                'order_no' => 'required|regex:/[0-9]+/',
            ]);
    
            // If the code reaches here, validation has passed.
            Log::info('Validation passed.', $validatedData);
    
            // The rest of your code...
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // If validation fails, the exception will be caught here.
            Log::warning('Validation failed.', [
                'errors' => $e->errors(),
                'data' => $e->validator->getData()
            ]);
    
            // Re-throw the exception if you want the default Laravel behavior to kick in
            // (redirect back with error messages).
            throw $e;
        }

        $request->validate([
            'address_1' => 'required',
            'address_2' => 'nullable', // It's okay if it's empty
            'city' => 'required',
            'state' => 'required',
            'zip_code' => 'required|regex:/[0-9]+/',
            'hardware_id' => 'required|integer|in:1',
			'alias' => 'required|string|max:255',
            'sku' => 'required',
            'serial_no' => 'required',
            'order_no' => 'required|regex:/[0-9]+/'
        ]);



        Log::info('After validation');

        // Extract request inputs
        $hardware_id = $request->input('hardware_id');

        // Log the received hardware_id
        Log::info('Received hardware_id:', ['hardware_id' => $hardware_id]);

        // Retrieve the hardware name based on hardware_id for logging
        $hardwareName = Hardware::where('id', $hardware_id)->value('name');
        Log::info('Hardware Name:', ['hardwareName' => $hardwareName]);


        // Extract request inputs
        $address_1 = $request->input('address_1');
        $address_2 = $request->input('address_2');
        $city = $request->input('city');
        $state = $request->input('state');
        $zip_code = $request->input('zip_code');
        $hardware_id = $request->input('hardware_id');
        $sku = $request->input('sku');
        $serial_no = $request->input('serial_no');
        $order_no = $request->input('order_no');
        $latitude = $request->input('latitude');
        $longitude = $request->input('longitude');
        $status_notification = $request->input('status_notification');

        $alias = $request->input('alias');

        $user = Auth::user();

        Log::info('Received hardware_id:', ['hardware_id' => $hardware_id]);
        // Insert data into the Device Register model (devices)
        if ($user) {

            Log::info('Attempting to insert data', [
                'user_id' => $user->id,
                // Add the rest of your log data here...
                'hardware_name' => $hardwareName, // Logging the hardware name for reference
            ]);

            $user_id = $user->id;
            Log::info('Attempting to insert data', [
                'user_id' => $user_id,
                'data' => [
                    'address_1' => $address_1,
                    'address_2' => $address_2,
                    'city' => $city,
                    'state' => $state,
                    'zip_code' => $zip_code,
                    'hardware_id' => $hardware_id,
		    'alias' => $alias,
                    'sku' => $sku,
                    'serial_no' => $serial_no,
                    'order_no' => $order_no,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]
            ]);

            // Extract the user's ID
            $user_id = $user->id;

            $is_insert_success = DeviceRegister::create([
                'address_1' => $address_1,
                'address_2' => $address_2,
                'city' => $city,
                'state' => $state,
                'country' => 'US',
                'zip_code' => $zip_code,
                'hardware_id' => $hardware_id,
                'alias' => $alias,
		        'sku' => $sku,
                'serial_no' => $serial_no,
                'order_no' => $order_no,
                'user_id' => $user_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'status_notification' => $status_notification,
                
            ]);




            // GEOCODE
            // =============================================================================================================
            // Please create the mode for Geocode
            // Enter latitude, longitude, serial_no, and status
            // The default status shold be none when the device registered for the first time

            if ($is_insert_success) {
                GeoCode::create([
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'serial_no' => $serial_no,
                    'status' => 'none', // Default status
                ]);
                Log::info('Insert successful', ['user_id' => $user_id]);
            }



        }

        if ($is_insert_success) {
            // Redirect to the thank-you route if the insert was successful
            return redirect()->route('thank-you');
        } else {
            // Redirect back with an error message if the insert failed
            return redirect()->back()->with('error', 'Insert Failed');
        }
    }

    public function apiRegisterDevice(Request $request)
{
    try {
        // Validation
        $validatedData = $request->validate([
            'address_1' => 'required',
            'address_2' => 'nullable',
            'city' => 'required',
            'state' => 'required',
            'zip_code' => 'required|regex:/[0-9]+/',
            'hardware_id' => 'required|integer|in:1',
            'alias' => 'required|string|max:255',
            'serial_no' => 'required',
            'sku' => 'required',
            'order_no' => 'required|regex:/[0-9]+/',
            'latitude' => 'required',
            'longitude' => 'required',
            'user_id' => 'nullable|integer', // Optionally validate the user_id
        ]);

        // Log the validated data
        Log::info('Validation passed', $validatedData);

        // Retrieve user_id from request, or fallback to Auth if not provided
        $user_id = $request->input('user_id', Auth::id());

        // Insert into DeviceRegister model
        DeviceRegister::create([
            'address_1' => $validatedData['address_1'],
            'address_2' => $validatedData['address_2'],
            'city' => $validatedData['city'],
            'state' => $validatedData['state'],
            'country' => 'US',
            'zip_code' => $validatedData['zip_code'],
            'hardware_id' => $validatedData['hardware_id'],
            'alias' => $validatedData['alias'],
            'serial_no' => $validatedData['serial_no'],
            'sku' => $validatedData['sku'],
            'order_no' => $validatedData['order_no'],
            'user_id' => $user_id,
            'latitude' => $validatedData['latitude'],
            'longitude' => $validatedData['longitude'],
            'status_notification' => $request->input('status_notification'),
        ]);

        // Respond with a success message, no need to return the device data
        return response()->json([
            'message' => 'Device registered successfully'
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Validation failed
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        // Any other error
        Log::error('Device registration failed', ['error' => $e->getMessage()]);
        return response()->json(['message' => 'An error occurred'], 500);
    }
}


}
