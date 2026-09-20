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
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'serial_no' => 'required|string|max:255',
            'data' => 'required|string|max:16384',
        ]);
        if ($validator->fails()) {
            return $this->invalidPayload();
        }
        $data = $request->data;
		$json = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json) || substr(ltrim($data), 0, 1) !== '{') {
            return $this->invalidPayload();
        }
        $serial_no = (string) $request->serial_no;
		$device_register = DeviceRegister::where('serial_no', '=', $serial_no)->get()->first();
		if($device_register){
			$hardware_id = (int)$device_register->hardware_id;
			if (in_array($hardware_id, [1, 2], true) && !$this->validTelemetry($json, $hardware_id)) {
                return $this->invalidPayload();
            }
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

    private function invalidPayload()
    {
        // Never log payloads, serial numbers, credentials, or complete request URLs.
        Log::notice('device.telemetry_rejected', ['reason' => 'invalid_payload']);
        return response()->json(['msg' => 'invalid telemetry'], 422);
    }

    private function validTelemetry(array $data, int $hardware): bool
    {
        $fields = $hardware === 1
            ? ['ps1', 'ps2', 'ps_avg', 'pds', 'motor_speed', 'temp', 'cts', 'state']
            : ['v_batt', 'i_batt', 'v_sol', 'i_sol', 'i_inv', 'temp'];
        $hasValue = false;
        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                continue;
            }
            $value = $data[$field];
            if ($field === 'state') {
                if (!is_string($value) || trim($value) === '' || strlen($value) > 255) {
                    return false;
                }
            } elseif (!is_numeric($value) || !is_finite((float) $value)
                || ($field === 'cts' && filter_var($value, FILTER_VALIDATE_INT) === false)) {
                return false;
            }
            $hasValue = true;
        }
        // Partial reports and extension fields remain supported, but empty/unknown-only reports do not.
        return $hasValue;
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