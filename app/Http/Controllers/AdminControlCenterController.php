<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use App\Models\DeviceRegister;
use Illuminate\Support\Facades\Log;
use App\Models\SmartPanelLog;
use App\Models\GeoCode;
use App\Models\Api\SolarTrackerLog;




class AdminControlCenterController extends Controller
{
    /**
     * Show the firmware update form.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $firmwarePaginationSize = $request->input('firmware_show', 2); // Default pagination size for firmware
        $configPaginationSize = $request->input('config_show', 2); // Default pagination size for configurations

        // Log::info('Firmware pagination size: ' . $firmwarePaginationSize);
        // Log::info('Configuration pagination size: ' . $configPaginationSize);


        $firmwareUpdates = FirmwareVersions::orderBy('created_at', 'desc')->paginate($firmwarePaginationSize);
        $configVersions = ConfigVersions::orderBy('created_at', 'desc')->paginate($configPaginationSize);



        //$deviceLocations = DeviceRegister::select('latitude', 'longitude', 'address_1', 'sku', 'serial_no')->get();


        $endDate = new \DateTime();
        $startDate = (clone $endDate)->sub(new \DateInterval('P6M'));

        /*
        $deviceRegistrations = DeviceRegister::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('year', 'month')
            ->orderBy('year', 'asc')
            ->orderBy('month', 'asc')
            ->get();

        $labels = $deviceRegistrations->map(function ($item) {
            return \DateTime::createFromFormat('!m', $item->month)->format('M') . ' ' . $item->year;
        });
        */
         /*
        // Existing logic for accumulated data
        $runningTotal = 0;
        $accumulatedData = $deviceRegistrations->map(function ($item) use (&$runningTotal) {
            $runningTotal += $item->count;
            return $runningTotal;
        });
        */
        /*
        $newDeviceRegistrations = $deviceRegistrations->map(function ($registration) {
            return [
                'month' => \DateTime::createFromFormat('!m', $registration->month)->format('M') . ' ' . $registration->year,
                'new_devices' => $registration->count
            ];
        })->toArray(); // Convert the collection to an array
        */
        //$devices = DeviceRegister::with('hardware')->get();

         // Fetch all Solar Tracker Logs for devices in one go (if applicable)
        /*
        $solarTrackerSerialNos = $devices->where('hardware_id', 1)->pluck('serial_no');
        $solarTrackerLogs = SolarTrackerLog::whereIn('serial_no', $solarTrackerSerialNos)->latest()->get()->groupBy('serial_no');
	    */

        //$geoCodes = GeoCode::all()->keyBy('serial_no');

        // Augment devices with their matching GeoCode information
        /*
        $augmentedDevices = $devices->map(function ($device) use ($geoCodes,  $solarTrackerLogs) {
            $serialNo = $device->serial_no; // Assuming 'serial_no' is the column name in DeviceRegister
            if ($geoCodes->has($serialNo)) {
                // If there's a matching GeoCode, augment the device data with it
                $geoCode = $geoCodes->get($serialNo);
                // Assuming 'status' is a field you want from GeoCode; add as needed
                $device->geo_status = $geoCode->status; // Add GeoCode status to the device object
            } else {
                // Handle the case where there's no matching GeoCode, e.g., by setting a default status or leaving it undefined
                $device->geo_status = 'unknowN'; // Example default value
            }

            $device->hardware_name = $device->hardware ? $device->hardware->name : 'Unknown';
            if ($device->hardware_id == 1) {
                if ($solarTrackerLogs->has($serialNo)) { // New ADDITION
                    $solarTrackerLog = $solarTrackerLogs[$serialNo]->first() ?? null; // New ADDITION
                    $device->geo_status = $solarTrackerLog ? $solarTrackerLog->state : 'no solar data'; // New ADDITION
                } else { // New ADDITION
                    $device->geo_status = 'no solar data'; // New ADDITION
                }
            }
            return $device;
        });
        */

        // Log the augmented devices array
        // Log::info('Augmented Devices:', ['deviceLocations' => $augmentedDevices->toArray()]);



        //$newLabels = array_column($newDeviceRegistrations, 'month');
        //$newData = array_column($newDeviceRegistrations, 'new_devices');
        return view('admin-control-center', [
            'firmwareUpdates' => $firmwareUpdates,
            'configVersions' => $configVersions,
            'firmwarePaginationSize' => $firmwarePaginationSize,
            'configPaginationSize' => $configPaginationSize,
            //'labels' => $labels, // Existing labels for the accumulated data graph
            //'data' => $accumulatedData, // Existing data for the accumulated data graph
            //'newLabels' => $newLabels, // New labels for the new registrations graph
            // 'newData' => $newData, // New data for the new registrations graph
            //'deviceLocations' => $augmentedDevices,
        ]);
    }


    /**
     * Handle the firmware file upload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadFirmware(Request $request)
    {
        $request->validate([
            'firmware' => 'required|file|max:10240', // 10MB Max
            'description' => 'required|string|max:255',
            'prefix' => 'required|string|max:255' // Optional prefix field
        ]);

        $file = $request->file('firmware');
        $version = $this->generateFirmwareVersionNumber();
        $prefix = $request->input('prefix');
        $filename = $prefix ? "{$prefix}_firmware_{$version}.bin" : "firmware_{$version}.bin";
        $path = $file->storeAs('public/firmware', $filename);

        if ($path) {
            FirmwareVersions::create([
                'version' => $version,
                'prefix' => $prefix,
                'file_path' => $path,
                'description' => $request->input('description')
            ]);

            $fullPath = Storage::disk('public')->url($filename);
            $message = "Firmware v{$version} uploaded successfully! File located at: " . $fullPath;
            return back()->with('success', $message);
        } else {
            return back()->with('error', 'There was an issue uploading the firmware file.');
        }
    }

    /**
     * Handle the JSON configuration file upload.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function uploadConfig(Request $request)
    {
        $request->validate([
            'config' => 'required|file|mimes:json|max:1024', // 1MB Max for JSON file
            'description' => 'required|string|max:255',
            'prefix' => 'required|string|max:255' // Optional prefix field
        ]);

        $configFile = $request->file('config');
        $configFileVersion = $this->generateConfigVersionNumber();
        $prefix = $request->input('prefix');
        $configFilename = $prefix ? "{$prefix}_config_{$configFileVersion}.json" : "config_{$configFileVersion}.json";
        $configPath = $configFile->storeAs('public/config', $configFilename);

        if ($configPath) {
            ConfigVersions::create([
                'file_path' => $configPath,
                'prefix' => $prefix,
                'version' => $configFileVersion,
                'description' => $request->input('description') // Use description from the request
            ]);

            Log::info('Config file uploaded successfully! File located at: ' . Storage::disk('public')->url($configFilename));
            return back()->with('success', "Config file uploaded successfully!");
        } else {
            return back()->with('error', 'There was an issue uploading the config file.');
        }
    }
    /**
     * Generate a new version number.
     *
     * @return string
     */
    private function generateFirmwareVersionNumber()
    {
        $latestVersion = FirmwareVersions::orderBy('version', 'desc')->first();
        return $latestVersion ? (((int) $latestVersion->version) + 1) : '1';
    }

    /**
     * Generate a new version number for config files.
     *
     * @return string
     */
    private function generateConfigVersionNumber()
    {
        $latestConfigVersion = ConfigVersions::orderBy('version', 'desc')->first();
        return $latestConfigVersion ? (((int) $latestConfigVersion->version) + 1) : '1';
    }

    /**
     * Get the latest version of the config
     * @param prefix Alphanumeric representation of a product
     * @return The string represents the latest version of the config table 
     */
    public function getLatestConfigVersionNumber($prefix = null)
    {
        $latestConfig = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestConfig){
			$latestVersion = array('version' => (string)$latestConfig->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
        return $json;
    }

    /**
     * Get the latest version of the firmware
     * @param prefix Alphanumeric representation of a product
     * @return The string represents the latest version of the config table 
     */
    public function getLatestFirmwareVersionNumber($prefix = null)
    {
        $latestFirmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestFirmware){
			$latestVersion = array('version' => (string)$latestFirmware->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
        return $json;
    }

    /**
     * Serve the firmware file.
     *
     * @param  string|null  $version
     * @return \Illuminate\Http\Response
     */
    public function serveFirmwareByVersion($version = null)
    {
        // Log::info('Firmware request received. version: ' . $version);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($version) {
            $firmware = FirmwareVersions::where('version', $version)->first();
            if ($firmware) {
                // Log::info('Specific firmware version requested: ' . $firmware->version);
            } else {
                // Log::info('Requested firmware version not found: ' . $version);
            }
        } else {
            $firmware = FirmwareVersions::latest()->first();
            // Log::info('No version version requested. Serving latest firmware version: ' . $firmware->version);
        }

        if (!$firmware || !Storage::exists($firmware->file_path)) {
            // Log::error('Firmware file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
            abort(404, 'Firmware file not found.');
        }

        return Storage::download($firmware->file_path);
    }

    /**
     * Serve the configuration file.
     *
     * @param  string|null  $version
     * @return \Illuminate\Http\Response
     */
    public function serveConfigByVersion($version = null)
    {
        // Log::info('Configuration file request received. Version: ' . $version);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($version) {
            $config = ConfigVersions::where('version', $version)->first();
            if ($config) {
                // Log::info('Specific config version requested: ' . $config->version);
            } else {
                // Log::info('Requested config version not found: ' . $version);
            }
        } else {
            $config = ConfigVersions::latest()->first();
            // Log::info('No specific version requested. Serving latest config version: ' . $config->version);
        }

        if (!$config || !Storage::exists($config->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($config->file_path);
    }


    public function serveFirmwareByPrefix($prefix = null)
    {
        // Log::info('Firmware file request received. Prefix: ' . $prefix);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($prefix) {
            $firmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
            if ($firmware) {
                // Log::info('Specific firmware prefix requested: ' . $firmware->prefix);
            } else {
                // Log::info('Requested firmware prefix not found: ' . $prefix);
            }
        } else {
            // Log::info('No specific prefix requested. Failed to serve.');
        }

        if (!$firmware || !Storage::exists($firmware->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($firmware->file_path);
    }


    public function serveConfigByPrefix($prefix = null)
    {
        // Log::info('Configuration file request received. Version: ' . $prefix);
        // Log::info('Full Request URL: ' . request()->fullUrl());

        if ($prefix) {
            $config = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
            if ($config) {
                // Log::info('Specific config prefix requested: ' . $config->prefix);
            } else {
                // Log::info('Requested config prefix not found: ' . $prefix);
            }
        } else {
            // Log::info('No specific prefix requested. Failed to serve config.');
        }

        if (!$config || !Storage::exists($config->file_path)) {
            // Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
            abort(404, 'Configuration file not found.');
        }

        return Storage::download($config->file_path);
    }
}
