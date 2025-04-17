<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use App\Models\Api\SolarTrackerLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\License;
use App\Models\Hardware;
use Carbon\Carbon;



class DeviceManagerController extends Controller
{
    // structure of controller 
    // - index = data for table 
    // - all devices data for all checkbox
    // - paginated is when NOT all is checkmarked

    public function index(Request $request)
    {
        $user_id = Auth::id();
        // Attempt to get the 'show' parameter from the request
        $pagination_size = $request->input('show');

        // Check if 'show' is null or an empty string, and default to the .env setting if so
        if (empty($pagination_size)) {
            $pagination_size = env('PAGINATION_SIZE', 10); // Default to 10 if not set in .env
        }
        // //Log::info('Pagination Size:', ['pagination_size' => $pagination_size]);

        $devices = DeviceRegister::where('user_id', $user_id)
        //->with('hardware', 'license') // Load the license relationship
        ->select('id', 'hardware_id', 'serial_no', 'sku', 'alias', 'latitude', 'longitude', 'address_1', 'address_2', 'user_id', 'updated_at')
        ->paginate($pagination_size);

        // //Log::info('Initial Devices State:', ['devices' => $devices->pluck('state', 'serial_no')->toArray()]);


        foreach ($devices as $device) {
            //Log::info('controller Looking for GeoCode entry for serial number:', ['serial_no' => $device->serial_no]); //ADDED NEW

            // Fetch the latest 'updated_at' from the GeoCode table for each device
            $geoCodeEntry = GeoCode::where('serial_no', $device->serial_no)->latest('updated_at')->first();


            //Log::info('controller GeoCode entry found:', ['geoCodeEntry' => $geoCodeEntry]); // NEWLY ADDED

            // Check if there is a GeoCode entry and get its status and updated_at timestamp
            if ($geoCodeEntry) {
                $device->state = $geoCodeEntry->status;
                $lastUpdated = Carbon::parse($geoCodeEntry->updated_at);
            } else {
                $device->state = 'no geo data';
                // If there is no GeoCode entry, you might want to use the DeviceRegister's updated_at or set a default value
                $lastUpdated = Carbon::parse($device->updated_at); // or use Carbon::now() for a default value
            }
            // //Log::info('GeoCode Last Updated (before formatting):', ['serial_no' => $device->serial_no, 'last_updated' => $lastUpdated->toDateTimeString()]);


            $device->last_updated = $lastUpdated->diffForHumans();

            // //Log::info('Before Modification:', [
            //     'device_id' => $device->id, 
            //     'initial_state' => $device->state, 
            //     'initial_last_updated' => $device->last_updated
            // ]);

            // //Log::info('After Modification:', [
            //     'device_id' => $device->id, 
            //     'modified_state' => $device->state, 
            //     'modified_last_updated' => $device->last_updated
            // ]);
        }
        //Log::info('controller Final Devices State:', ['devices' => $devices->pluck('state', 'serial_no')->toArray()]);


        return view('device-manager', ['devices' => $devices, 'pagination_size' => $pagination_size]);
    }


    // import this class loads in the values for the map when show IS checked 

    public function allDevices(Request $request)
{
    $user_id = Auth::id();
    $devices = DeviceRegister::where('user_id', $user_id)
             ->with('hardware')  // This fetches hardware details
             ->get(['id', 'hardware_id', 'serial_no', 'sku', 'alias', 'latitude', 'longitude', 'address_1', 'address_2', 'updated_at']);

    foreach ($devices as $device) {
        // Check if the device uses a specific hardware type that requires checking the SolarTrackerLog
            // Fetching the geo status as before
            $geo_status = GeoCode::where('serial_no', $device->serial_no)
                ->latest('updated_at')
                ->first();

            // Setting the state from the GeoCode status or defaulting to 'no geo data'
            $device->state = $geo_status ? $geo_status->status : 'no geo data';

            // If a matching GeoCode entry is found, use its updated_at for last_updated
            if ($geo_status) {
                $lastUpdated = Carbon::parse($geo_status->updated_at); // Now using the updated_at from GeoCode
                //Log::info('GeoCode Last Updated (before formatting):', ['serial_no' => $device->serial_no, 'last_updated' => $lastUpdated->toDateTimeString()]);
                $device->last_updated = $lastUpdated->diffForHumans();
            } else {
                // If no GeoCode entry is found, indicate that the last updated time is unknown
                $device->last_updated = 'Unknown';
            }
        
    }

// Convert the devices array to JSON
$devicesJson = $devices->toJson();

// Log the devices JSON
//Log::info('Devices JSON:', ['devices' => $devicesJson]);

    return response()->json($devices);
}

    // import this class loads in the values for the map when show is NOT checked 
    public function paginatedDevices(Request $request)
{
    $user_id = Auth::id();
    $pagination_size = $request->input('show', env('PAGINATION_SIZE', 10));

    $devices = DeviceRegister::where('user_id', $user_id)
             ->with('hardware')  // Fetch hardware details
             ->select('id', 'hardware_id', 'serial_no', 'sku', 'alias', 'latitude', 'longitude', 'address_1', 'user_id', 'updated_at')
             ->paginate($pagination_size);

    foreach ($devices as $device) {
        
            $geo_status = GeoCode::where('serial_no', $device->serial_no)->latest('updated_at')->first(['status', 'updated_at']);

            // Set the device state from the GeoCode entry or use a default value
            $device->state = $geo_status ? $geo_status->status : 'no geo data';

            // Use the GeoCode updated_at timestamp for last_updated, if available
            if ($geo_status && $geo_status->updated_at) {
                $lastUpdated = Carbon::parse($geo_status->updated_at);
                //Log::info('GeoCode Last Updated (before formatting):', ['serial_no' => $device->serial_no, 'last_updated' => $lastUpdated->toDateTimeString()]);

                $device->last_updated = $lastUpdated->diffForHumans();
            } else {
                // If no GeoCode entry or updated_at is found, indicate that the last updated time is unknown
                $device->last_updated = 'Unknown';
            }
        
    }

    return response()->json($devices);
}


    public function delete($id)
    {
        // Find the device by ID
        $device = DeviceRegister::find($id);

        // Check if the device exists
        if (!$device) {
            return redirect()->route('device-manager')->with('error', 'Device not found');
        }

        // Check if the currently authenticated user is the owner of the device
        if (Auth::id() !== $device->user_id) {
            return redirect()->route('device-manager')->with('error', 'You do not have permission to delete this device');
        }

        // Remove the device from any assigned license
        /*
		$license = License::where('device_id', $device->id)->first();
        if ($license) {
            $license->device_id = 0;
            $license->save();
        }*/

        // Delete the device from the device register
        $device->delete();

        // Delete the device from geocode
        $geocodeEntry = Geocode::where('serial_no', $device->serial_no)->first();
        if ($geocodeEntry) {
            $geocodeEntry->delete();
        }

        return redirect()->route('device-manager')->with('success', 'Device deleted successfully');
    }

    public function allDevicesAPI(Request $request)
    {
        try {
            $user_id = Auth::id(); // Get the authenticated user's ID
            if (!$user_id) {
                //Log::info('No user ID found');
                return response()->json(['error' => 'User not authenticated'], 401);
            }
    
            $devices = DeviceRegister::where('user_id', $user_id)
                ->with('hardware')
                ->get([
                    'id', 'hardware_id', 'serial_no', 'sku', 'alias', 'latitude', 'longitude', 
                    'address_1', 'address_2', 'updated_at', 'status_notification', 
                    'sms_notification', 'zip_code'
                ]);
    
            foreach ($devices as $device) {
                $geo_status = GeoCode::where('serial_no', $device->serial_no)
                    ->latest('updated_at')
                    ->first();
    
                $device->state = $geo_status ? $geo_status->status : 'no geo data';
    
                if ($geo_status) {
                    $lastUpdated = Carbon::parse($geo_status->updated_at);
                    $device->last_updated = $lastUpdated->diffForHumans();
                } else {
                    $device->last_updated = 'Unknown';
                }
            }
    
            // Log the devices JSON for debugging
            //Log::info('Devices JSON:', ['devices' => $devices]);
    
            return response()->json($devices);
        } catch (\Exception $e) {
            Log::error('Error fetching devices: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching devices'], 500);
        }
    }

    public function deleteAPI($id)
    {
        // Find the device by ID
        $device = DeviceRegister::find($id);

        // Check if the device exists
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Check if the currently authenticated user is the owner of the device
        if (Auth::id() !== $device->user_id) {
            return response()->json(['error' => 'You do not have permission to delete this device'], 403);
        }

        // Remove the device from any assigned license
        /*
        $license = License::where('device_id', $device->id)->first();
        if ($license) {
            $license->device_id = 0;
            $license->save();
        }*/

        // Delete the device from the device register
        $device->delete();

        // Delete the device from geocode
        $geocodeEntry = GeoCode::where('serial_no', $device->serial_no)->first();
        if ($geocodeEntry) {
            $geocodeEntry->delete();
        }

        return response()->json(['success' => 'Device deleted successfully'], 200);
    }

}
