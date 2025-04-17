<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Api\SolarTrackerLog;
use App\Models\Api\EnergyMonitorLog;
use App\Models\DeviceRegister;
use App\Models\Hardware;
use Illuminate\Support\Facades\Log;


class DeviceLogController extends Controller
{
    // JSON Response Messages
    const SUCCESS_RESPONSE = "{\"msg\":\"success\"}";
    const INVALID_HARDWARE_RESPONSE = "{\"msg\":\"invalid hardware\"}";
	const DEVICE_NOT_REGISTERED = "{\"msg\":\"device is not registered\"}";

    public function logData(Request $request)
    {			
        $data = $request->data;
		$json = json_decode($data, true);
        $serial_no = (string) $request->serial_no;
		$device_register = DeviceRegister::where('serial_no', '=', $serial_no)->get()->first();
		if($device_register){
			$hardware_id = (int)$device_register->hardware_id;
			switch($hardware_id){
				case 1: // Smart Panels
					self::logSolarTrackerData($json, $serial_no);
					return self::SUCCESS_RESPONSE;
				case 2: // Energy Monitor
					self::logEnergyMonitorData($json, $serial_no);
					return self::SUCCESS_RESPONSE;
				default:
					// Do nothing
					return self::INVALID_HARDWARE_RESPONSE;
			
			}
		}else{
			return self::DEVICE_NOT_REGISTERED;
		}
    }

    // Logs the solar tracker data
    private function logSolarTrackerData($json, $serial_no)
    {
        if (isset($json['ps1'])) {
            $ps1 = (float) $json['ps1'];
        } else {
            $ps1 = null;
        }
        if (isset($json['ps2'])) {
            $ps2 = (float) $json['ps2'];
        } else {
            $ps2 = null;
        }
        if (isset($json['ps_avg'])) {
            $ps_avg = (float) $json['ps_avg'];
        } else {
            $ps_avg = null;
        }
        if (isset($json['pds'])) {
            $pds = (float) $json['pds'];
        } else {
            $pds = null;
        }
        if (isset($json['motor_speed'])) {
            $motor_speed = (float) $json['motor_speed'];
        } else {
            $motor_speed = null;
        }
        if (isset($json['temp'])) {
            $temp = (float) $json['temp'];
        } else {
            $temp = null;
        }
        if (isset($json['cts'])) {
            $cts = (int) $json['cts'];
        } else {
            $cts = null;
        }
        if (isset($json['state'])) {
            $state = (string) $json['state'];
        } else {
            $state = null;
        }
        $log = new SolarTrackerLog;
        $log->ps1 = $ps1;
        $log->ps2 = $ps2;
        $log->ps_avg = $ps_avg;
        $log->pds = $pds;
        $log->motor_speed = $motor_speed;
        $log->temp = $temp;
        $log->cts = $cts;
        $log->state = $state;
        $log->serial_no = $serial_no;
        $log->save();
    }

    // Logs the solar bus tap data
    private function logEnergyMonitorData($json, $serial_no)
    {
        if (isset($json['v_batt'])) {
            $v_batt = (float) $json['v_batt'];
        } else {
            $v_batt = null;
        }
        if (isset($json['i_batt'])) {
            $i_batt = (float) $json['i_batt'];
        } else {
            $i_batt = null;
        }
        if (isset($json['v_sol'])) {
            $v_sol = (float) $json['v_sol'];
        } else {
            $v_sol = null;
        }
        if (isset($json['i_sol'])) {
            $i_sol = (float) $json['i_sol'];
        } else {
            $i_sol = null;
        }
        if (isset($json['i_inv'])) {
            $i_inv = (float) $json['i_inv'];
        } else {
            $i_inv = null;
        }
        if (isset($json['temp'])) {
            $temp = (float) $json['temp'];
        } else {
            $temp = null;
        }
        $log = new EnergyMonitorLog;
        $log->v_batt = $v_batt;
        $log->i_batt = $i_batt;
        $log->v_sol = $v_sol;
        $log->i_sol = $i_sol;
        $log->i_inv = $i_inv;
        $log->temp = $temp;
        $log->serial_no = $serial_no;
        $log->save();
    }
}