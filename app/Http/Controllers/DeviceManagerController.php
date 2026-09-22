<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\GeoCode;
use App\Models\Api\SolarTrackerLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;



class DeviceManagerController extends Controller
{
    // structure of controller
    // - index = data for table
    // - all devices data for all checkbox
    // - paginated is when NOT all is checkmarked

    public function index(Request $request)
    {
        $search = $this->nameSearch($request);
        // Attempt to get the 'show' parameter from the request
        $pagination_size = $request->input('show');

        // Check if 'show' is null or an empty string, and default to the .env setting if so
        if (empty($pagination_size)) {
            $pagination_size = env('PAGINATION_SIZE', 10); // Default to 10 if not set in .env
        }
        // //Log::info('Pagination Size:', ['pagination_size' => $pagination_size]);

        $devices = $this->ownedDevices($search)
        ->select('id', 'state', 'serial_no', 'sku', 'name', 'latitude', 'longitude', 'address_1', 'address_2', 'user_id', 'updated_at')
        ->paginate($pagination_size)->appends(['show' => $pagination_size, 'search' => $search, 'status' => $this->statusFilter()]);

        // //Log::info('Initial Devices State:', ['devices' => $devices->pluck('state', 'serial_no')->toArray()]);


        foreach ($devices as $device) {
            //Log::info('controller Looking for GeoCode entry for serial number:', ['serial_no' => $device->serial_no]); //ADDED NEW

            // Fetch the latest 'updated_at' from the GeoCode table for each device
            $geoCodeEntry = GeoCode::where('serial_no', $device->serial_no)->latest('updated_at')->orderByDesc('id')->first();


            //Log::info('controller GeoCode entry found:', ['geoCodeEntry' => $geoCodeEntry]); // NEWLY ADDED

            // Check if there is a GeoCode entry and get its status and updated_at timestamp
            if ($geoCodeEntry) {
                $device->status = $geoCodeEntry->status ?? 'no geo data';
                $lastUpdated = Carbon::parse($geoCodeEntry->updated_at);
            } else {
                $device->status = 'no geo data';
                // If there is no GeoCode entry, you might want to use the Device's updated_at or set a default value
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


        $creationEnabled = true;

        return view('device-manager', [
            'devices' => $devices, 'pagination_size' => $pagination_size, 'search' => $search,
            'creationEnabled' => $creationEnabled,
            'statusFilter' => $this->statusFilter(),
            'statusOptions' => $this->ownedDevices('', false)->selectRaw($this->statusExpression().' AS operating_status')->get()->pluck('operating_status')->unique()->sort()->values()->all(),
        ]);
    }


    // import this class loads in the values for the map when show IS checked

    public function allDevices(Request $request)
{
    $search = $this->nameSearch($request);
    $devices = $this->ownedDevices($search)
             ->get(['id', 'state', 'serial_no', 'sku', 'name', 'latitude', 'longitude', 'address_1', 'address_2', 'updated_at']);

    foreach ($devices as $device) {
            // Fetching the geo status as before
            $geo_status = GeoCode::where('serial_no', $device->serial_no)
                ->latest('updated_at')->orderByDesc('id')
                ->first();

            // Setting the state from the GeoCode status or defaulting to 'no geo data'
            $device->status = $geo_status->status ?? 'no geo data';

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
    $search = $this->nameSearch($request);
    $pagination_size = $request->input('show', env('PAGINATION_SIZE', 10));

    $devices = $this->ownedDevices($search)
             ->select('id', 'state', 'serial_no', 'sku', 'name', 'latitude', 'longitude', 'address_1', 'user_id', 'updated_at')
             ->paginate($pagination_size)->appends(['show' => $pagination_size, 'search' => $search, 'status' => $this->statusFilter()]);

    foreach ($devices as $device) {

            $geo_status = GeoCode::where('serial_no', $device->serial_no)->latest('updated_at')->orderByDesc('id')->first(['status', 'updated_at']);

            // Set the device state from the GeoCode entry or use a default value
            $device->status = $geo_status->status ?? 'no geo data';

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

    private function nameSearch(Request $request): string
    {
        $validated = $request->validate(['search' => 'nullable|string|max:255']);

        return trim($validated['search'] ?? '');
    }

    private function statusFilter(): array
    {
        $value = request()->input('status', []) ?? [];
        if (is_string($value)) $value = $value === '' ? [] : [$value];
        $validated = validator(['status' => $value], [
            'status' => 'array|max:50', 'status.*' => 'string|max:255',
        ])->validate();
        return array_values(array_unique(array_filter(array_map('trim', $validated['status']))));
    }

    private function statusExpression(): string
    {
        return "COALESCE((SELECT status FROM geocode WHERE geocode.serial_no = devices.serial_no ORDER BY updated_at DESC, id DESC LIMIT 1), 'no geo data')";
    }

    private function ownedDevices(string $search, bool $filterStatus = true): Builder
    {
        $query = Device::where('state', 'active')->where('user_id', Auth::id());
        if ($filterStatus && $this->statusFilter() !== []) {
            $query->whereIn(\Illuminate\Support\Facades\DB::raw($this->statusExpression()), $this->statusFilter());
        }
        if ($search !== '') {
            // Bound parameters and an explicit escape character make %, _ and ! literal.
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ['%'.$literal.'%']);
        }

        return $query->orderBy('id');
    }


    public function archive(Request $request, $id)
    {
        $device = Device::findOrFail($id);
        abort_unless((int) $device->user_id === (int) $request->user()->id, 403);
        $device->state = 'archived';
        $device->save();

        if ($request->is('api/*') || $request->wantsJson()) {
            return response()->json(['message' => 'Device archived successfully.', 'state' => $device->state]);
        }
        return redirect()->route('dashboard')->with('success', 'Device archived successfully.');
    }

    public function allDevicesAPI(Request $request)
    {
        try {
            $user_id = Auth::id(); // Get the authenticated user's ID
            if (!$user_id) {
                //Log::info('No user ID found');
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $devices = Device::where('state', 'active')->where('user_id', $user_id)
                ->get([
                    'id', 'state', 'serial_no', 'sku', 'name', 'latitude', 'longitude',
                    'address_1', 'address_2', 'updated_at', 'status_notification', 'zip_code'
                ]);

            foreach ($devices as $device) {
                $geo_status = GeoCode::where('serial_no', $device->serial_no)
                    ->latest('updated_at')->orderByDesc('id')
                    ->first();

                $device->status = $geo_status->status ?? 'no geo data';

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
            Log::error('device.list_failed');
            return response()->json(['error' => 'An error occurred while fetching devices'], 500);
        }
    }

}
