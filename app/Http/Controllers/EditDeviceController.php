<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Http\Controllers\Log;


class EditDeviceController extends Controller
{
    public function index($id)
    {
        $device = DeviceRegister::find($id);

        return view('edit-device', compact('device'));
    }

    public function updateAddress1(Request $request, $id)
    {


        // Validate the request for address_1
        $request->validate([
            'address_1' => 'required',
        ]);

        // Get the new value from the request
        $address1 = $request->input('address_1');

        // Update the database with the new value
        DeviceRegister::where('id', $id)->update(['address_1' => $address1]);

        return redirect()->route('edit-device', ['id' => $id])->with('success', 'Address 1 updated successfully.');
    }

    public function updateProductAlias(Request $request, $id)
{
    $request->validate([
        'alias' => 'required|string|max:255', // Adjust validation rules as needed
    ]);

    $Alias = $request->input('alias');

    DeviceRegister::where('id', $id)->update(['alias' => $Alias]);

    return redirect()->route('edit-device', ['id' => $id])->with('success', 'Product Alias updated successfully.');
}



    public function updateAddress2(Request $request, $id)
    {
        // Validate the request for address_2
        $request->validate([
            'address_2' => 'nullable',
        ]);

        // Get the new value from the request
        $address2 = $request->input('address_2');

        // Update the database with the new value
        DeviceRegister::where('id', $id)->update(['address_2' => $address2]);

        return redirect()->route('edit-device', ['id' => $id])->with('success', 'Address 2 updated successfully.');
    }

    public function updateZipCode(Request $request, $id)
    {

        // Validate the request for zip_code
        $request->validate([
            'zip_code' => 'required|regex:/[0-9]+/',
        ]);

        // Get the new value from the request
        $zipCode = $request->input('zip_code');

        // Update the database with the new value
        DeviceRegister::where('id', $id)->update(['zip_code' => $zipCode]);

        return redirect()->route('edit-device', ['id' => $id])->with('success', 'Zip Code updated successfully.');
    }

    public function updateStateCity(Request $request, $id)
    {


        // You can handle the state and city update together here
        // Validate the request for state and city if needed

        // Get the new values from the request
        $state = $request->input('state');
        $city = $request->input('city');

        // Update the database with the new values
        DeviceRegister::where('id', $id)->update([
            'state' => $state,
            'city' => $city
        ]);

        return redirect()->route('edit-device', ['id' => $id])->with('success', 'State and City updated successfully.');
    }

    public function updateStatusNotification(Request $request, $id)
    {
        $device = DeviceRegister::findOrFail($id);
        $device->status_notification = $request->status_notification == '1';
        $device->save();

        return back()->with('success', 'Notification preference updated successfully.');
    }


        public function updateSMSNotification(Request $request, $id)
    {
        $device = DeviceRegister::findOrFail($id);
        $device->sms_notification = $request->sms_notification == '1';
        $device->save();

        return back()->with('success', 'SMS notification preference updated successfully.');
    }


    //------------------------------------------
    public function apiUpdateAddress1(Request $request, $id)
    {
        $request->validate([
            'address_1' => 'required',
        ]);

        $address1 = $request->input('address_1');
        DeviceRegister::where('id', $id)->update(['address_1' => $address1]);

        return response()->json(['message' => 'Address 1 updated successfully.'], 200);
    }

    public function apiUpdateProductAlias(Request $request, $id)
    {
        $request->validate([
            'alias' => 'required|string|max:255',
        ]);

        $alias = $request->input('alias');
        DeviceRegister::where('id', $id)->update(['alias' => $alias]);

        return response()->json(['message' => 'Product Alias updated successfully.'], 200);
    }

    public function apiUpdateAddress2(Request $request, $id)
    {
        $request->validate([
            'address_2' => 'nullable',
        ]);

        $address2 = $request->input('address_2');
        DeviceRegister::where('id', $id)->update(['address_2' => $address2]);

        return response()->json(['message' => 'Address 2 updated successfully.'], 200);
    }

    public function apiUpdateZipCode(Request $request, $id)
    {
        $request->validate([
            'zip_code' => 'required|regex:/[0-9]+/',
        ]);

        $zipCode = $request->input('zip_code');
        DeviceRegister::where('id', $id)->update(['zip_code' => $zipCode]);

        return response()->json(['message' => 'Zip Code updated successfully.'], 200);
    }

    public function apiUpdateStateCity(Request $request, $id)
    {
        $state = $request->input('state');
        $city = $request->input('city');

        DeviceRegister::where('id', $id)->update([
            'state' => $state,
            'city' => $city,
        ]);

        return response()->json(['message' => 'State and City updated successfully.'], 200);
    }

    public function apiUpdateStatusNotification(Request $request, $id)
    {
        $device = DeviceRegister::findOrFail($id);
        $device->status_notification = $request->status_notification == '1';
        $device->save();

        return response()->json(['message' => 'Notification preference updated successfully.'], 200);
    }

    public function apiUpdateSMSNotification(Request $request, $id)
    {
        $device = DeviceRegister::findOrFail($id);
        $device->sms_notification = $request->sms_notification == '1';
        $device->save();

        return response()->json(['message' => 'SMS notification preference updated successfully.'], 200);
    }



}
