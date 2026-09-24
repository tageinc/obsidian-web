<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelemetryDuplicateDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Api\SolarTrackerLog;
use App\Models\Device;


class DeviceLogController extends Controller
{
    // JSON Response Messages
    const SUCCESS_RESPONSE = "{\"msg\":\"success\"}";
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
        $data = (string) $request->data;
		$json = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json) || substr(ltrim($data), 0, 1) !== '{') {
            return $this->invalidPayload();
        }
        $serial_no = (string) $request->serial_no;
		$create_device = Device::where('serial_no', '=', $serial_no)->get()->first();
		if($create_device){
            if (!$this->validTelemetry($json)) {
                return $this->invalidPayload();
            }
            // Bounded duplicate detection: reject identical payloads arriving
            // within the dedup window so the device can observe the 409 and
            // avoid a false-positive "success" for its network retry.
            if ($this->isDuplicate($serial_no, $data)) {
                return response()->json([
                    'msg' => 'duplicate',
                    'retry_after' => Config::get('devices.telemetry_freshness_seconds', 600),
                ], 409);
            }
            self::logSolarTrackerData($json, $serial_no);
            return self::SUCCESS_RESPONSE;
		}else{
			return response()->json(['msg' => 'device is not registered'], 422);
		}
    }

    private function invalidPayload()
    {
        // Never log payloads, serial numbers, credentials, or complete request URLs.
        Log::notice('device.telemetry_rejected', ['reason' => 'invalid_payload']);
        return response()->json(['msg' => 'invalid telemetry'], 422);
    }

    private function validTelemetry(array $data): bool
    {
        $fields = ['ps1', 'ps2', 'ps_avg', 'pds', 'motor_speed', 'temp', 'cts', 'state'];
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

    /**
     * Check whether identical telemetry (same serial_no + data payload) arrived
     * within the dedup window. Returns false if Redis is unavailable so the code
     * path remains backward-compatible.
     */
    private function isDuplicate(string $serialNo, string $data): bool
    {
        try {
            return app(TelemetryDuplicateDetector::class)
                ->checkAndMark($serialNo, md5($data), Config::get('devices.telemetry_freshness_seconds', 600));
        } catch (\Throwable $e) {
            // Redis unavailable — log and fall through to prevent outage.
            Log::error('device.dedup_unavailable', [
                'serial_hash' => hash('sha256', $serialNo),
                'exception_class' => $e::class,
            ]);
            return false;
        }
    }

}
