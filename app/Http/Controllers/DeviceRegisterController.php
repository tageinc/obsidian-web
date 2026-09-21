<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Hardware;



class DeviceRegisterController extends Controller
{
    public function index()
    {
        return view('device-register', ['hardwares' => Hardware::where('id', 1)->select('id', 'name')->get()]);

    }

    public function dataInsert(Request $request)
    {
        $validated = $request->validate([
            'hardware_id' => 'required|integer|in:1|exists:hardware,id',
            'alias' => 'required|string|max:255',
            'serial_no' => 'required|string|max:255|unique:device_registers,serial_no',
            'sku' => 'required|string|max:255',
            'order_no' => 'required|string|max:255',
            'address_1' => 'required|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'zip_code' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $device = DeviceRegister::create(array_merge($validated, [
                'user_id' => $request->user()->id,
                'status_notification' => false,
                'sms_notification' => false,
            ]));

            GeoCode::create([
                'serial_no' => $device->serial_no,
                'latitude' => $device->latitude,
                'longitude' => $device->longitude,
                'status' => 'none',
            ]);
        });

        return redirect()->route('dashboard')->with('success', 'Device registered successfully.');
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

        Log::info('device.registration_validated');

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
        Log::error('device.registration_failed');
        return response()->json(['message' => 'An error occurred'], 500);
    }
}


}
