<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\GeoCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;



class CreateDeviceController extends Controller
{
    public function index()
    {
        return redirect()->route('dashboard', ['create' => 1]);

    }

    public function createDevice(Request $request)
    {
        $request->merge(['_creation_modal' => '1']);
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'alias' => 'required|string|max:255',
            'serial_no' => 'required|string|max:255|unique:devices,serial_no',
            'sku' => 'required|string|max:255',
            'order_no' => 'required|string|max:255',
            'address_1' => 'required|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:255',
            'address_state' => 'required|string|max:255',
            'zip_code' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            throw (new \Illuminate\Validation\ValidationException($validator))->redirectTo(route('dashboard'));
        }
        $validated = $validator->validated();

        DB::transaction(function () use ($request, $validated) {
            $device = Device::create(array_merge($validated, [
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

        return redirect()->route('dashboard')->with('success', 'Device created successfully.');
    }

    public function apiCreateDevice(Request $request)
{
    try {
        // Validation
        $validatedData = $request->validate([
            'address_1' => 'required',
            'address_2' => 'nullable',
            'city' => 'required',
            'address_state' => 'required',
            'zip_code' => 'required|regex:/[0-9]+/',
            'alias' => 'required|string|max:255',
            'serial_no' => 'required',
            'sku' => 'required',
            'order_no' => 'required|regex:/[0-9]+/',
            'latitude' => 'required',
            'longitude' => 'required',
            'user_id' => 'nullable|integer', // Optionally validate the user_id
        ]);

        Log::info('device.creation_validated');

        // Retrieve user_id from request, or fallback to Auth if not provided
        $user_id = $request->input('user_id', Auth::id());

        // Insert into Device model
        Device::create([
            'address_1' => $validatedData['address_1'],
            'address_2' => $validatedData['address_2'] ?? null,
            'city' => $validatedData['city'],
            'address_state' => $validatedData['address_state'],
            'country' => 'US',
            'zip_code' => $validatedData['zip_code'],
            'alias' => $validatedData['alias'],
            'serial_no' => $validatedData['serial_no'],
            'sku' => $validatedData['sku'],
            'order_no' => $validatedData['order_no'],
            'user_id' => $user_id,
            'latitude' => $validatedData['latitude'],
            'longitude' => $validatedData['longitude'],
            'status_notification' => $request->boolean('status_notification'),
        ]);

        // Respond with a success message, no need to return the device data
        return response()->json([
            'message' => 'Device created successfully'
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        // Validation failed
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        // Any other error
        Log::error('device.creation_failed');
        return response()->json(['message' => 'An error occurred'], 500);
    }
}


}
