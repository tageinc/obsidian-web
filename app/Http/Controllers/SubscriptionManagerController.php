<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\License;
use App\Models\Hardware;
use App\Models\Product;


class SubscriptionManagerController extends Controller{
	public function index(Request $request){
		$user_id = Auth::id();
		// Attempt to get the 'show' parameter from the request
		$pagination_size = $request->input('show');

		// Check if 'show' is null or an empty string, and default to the .env setting if so
		if (empty($pagination_size)) {
			$pagination_size = env('PAGINATION_SIZE', 10); // Default to 10 if not set in .env
		}
		
		// Log::info('Pagination Size:', ['pagination_size' => $pagination_size]);

		$devices = DeviceRegister::where('user_id', $user_id)->select('id', 'alias', 'hardware_id', 'latitude', 'longitude', 'address_1', 'user_id')->with('hardware', 'license')->paginate($pagination_size); // Use the $pagination_size variable
	
		$licenses = License::where('user_id', $user_id)->select('key', 'device_id')->get();
	
		return view('subscription-manager', ['devices' => $devices, 'licenses' => $licenses, 'pagination_size' => $pagination_size]);
	}
		
	public function paginatedDevices(Request $request){
		$user_id = Auth::id();
		$pagination_size = $request->input('show', env('PAGINATION_SIZE', 10));

		$devices = DeviceRegister::where('user_id', $user_id)->select('id', 'alias', 'hardware_id', 'latitude', 'longitude', 'address_1', 'user_id')->with('hardware', 'license')->paginate($pagination_size);

		return response()->json($devices);
	}
	
	public function assignLicense($id){
		$user_id = Auth::id();
		
		// Find the device by ID
		$device = DeviceRegister::find($id);

		// Check if the device exists
		if(!$device){
			return redirect()->route('subscription-manager')->with('error', 'Device not found');
		}
		
		// Check if the currently authenticated user is the owner of the device
		if (Auth::id() !== $device->user_id){
			return redirect()->route('subscription-manager')->with('error', 'Permission denied. ');
		}
		
		// Get the hardware id of the device of interest
		$hardware_id = $device->hardware->id;
		
		// Find the next available license for this particular product based on the hardware id
		$licenses = License::where('user_id', $user_id)->where('device_id', 0)->get();
		
		// Iterate through the licenses and break it if we find the license with a product that pertains to the hardware id of the device
		foreach($licenses as $license){
			// Get the product of that license
			$product = $license->products;
			if($product->hardware_id == $hardware_id){
				$license->device_id = $device->id;
				$license->save();
				return redirect()->route('subscription-manager')->with('success', 'License assigned successfully');
			}
		}
		return redirect()->route('subscription-manager')->with('error', 'License not found');
	}
	
	public function cancelSubscription($id){
		// Find the license by id
		$license = License::find($id);

		if($license){
			$license->cancel();
			return redirect()->route('subscription-manager')->with('success', 'License cancelled successfully');
		}else{
			return redirect()->route('subscription-manager')->with('error', 'License not found');
		}
	}
}
