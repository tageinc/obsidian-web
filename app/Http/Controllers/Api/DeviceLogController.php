<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Api\DeviceLog;
use App\Models\Device;
use Illuminate\Support\Facades\Log;


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
        $data = $request->data;
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
            $this->storeDeviceLog($json, $serial_no);
            return self::SUCCESS_RESPONSE;
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

    private function storeDeviceLog(array $data, string $serialNo): void
    {
        // Retain extension fields while preserving the existing numeric normalization.
        foreach (['ps1', 'ps2', 'ps_avg', 'pds', 'motor_speed', 'temp'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = (float) $data[$field];
            }
        }
        if (isset($data['cts'])) {
            $data['cts'] = (int) $data['cts'];
        }
        DeviceLog::create(['serial_no' => $serialNo, 'data' => $data]);
    }
}
