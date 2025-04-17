<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegister;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Models\Api\EnergyMonitorLog;
use App\Models\Api\SolarTrackerLog;
use App\Models\License;
use App\Models\Api\EnergyMonitorInfo;
use App\Models\SolarTrackerRemoteControl;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DeviceInfoController extends Controller
{
    public function index($id)
    {
		$device = DeviceRegister::where('id', $id)->get()->first();
        if($device)
		{
            $hardware_id = $device->hardware_id;
			$license = $device->license;
			if($hardware_id == 1) // SOLAR TRACKER
			{
                $stateMessage = 'default';
				$latestStatus = SolarTrackerLog::where('serial_no', $device->serial_no)
                ->orderBy('updated_at', 'desc')
                ->first();


                // Calculate the averages using the helper methods
                $tempAverages = $this->getTempAverages($device->serial_no);
                $psAverages = $this->getPSAverages($device->serial_no);
                $motorAvgs = $this->getmotorAvgs($device->serial_no);

                if ($latestStatus) {
                    //Log::info("inside the loop " );
					$stateMessage = $latestStatus->state;
                    $latestStatus->updated_at_pst = $latestStatus->updated_at
                    ->timezone('America/Los_Angeles')
                    ->format('F j, Y, g:i A T'); // More readable format with timezone abbreviation

                    $latestStatus->cts = $latestStatus->cts ?? 0;
                    $latestStatus->state = $latestStatus->state ?? 0;
                    $latestStatus->updated_at_pst = $latestStatus->updated_at_pst ?? 0;
                    $latestStatus->ps1 = $latestStatus->ps1 ?? 0; // Ensure ps1 is set to 0 if null
                    $latestStatus->ps_avg = $latestStatus->ps_avg ?? 0; // Ensure ps_avg is set to 0 if null
                    $latestStatus->motor_speed = $latestStatus->motor_speed ?? 0; // Ensure motor_speed is set to 0 if null
                    $latestStatus->ps2 = $latestStatus->ps2 ?? 0; // Ensure ps2 is set to 0 if null
                    $latestStatus->pds = $latestStatus->pds ?? 0; // Ensure pds is set to 0 if null
                    $latestStatus->temp = $latestStatus->temp ?? 0; // Ensure temp is set to 0 if null


                }else {
                    $latestStatus = (object)[
                        'cts' => 0,
                        'state' => 0,
                        'updated_at_pst' => 0,
                        'ps1' => 0,
                        'ps_avg' => 0, // Ensure ps_avg is set to 0
                        'motor_speed' => 0, // Ensure motor_speed is set to 0
                        'ps2' => 0, // Ensure ps2 is set to 0
                        'pds' => 0, // Ensure pds is set to 0
                        'temp' => 0 // Ensure temp is set to 0
                    ];
                }

                 //Log::info("Formatted updated_at_pst: " . $latestStatus->updated_at_pst);

				return view('device-info', [
					'device' => $device,
					'license' => $license,
					'latestStatus' => $latestStatus,
					'tempAverages' => $tempAverages,
					'psAverages' => $psAverages,
					'motorAvgs' => $motorAvgs,
					'stateMessage' => $stateMessage,
					'ctsValue' => $latestStatus ? $latestStatus->cts : null,
				]);            
			}
			elseif($hardware_id == 2 || $hardware_id == 3) // ENERGY MONITOR WIFI OR ENERGY MONITOR 4G/GPS
			{


					$arr = EnergyMonitorInfo::where('serial_no', $device->serial_no)->orderBy('created_at', 'desc')->take(1440)->get();
                    if (count($arr) > 1) {
                        $timestamp_1 = Carbon::parse($arr[0]->created_at);
                        $timestamp_2 = Carbon::parse($arr[1]->created_at);
                        $delta = $timestamp_1->diffInSeconds($timestamp_2) / 60.0;
                    } else {
                        // Handle the case where there are not enough entries
                        // You could log this situation or set default values for $delta
                        Log::warning("Not enough data to calculate timestamps for device {$device->serial_no}");
                        $delta = 0; // Default or calculated value based on your requirements
                    }
					
                    $e_batt = [];
                    $emissions = [];
                    $savings = [];

                    foreach ($arr as $a) {
                        array_push($e_batt, $a->e_batt);
                        array_push($emissions, $a->emissions);
                        array_push($savings, $a->savings);
                    }
					
                    // --------------------------------------------------------------

                    $today = Carbon::today();
                    $savingsData = EnergyMonitorInfo::where('serial_no', $device->serial_no)
                        ->whereDate('created_at', '>=', $today->copy()->subDays(6))
                        ->groupBy(DB::raw('Date(created_at)'))
                        ->orderBy('created_at', 'asc')
                        ->get([
                            DB::raw('Date(created_at) as date'),
                            DB::raw('SUM(savings) as daily_savings')
                        ]);

                    $dates = $savingsData->pluck('date')->all();
                    $dailySavings = $savingsData->pluck('daily_savings')->all();
                    // --------------------------------------------------------------                        

                    $today = Carbon::today();
                    // Query to aggregate p_batt data on an hourly basis for today
                    $pBattData = EnergyMonitorInfo::where('serial_no', $device->serial_no)
                        ->whereDate('created_at', '=', $today)
                        ->select([
                            DB::raw('HOUR(created_at) as hour'), // Extracting the hour part of the created_at timestamp
                            DB::raw('SUM(p_batt) as total_p_batt') // Summing up p_batt for each hour
                        ])
                        ->groupBy('hour') // Grouping the result by hour
                        ->orderBy('hour', 'asc') // Ordering the result by hour in ascending order
                        ->get();

                    $hours = $pBattData->pluck('hour')->all(); // Extracting hours for X-axis labels
                    $totalPBatt = $pBattData->pluck('total_p_batt')->all(); // Extracting total p_batt values for Y-axis
                    
                  
                    // --------------------------------------------------------------

                    // Retrieve sum of e_batt for the last 7 days
                    $eBattData = EnergyMonitorInfo::where('serial_no', $device->serial_no)
                        ->whereDate('created_at', '>=', $today->copy()->subDays(6))
                        ->groupBy(DB::raw('Date(created_at)'))
                        ->orderBy('created_at', 'asc')
                        ->get([
                            DB::raw('Date(created_at) as date'),
                            DB::raw('SUM(e_batt) as daily_e_batt')
                        ]);
						
						

                    $dates = $eBattData->pluck('date')->all();
                    $dailyEBatt = $eBattData->pluck('daily_e_batt')->all();
                    // -------------------------------------------------------------- 
                    $latestEnergyMonitorLog = EnergyMonitorLog::where('serial_no', $device->serial_no)
                            ->latest()
                            ->first();

                    $latestLogData = [];
                    if($latestEnergyMonitorLog) 
					{
                         $formattedCreatedAt = $latestEnergyMonitorLog->created_at->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');
                         $formattedUpdatedAt = $latestEnergyMonitorLog->updated_at->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');

                        // Transform the log entry into a more convenient format if necessary or pass it directly this are OG TABLE AND NAMES
                        $latestLogData = [
                            'v_batt' => $latestEnergyMonitorLog->v_batt,
                            'i_batt' => $latestEnergyMonitorLog->i_batt,
                            'v_sol' => $latestEnergyMonitorLog->v_sol,
                            'i_sol' => $latestEnergyMonitorLog->i_sol,
                            'i_inv' => $latestEnergyMonitorLog->i_inv,
                            'temp' => $latestEnergyMonitorLog->temp,
                            'serial_no' => $latestEnergyMonitorLog->serial_no,
                            'created_at' => $formattedCreatedAt,
                            'last_updated' => $formattedUpdatedAt,
                        ];
                    }
					else
					{
                        // Log that no data was found
                        Log::warning('No latest solar tracker log data found for device with serial number: ' . $device->serial_no);
                    }
                    return view('device-info', [
                        'device' => $device,
						'license' => $license,
                        'e_batt' => $e_batt,
                        'emissions' => $emissions,
                        'savings' => $savings,
                        'dates' => $dates, // These are the dates for the X-axis
                        'dailySavings' => $dailySavings, // This is the aggregated savings data for the Y-axis
                        'hours' => $hours, // These are the hours for the X-axis
                        'totalPBatt' => $totalPBatt, // This is the aggregated p_batt data for the Y-axis
                        'dailyEBatt' => $dailyEBatt, // Sum of e_batt for each day for the Y-axis
                        'tablelatestLog' => $latestLogData,
                    ]);
			}
			else
			{
				return redirect()->route('devices.index')->with('error', 'Hardware invalid');
			}
        } 
		else 
		{
            return redirect()->route('devices.index')->with('error', 'Device not found');
        }
    }

    public function getLatestStatusJson($serial_no)
{
    // Fetch the device by its serial number
    $device = DeviceRegister::where('serial_no', $serial_no)->first();

    if (!$device) {
        return response()->json(['error' => 'Device not found'], 404);
    }

    $stateMessage = 'default';

    if ($device->hardware_id == 1) { // Solar Tracker
        $latestStatus = SolarTrackerLog::where('serial_no', $serial_no)
            ->orderBy('updated_at', 'desc')
            ->first();

        // Calculate the averages using the helper methods
        $tempAverages = $this->getTempAverages($serial_no);
        $psAverages = $this->getPSAverages($serial_no);
        $motorAvgs = $this->getmotorAvgs($serial_no);

        if ($latestStatus) {
            $stateMessage = $latestStatus->state;
            $latestStatus->updated_at_pst = $latestStatus->updated_at
                ->timezone('America/Los_Angeles')
                ->format('F j, Y, g:i A T'); // More readable format with timezone abbreviation

            $latestStatus->cts = $latestStatus->cts ?? 0;
            $latestStatus->state = $latestStatus->state ?? 0;
            $latestStatus->updated_at_pst = $latestStatus->updated_at_pst ?? 0;
            $latestStatus->ps1 = $latestStatus->ps1 ?? 0;
            $latestStatus->ps_avg = $latestStatus->ps_avg ?? 0;
            $latestStatus->motor_speed = $latestStatus->motor_speed ?? 0;
            $latestStatus->ps2 = $latestStatus->ps2 ?? 0;
            $latestStatus->pds = $latestStatus->pds ?? 0;
            $latestStatus->temp = $latestStatus->temp ?? 0;
        } else {
            $latestStatus = (object)[
                'cts' => 0,
                'state' => 0,
                'updated_at_pst' => 0,
                'ps1' => 0,
                'ps_avg' => 0,
                'motor_speed' => 0,
                'ps2' => 0,
                'pds' => 0,
                'temp' => 0
            ];
        }

        return response()->json([
            'device' => $serial_no,
            'latestStatus' => $latestStatus,
            'tempAverages' => $tempAverages,
            'psAverages' => $psAverages,
            'motorAvgs' => $motorAvgs,
            'stateMessage' => $stateMessage,
            'ctsValue' => $latestStatus ? $latestStatus->cts : null,
        ]);

    } elseif ($device->hardware_id == 2) { // Energy Monitor
        $latestEnergyMonitorLog = EnergyMonitorLog::where('serial_no', $serial_no)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($latestEnergyMonitorLog) {
            $formattedCreatedAt = $latestEnergyMonitorLog->created_at->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');
            $formattedUpdatedAt = $latestEnergyMonitorLog->updated_at->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');

            return response()->json([
                'v_batt' => $latestEnergyMonitorLog->v_batt ?? 0,
                'i_batt' => $latestEnergyMonitorLog->i_batt ?? 0,
                'v_sol' => $latestEnergyMonitorLog->v_sol ?? 0,
                'i_sol' => $latestEnergyMonitorLog->i_sol ?? 0,
                'i_inv' => $latestEnergyMonitorLog->i_inv ?? 0,
                'temp' => $latestEnergyMonitorLog->temp ?? 0,
                'serial_no' => $latestEnergyMonitorLog->serial_no,
                'created_at' => $formattedCreatedAt,
                'last_updated' => $formattedUpdatedAt,
            ]);
        } else {
            return response()->json(['error' => 'No data available for this device'], 404);
        }

    } else {
        return response()->json(['error' => 'Unsupported hardware type'], 400);
    }
}


    public function fetchLatestSolarData($id)
    {
        //Log::info("Fetching latest energy monitor data for device ID: $id");

        $device = DeviceRegister::where('id', $id)->first();
        if (!$device) {
            Log::error("Device not found with ID: $id");
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Check if the device is supposed to have energy monitor logs
        if ($device->hardware_id == 2 || $device->hardware_id == 3) { // Assuming 2 and 3 are IDs for Energy Monitors
            $latestLog = EnergyMonitorLog::where('serial_no', $device->serial_no)->latest()->first();

            if (!$latestLog) {
                Log::warning("No data available for device with serial number: {$device->serial_no}");
                return response()->json(['error' => 'No data available for this device'], 404);
            }

            // Format the date-time to be more human-readable and convert to PST
            $formattedCreatedAt = $latestLog->created_at->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');
            $formattedLastUpdated = Carbon::now()->timezone('America/Los_Angeles')->format('l, F j, Y h:i A');

        


            // Format the data as needed for the front end this is for refreshed table
            $data = [                                       
                'v_batt' => $latestLog->v_batt,
                'i_batt' => $latestLog->i_batt,
                'v_sol'  => $latestLog->v_sol,
                'i_sol'  => $latestLog->i_sol,
                'i_inv'  => $latestLog->i_inv,
                'temp'   => $latestLog->temp,
                'serial_no'   => $latestLog->serial_no,
                'created_at' => $formattedCreatedAt,
                'last_updated' => $formattedLastUpdated 

            ];

            //Log::info("Data fetched successfully for device ID: $id", $data);
            return response()->json($data);
        } else {
            Log::error("Device ID $id is not associated with Energy Monitors");
            return response()->json(['error' => 'This device is not associated with Energy Monitors'], 400);
        }
    }
    public function getTempAverages($deviceSerialNo)
{
    // Get the last 9000 entries with 'temp' and 'created_at' columns
    $tempEntries = SolarTrackerLog::where('serial_no', $deviceSerialNo)
        ->orderBy('updated_at', 'desc')
        ->limit(9000)
        ->get(['temp', 'created_at']); // Select 'temp' and 'created_at' columns

    $averages = [];
    $tempWithTimestamps = [];
    
    for ($i = 0; $i < count($tempEntries); $i += 360) { // Change from 20 to 18
        $subset = $tempEntries->slice($i, 360); // Get the current subset of 360 entries
        $average = $subset->avg('temp'); // Calculate average temperature
        $timestamps = $subset->pluck('created_at'); // Get timestamps for the subset
        
        // Collect the temperatures with timestamps for logging
        foreach ($subset as $entry) {
            $pstTimestamp = Carbon::parse($entry->created_at)->setTimezone('America/Los_Angeles'); // Convert to PST
            $tempWithTimestamps[] = [
                'temp' => $entry->temp,
                'timestamp' => $pstTimestamp->toDateTimeString()
            ];
        }
        
        // Store the rounded average
        $averages[] = round($average, 3);
    }
    
    // Log the temperatures with timestamps
    foreach ($tempWithTimestamps as $entry) {
        //Log::info('Temperature: ' . $entry['temp'] . ', Timestamp (PST): ' . $entry['timestamp']);
    }
    
    // Log the averages
    foreach ($averages as $average) {
        //Log::info('Average Temperature: ' . $average);
    }
    
    // Unset the $tempWithTimestamps array
    unset($tempWithTimestamps);

    return array_reverse($averages); // Reverse the array of averages before returning
}
// for the EMON graph in swift VVVV
protected function getSavingsData($serialNo)
{
    $today = Carbon::today();
    $savingsData = EnergyMonitorInfo::where('serial_no', $serialNo)
        ->whereDate('created_at', '=', $today)
        ->groupBy(DB::raw('HOUR(created_at)'))
        ->orderBy('created_at', 'asc')
        ->get([
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COALESCE(SUM(savings), 0) as total_savings') // Use COALESCE to handle null values
        ]);

    return $savingsData->map(function($item) {
        return [
            'hour' => $item->hour,
            'total_savings' => $item->total_savings ?? 0 // Ensure zero if null
        ];
    });
}


protected function getBatteryPowerData($serialNo)
{
    $today = Carbon::today();
    $batteryPowerData = EnergyMonitorInfo::where('serial_no', $serialNo)
        ->whereDate('created_at', '=', $today)
        ->groupBy(DB::raw('HOUR(created_at)'))
        ->orderBy('created_at', 'asc')
        ->get([
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COALESCE(SUM(p_batt), 0) as total_p_batt')
        ]);

    return $batteryPowerData->map(function($item) {
        return [
            'hour' => $item->hour,
            'total_p_batt' => $item->total_p_batt ?? 0
        ];
    });
}


protected function getBatteryEnergyData($serialNo)
{
    $today = Carbon::today();
    $batteryEnergyData = EnergyMonitorInfo::where('serial_no', $serialNo)
        ->whereDate('created_at', '=', $today)
        ->groupBy(DB::raw('HOUR(created_at)'))
        ->orderBy('created_at', 'asc')
        ->get([
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COALESCE(SUM(e_batt), 0) as total_e_batt')
        ]);

    return $batteryEnergyData->map(function($item) {
        return [
            'hour' => $item->hour,
            'total_e_batt' => $item->total_e_batt ?? 0
        ];
    });
}

protected function getEmissionsData($serialNo)
{
    $today = Carbon::today();
    $emissionsData = EnergyMonitorInfo::where('serial_no', $serialNo)
        ->whereDate('created_at', '=', $today)
        ->groupBy(DB::raw('HOUR(created_at)'))
        ->orderBy('created_at', 'asc')
        ->get([
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COALESCE(SUM(emissions), 0) as total_emissions')
        ]);

    return $emissionsData->map(function($item) {
        return [
            'hour' => $item->hour,
            'total_emissions' => $item->total_emissions ?? 0
        ];
    });
}



    
// for the EMON graph in swift ^^^^^


public function getDeviceData($id)
{
    $device = DeviceRegister::where('id', $id)->first();

    if ($device) {
        if ($device->hardware_id == 1) { // Solar Tracker
            // Fetch data using helper functions
            $tempAverages = $this->getTempAverages($device->serial_no);
            $psAverages = $this->getPSAverages($device->serial_no);
            $motorAvgs = $this->getmotorAvgs($device->serial_no);

            return response()->json([
                'tempAverages' => $tempAverages,
                'psAverages' => $psAverages,
                'motorAvgs' => $motorAvgs
            ]);

        } elseif ($device->hardware_id == 2) { // Energy Monitor
            // Fetch data using helper functions with null checks
            $savingsData = $this->getSavingsData($device->serial_no);
            $batteryPowerData = $this->getBatteryPowerData($device->serial_no);
            $batteryEnergyData = $this->getBatteryEnergyData($device->serial_no);
            $emissionsData = $this->getEmissionsData($device->serial_no);

            return response()->json([
                'savingsData' => $savingsData,
                'batteryPowerData' => $batteryPowerData,
                'batteryEnergyData' => $batteryEnergyData,
                'emissionsData' => $emissionsData
            ]);

        } else {
            return response()->json(['error' => 'Invalid hardware ID'], 404);
        }
    } else {
        return response()->json(['error' => 'Device not found'], 404);
    }
}



public function getPSAverages($deviceSerialNo)
{
    $psAverages = SolarTrackerLog::where('serial_no', $deviceSerialNo)
        ->orderBy('updated_at', 'desc')
        ->limit(9000) // Get the last 240 entries
        ->pluck('ps_avg');

    $averagesPS = [];
    for ($i = 0; $i < count($psAverages); $i += 360) { // Change from 20 to 18
        $averagePS = $psAverages->skip($i)->take(360)->avg(); // Calculate average over 18 entries
        $averagesPS[] = round($averagePS, 3);
    }

    return array_reverse($averagesPS); // Reverse the array before returning
}

public function getmotorAvgs($deviceSerialNo)
{
    $motorAvgs = SolarTrackerLog::where('serial_no', $deviceSerialNo)
        ->orderBy('updated_at', 'desc')
        ->limit(9000) // Get the last 240 entries
        ->pluck('motor_speed');

    $averagesMotor = [];
    for ($i = 0; $i < count($motorAvgs); $i += 360) { // Change from 20 to 18
        $averageMotor = $motorAvgs->skip($i)->take(360)->avg(); // Calculate average over 18 entries
        $averagesMotor[] = round($averageMotor, 3);
    }

    return array_reverse($averagesMotor); // Reverse the array before returning
}


    public function refresh($id)
    {
            $device = DeviceRegister::find($id); // Retrieve the device by its ID from the database

        if (!$device) {
            // Return a JSON response with error message
            return response()->json(['error' => 'Device not found'], 404);
        }

        // Get the latest data from the smart_panel_logs table for the matching serial_no
        $latestLog = SolarTrackerLog::where('serial_no', $device->serial_no)
            ->orderBy('updated_at', 'desc')
            ->first();

        if ($latestLog) {
            $formattedUpdatedAt = Carbon::parse($latestLog->updated_at, 'UTC')
            ->timezone('America/Los_Angeles')
            ->format('F j, Y, g:i A T');

            $latestLog->updated_at_pst = $formattedUpdatedAt;


            // Return the latest log as JSON
            return response()->json($latestLog);
        } else {
            // Return a JSON response with error message
            return response()->json(['error' => 'No matching data found in smart_panel_logs'], 404);
        }
    }

    //button
    public function updateSolarTracker(Request $request)
{
    //Log::info('updateSolarTracker API called', ['request' => $request->all()]);

    $validatedData = $request->validate([
        'mode' => 'required|boolean',
        'motor_speed' => 'required|numeric|min:-100|max:100',
        'serial_no' => 'required|string|max:255',
    ]);

    //Log::info('Validated Data', $validatedData);

    $panel = SolarTrackerRemoteControl::updateOrCreate(
        ['serial_no' => $validatedData['serial_no']], // Search criteria
        [
            'mode' => $validatedData['mode'], // Data to update or create
            'motor_speed' => $validatedData['motor_speed']
        ]
    );

    //Log::info('SolarTrackerRemoteControl updated or created', ['panel' => $panel]);

    return response()->json(['success' => true, 'message' => 'Solar panel updated successfully.']);
}

//API
public function getSolarTrackerStatus(Request $request)
{
    $validatedData = $request->validate([
        'serial_no' => 'required|string|max:255',
    ]);

    $panel = SolarTrackerRemoteControl::where('serial_no', $validatedData['serial_no'])->first();

    if ($panel) {
        return response()->json([
            'success' => true,
            'mode' => $panel->mode,
            'motor_speed' => $panel->motor_speed,
        ]);
    } else {
        return response()->json(['success' => false, 'message' => 'Panel not found'], 404);
    }

    //Log::info('got solar tracking statuses and speed');

}

    

    public function fetchGraphData(Request $request, $id)
    {
        $device = DeviceRegister::find($id);
        $timeFrame = $request->input('timeFrame');
        ////Log::info('Received timeFrame:', ['timeFrame' => $request->input('timeFrame')]);
        ////Log::info('Request data:', $request->all());
        $timeFrame = $request->input('timeFrame', 'today'); // Default to empty string if not provided


        // Log incoming request data
        ////Log::info("Request data:", $request->all());
    
        if (!$device) {
            Log::error("Device not found with ID: {$id}");
            return response()->json(['error' => 'Device not found'], 404);
        }
    
        if (!$timeFrame) {
            Log::error("Time frame not provided or invalid for device ID: {$id}");
            return response()->json(['error' => 'Invalid or missing time frame'], 400);
        }
    
        $data = $this->getDataForTimeFrame($device->serial_no, $timeFrame);
    
        ////Log::info("Data fetched successfully for device ID: {$id} with timeFrame: {$timeFrame}");
    
        return response()->json($data);
    }
    


    protected function getDataForTimeFrame($serialNo, $timeFrame)
    {
        $labels = [];
        $values = [];

        $query = EnergyMonitorInfo::where('serial_no', $serialNo);

        switch ($timeFrame) {
            case 'today':
            case 'yesterday':
                $startDate = $timeFrame === 'today' ? Carbon::today('UTC') : Carbon::yesterday('UTC');
                $endDate = $startDate->copy()->endOfDay();

                $dataPoints = $query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at')
                    ->get(['created_at', 'savings'])
                    ->groupBy(function ($date) {
                        // Group by hour after converting to PST
                        return Carbon::parse($date->created_at, 'UTC')->timezone('America/Los_Angeles')->format('Y-m-d H:00');
                    });

                foreach ($dataPoints as $hour => $points) {
                    $labels[] = Carbon::parse($hour, 'America/Los_Angeles')->format('g A'); // Format for display
                    $values[] = $points->sum('savings'); // Aggregate savings for the hour
                }


                // =============================================vv=====PBATT====vv================================================
                // Retrieve 'pBatt' data, similar to 'savings' but for the p_batt field
                $pBattDataPoints = $query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at')
                    ->get(['created_at', 'p_batt']) // Adjust field name as necessary
                    ->groupBy(function ($date) {
                        return Carbon::parse($date->created_at, 'UTC')->timezone('America/Los_Angeles')->format('Y-m-d H:00');
                    });

                $pBattValues = [];
                foreach ($pBattDataPoints as $hour => $points) {
                    $pBattValues[] = $points->sum('p_batt'); // Adjust aggregation as necessary
                }
                // ==============================================^^====PBATT====^^================================================

                $eBattDataPoints = $query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at')
                    ->get(['created_at', 'e_batt']) // Adjusted for e_batt field
                    ->groupBy(function ($date) {
                        // Group by hour after converting to a specific timezone, e.g., 'America/Los_Angeles'
                        return Carbon::parse($date->created_at, 'UTC')->timezone('America/Los_Angeles')->format('Y-m-d H:00');
                    });

                $eBattValues = []; // Initialize the array for storing aggregated e_batt values
                foreach ($eBattDataPoints as $hour => $points) {
                    $eBattValues[] = $points->sum('e_batt'); // Aggregate e_batt values for each group
                }
                // ==============================================^^====E_batt====^^================================================
                $emissionsDataPoints = $query->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at')
                    ->get(['created_at', 'emissions']) // Adjusted for emissions field
                    ->groupBy(function ($date) {
                        // Group by hour after converting to a specific timezone, e.g., 'America/Los_Angeles'
                        return Carbon::parse($date->created_at, 'UTC')->timezone('America/Los_Angeles')->format('Y-m-d H:00');
                    });

                $emissionsValues = []; // Initialize the array for storing aggregated emissions values
                foreach ($emissionsDataPoints as $hour => $points) {
                    $emissionsValues[] = $points->sum('emissions'); // Aggregate emissions values for each group
                }

                foreach ($labels as $index => $label) {
                    $value = $values[$index] ?? 'No Value'; // Safe check in case there is no corresponding value
                
                    //Log::info("Chart Entry #{$index}:");
                    //Log::info("Label: {$label}");
                    //Log::info("Value: {$value}");
                    //Log::info("--------------------------------------------------"); // Visual separator
                }
                


                break;

            case 'past7days':
                $dataPoints = $query->whereDate('created_at', '>=', Carbon::today('UTC')->subDays(6))
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(savings) as total_savings'))
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->get();

                    //Log::info('Data points retrieved.', ['dataPoints' => $dataPoints]);


                foreach ($dataPoints as $point) {
                    $labels[] = Carbon::parse($point->date, 'UTC')->timezone('America/Los_Angeles')->format('M d'); // Convert to PST and format for display
                    $values[] = $point->total_savings;

                    //Log::info("Added label and value", ['date' => $labels, 'total_savings' => $point->total_savings]);

                }

                //Log::info('All labels', ['labels' => $labels]);
                //Log::info('All values', ['values' => $values]);
                $labelValuePairs = array_combine($labels, $values);
                //Log::info('All label-value pairs for the graph:', ['labelValuePairs' => $labelValuePairs]);
                // ==================================================PBATT====================================================    
                // Fetch 'pBatt' data, similar process to 'savings'
                $pBattDataPoints = $query->whereDate('created_at', '>=', Carbon::today('UTC')->subDays(6))
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(p_batt) as total_p_batt')) // Adjust field name as necessary
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->get();

                $pBattValues = [];
                foreach ($pBattDataPoints as $point) {
                    $pBattValues[] = $point->total_p_batt;
                }
                // ==================================================PBATT====================================================

                $eBattDataPoints = $query->whereDate('created_at', '>=', Carbon::today('UTC')->subDays(6))
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(e_batt) as total_e_batt')) // Adjusted for e_batt field
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->get();

                $eBattValues = [];
                foreach ($eBattDataPoints as $point) {
                    $eBattValues[] = $point->total_e_batt;
                }



                $emissionsDataPoints = $query->whereDate('created_at', '>=', Carbon::today('UTC')->subDays(6))
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(emissions) as total_emissions')) // Adjusted for emissions field
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->get();

                $emissionsValues = [];
                foreach ($emissionsDataPoints as $point) {
                    $emissionsValues[] = $point->total_emissions; // Aggregate emissions values for each date
                }


                break;

            case 'monthToDate':
                $startOfMonth = Carbon::now('UTC')->startOfMonth();
                $endOfToday = Carbon::now('UTC')->endOfDay();

                $dataPoints = $query->whereBetween('created_at', [$startOfMonth, $endOfToday])
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(savings) as total_savings'))
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->orderBy('date')
                    ->get();

                foreach ($dataPoints as $point) {
                    // Convert each date to PST and format for display
                    $labels[] = Carbon::parse($point->date, 'UTC')->timezone('America/Los_Angeles')->format('M d');
                    $values[] = $point->total_savings;
                }

                // ==================================================PBATT====================================================
                // Retrieve 'pBatt' data, following the same logic as for 'savings'
                $pBattDataPoints = $query->whereBetween('created_at', [$startOfMonth, $endOfToday])
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(p_batt) as total_p_batt'))
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->orderBy('date')
                    ->get();

                $pBattValues = [];
                foreach ($pBattDataPoints as $point) {
                    //use existing labels from savings 
                    $pBattValues[] = $point->total_p_batt;
                }
                // ==================================================PBATT====================================================
                $eBattDataPoints = $query->whereBetween('created_at', [$startOfMonth, $endOfToday])
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(e_batt) as total_e_batt')) // Adjusted for e_batt
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->orderBy('date')
                    ->get();

                $eBattValues = [];
                foreach ($eBattDataPoints as $point) {
                    // Use existing labels from savings, assuming they match your date labels
                    $eBattValues[] = $point->total_e_batt;
                }

                $emissionsDataPoints = $query->whereBetween('created_at', [$startOfMonth, $endOfToday])
                    ->select(DB::raw('Date(created_at) as date'), DB::raw('SUM(emissions) as total_emissions')) // Adjusted for emissions
                    ->groupBy(DB::raw('Date(created_at)'))
                    ->orderBy('date')
                    ->get();

                $emissionsValues = [];
                foreach ($emissionsDataPoints as $point) {
                    // Use existing labels from savings, assuming they match your date labels
                    $emissionsValues[] = $point->total_emissions; // Aggregate emissions values for each date
                }


                break;

            case 'yearToDate':
                $startOfYear = Carbon::now('UTC')->startOfYear();
                $endOfToday = Carbon::now('UTC')->endOfDay();

                // Assuming `created_at` is indexed by UTC
                $dataPoints = $query->whereBetween('created_at', [$startOfYear, $endOfToday])
                    ->select(DB::raw('YEAR(created_at) as year'), DB::raw('WEEK(created_at) as week'), DB::raw('SUM(savings) as total_savings'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('WEEK(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('WEEK(created_at)'), 'asc')
                    ->get();

                foreach ($dataPoints as $point) {
                    // Use the Carbon instance to create a label for each week's start date
                    $weekStartDate = Carbon::now('UTC')->setISODate($point->year, $point->week)->startOfWeek();
                    $labels[] = $weekStartDate->format('M j'); // Outputs as 'Jan 1', 'Jan 8', etc.
                    $values[] = $point->total_savings;
                }
                // ==================================================PBATT====================================================
                // Fetching 'pBatt' data with the same logic
                $pBattDataPoints = $query->whereBetween('created_at', [$startOfYear, $endOfToday])
                    ->select(DB::raw('YEAR(created_at) as year'), DB::raw('WEEK(created_at) as week'), DB::raw('SUM(p_batt) as total_p_batt'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('WEEK(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('WEEK(created_at)'), 'asc')
                    ->get();

                $pBattValues = []; // Initialize the array to store pBatt aggregated values
                foreach ($pBattDataPoints as $point) {
                    // No need to create labels again, as they are already generated from the savings data
                    $pBattValues[] = $point->total_p_batt;
                }
                // ==================================================PBATT====================================================

                $eBattDataPoints = $query->whereBetween('created_at', [$startOfYear, $endOfToday])
                    ->select(DB::raw('YEAR(created_at) as year'), DB::raw('WEEK(created_at) as week'), DB::raw('SUM(e_batt) as total_e_batt'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('WEEK(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('WEEK(created_at)'), 'asc')
                    ->get();

                $eBattValues = []; // Initialize the array to store eBatt aggregated values
                foreach ($eBattDataPoints as $point) {
                    // Using the same logic as for pBatt, no need to adjust labels
                    $eBattValues[] = $point->total_e_batt;
                }

                $emissionsDataPoints = $query->whereBetween('created_at', [$startOfYear, $endOfToday])
                    ->select(DB::raw('YEAR(created_at) as year'), DB::raw('WEEK(created_at) as week'), DB::raw('SUM(emissions) as total_emissions'))
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('WEEK(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('WEEK(created_at)'), 'asc')
                    ->get();

                $emissionsValues = [];
                foreach ($emissionsDataPoints as $point) {
                    $emissionsValues[] = $point->total_emissions;
                }

                break;

            case 'lifetime':

                // Assuming your query is being built on the $query variable.
                $endOfToday = Carbon::now('UTC')->endOfDay();

                $dataPoints = $query->where('created_at', '<=', $endOfToday)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('SUM(savings) as total_savings')
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('MONTH(created_at)'), 'asc')
                    ->get();



                foreach ($dataPoints as $point) {
                    // Create a label for each month and year (e.g., "Jan 2024")
                    $monthName = Carbon::createFromDate($point->year, $point->month, 1, 'UTC')->format('M Y');
                    $labels[] = $monthName;
                    $values[] = $point->total_savings;

                    // Log each month's name and its total savings
                    // //Log::info("Month and Year: {$monthName}, Total Savings: {$point->total_savings}");
                }
                // //Log::info("Number of Data Points: " . $dataPoints->count());
                // ==================================================PBATT====================================================
                // Fetching 'pBatt' data with the same lifetime logic
                $pBattDataPoints = $query->where('created_at', '<=', $endOfToday)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('SUM(p_batt) as total_p_batt') // Adjust for the correct field name as needed
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('MONTH(created_at)'), 'asc')
                    ->get();

                $pBattValues = []; // Initialize array for pBatt values
                foreach ($pBattDataPoints as $point) {
                    // No need to create labels again; they are already generated from the savings data
                    $pBattValues[] = $point->total_p_batt;
                }

                $eBattDataPoints = $query->where('created_at', '<=', $endOfToday)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('SUM(e_batt) as total_e_batt') // Adjusted for e_batt field
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('MONTH(created_at)'), 'asc')
                    ->get();

                $eBattValues = []; // Initialize array for eBatt values
                foreach ($eBattDataPoints as $point) {
                    // Utilizing existing structure; no need to adjust labels
                    $eBattValues[] = $point->total_e_batt;
                }


                $emissionsDataPoints = $query->where('created_at', '<=', $endOfToday)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('SUM(emissions) as total_emissions')
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'), 'asc')
                    ->orderBy(DB::raw('MONTH(created_at)'), 'asc')
                    ->get();

                $emissionsValues = [];
                foreach ($emissionsDataPoints as $point) {
                    $emissionsValues[] = $point->total_emissions;
                }


                // ==================================================PBATT====================================================
                break;

                default:
                Log::error("Invalid time frame: $timeFrame");
                $pBattValues = [0]; // Default value
                break;

        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Savings',
                    'backgroundColor' => 'rgba(255, 99, 14402, 0.2)',
                    'borderColor' => 'rgba(255, 99, 14402, 1)',
                    'data' => $values,
                ],
            ],
            'pBatt' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Total Power Battery',
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'data' => $pBattValues,
                    ],
                ],
            ],
            'eBatt' => [ 
                'labels' => $labels, 
                'datasets' => [
                    [
                        'label' => 'Battery Energy',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'data' => $eBattValues,
                    ],
                ],
            ],
            'emissions' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Emissions',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'data' => $emissionsValues,
                    ],
                ],
            ],
        ];

    }
}
