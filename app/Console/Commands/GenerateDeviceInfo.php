<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Api\EnergyMonitorLog;
use App\Models\Api\EnergyMonitorInfo;
use Illuminate\Support\Facades\Log;
use App\Models\DeviceRegister;

// Constants
const CO2_EMISSIONS = 0.000857; // LBS / WHR
const WHR_USD = 0.00034; // WHR / USD
const DELTA = 0.00416; // in hours ratio of 15sec / 3600 sec

class GenerateDeviceInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device:generate-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Logs data for a device based on the serial number and provided data.';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
		$serial_numbers = DeviceRegister::pluck('serial_no');
		
		foreach($serial_numbers as $serial_no)
		{
			// Pull the most recent row of device data from device logs table
			$device_log = EnergyMonitorLog::where('serial_no', $serial_no)->orderBy('created_at', 'desc')->first();
			

			// Pull the most recent row of device info from device info table
			$device_info = EnergyMonitorInfo::where('serial_no', $serial_no)->orderBy('created_at', 'desc')->take(2)->get();
			
			// If the device logs leads the device info table then add a new row to the device info table		
			if($device_log)
			{
				if(sizeof($device_info) == 2)
				{
					// Get the timestamp of the most recent device log
					$log_timestamp = Carbon::parse($device_log->created_at);
					
					// Get the time stamp of the most recent device info
					$info_timestamp_1 = Carbon::parse($device_info[0]->created_at);
					
					// Get the timestamp of the 2nd to most recent device log
					$info_timestamp_2 = Carbon::parse($device_info[1]->created_at);

					// Delta is the differnce in time of two consecutive logs in hours
					$delta = $info_timestamp_1->diffInSeconds($info_timestamp_2) / 3600.0;
				
					// Get the Carbon instance for midnight of the same date as the timestamp
					$midnight = Carbon::parse($log_timestamp)->endOfDay();

					if($log_timestamp->gt($info_timestamp_1))
					{
						// Log::info("Generating power and energy results ...");
						
						// Calculate power
						$p_batt = $device_log->v_batt * $device_log->i_batt;
						$p_sol = $device_log->v_sol * $device_log->i_sol;
						$p_inv = $device_log->v_inv * $device_log->i_inv;
						
						// Calculate energy
						// Set energy to zero once the log has just passed midnight
						if($log_timestamp->diffInMinutes($midnight) >= 5)
						{
							$e_batt = $device_info[0]->e_batt + $p_batt * $delta;
							$e_sol = $device_info[0]->e_sol + $p_sol * $delta;
							$e_inv = $device_info[0]->e_inv + $p_inv * $delta;
						}
						else
						{
							$e_batt = 0.0;
							$e_sol = 0.0;
							$e_inv = 0.0;
						}
						
						// Calculate green house impacts 
						$emissions = abs($e_batt * CO2_EMISSIONS); // Pounds of CO2
						
						// Calculate economics or cash savings
						$savings = abs($e_batt * WHR_USD); // Dollars per watt-hour

						$new_info = new EnergyMonitorInfo;
						$new_info->p_batt = $p_batt;
						$new_info->p_sol = $p_sol;
						$new_info->p_inv = $p_inv;
						$new_info->e_batt = $e_batt;
						$new_info->e_sol = $e_sol;
						$new_info->e_inv = $e_inv;
						$new_info->emissions = $emissions;
						$new_info->savings = $savings;
						$new_info->serial_no = $serial_no;
						$new_info->save();
					}
					
				}
				else
				{
						// Calculate power
						$p_batt = $device_log->v_batt * $device_log->i_batt;
						$p_sol = $device_log->v_sol * $device_log->i_sol;
						$p_inv = $device_log->v_inv * $device_log->i_inv;
						
						// Calculate energy
						$e_batt = $p_batt * DELTA;
						$e_sol = $p_sol * DELTA;
						$e_inv = $p_inv * DELTA;
						
						// Calculate green house impacts 
						$emissions = abs($e_batt * CO2_EMISSIONS); // Pounds of CO2
						
						// Calculate economics or cash savings
						$savings = abs($e_batt * WHR_USD); // Dollars per watt-hour

						$new_info = new EnergyMonitorInfo;
						$new_info->p_batt = $p_batt;
						$new_info->p_sol = $p_sol;
						$new_info->p_inv = $p_inv;
						$new_info->e_batt = $e_batt;
						$new_info->e_sol = $e_sol;
						$new_info->e_inv = $e_inv;
						$new_info->emissions = $emissions;
						$new_info->savings = $savings;
						$new_info->serial_no = $serial_no;
						$new_info->save();
				}
			}
		}
		return 0;
    }
}
