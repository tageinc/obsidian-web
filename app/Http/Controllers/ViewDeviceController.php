<?php

namespace App\Http\Controllers;

use App\Models\Api\DeviceLog;
use App\Models\Device;
use App\Models\SolarTrackerRemoteControl;
use App\Services\SolarTrackerGraphData;
use App\Services\DeviceTelemetryData;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ViewDeviceController extends Controller
{
    public function show($id)
    {
        $device = Device::findOrFail($id);

        $remoteControl = SolarTrackerRemoteControl::where('serial_no', $device->serial_no)->first();
        $payload = config('frontend.vue3.view_device')
            ? ['telemetry' => app(DeviceTelemetryData::class)->forDevice($device)]
            : $this->statusPayload($device->serial_no);

        return view('view-device', array_merge([
            'device' => $device,
            'remoteControl' => [
                'mode' => $remoteControl ? (int) $remoteControl->mode : 0,
                'motor_speed' => $remoteControl ? (float) $remoteControl->motor_speed : 0,
            ],
        ], $payload));
    }

    public function apiShow($id)
    {
        $device = Device::findOrFail($id);
        $status = \App\Models\GeoCode::where('serial_no', $device->serial_no)
            ->latest('updated_at')->orderByDesc('id')->value('status');

        return response()->json(['data' => array_merge($device->only([
            'id', 'serial_no', 'name', 'sku', 'order_no', 'state',
            'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country',
            'latitude', 'longitude', 'created_at', 'updated_at',
        ]), ['status' => $status ?? 'no geo data'])]);
    }

    public function getLatestStatusJson($serialNo)
    {
        $device = Device::where('serial_no', $serialNo)->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        return response()->json(array_merge(['device' => $serialNo], $this->statusPayload($serialNo)));
    }

    public function getDeviceData($id)
    {
        $device = Device::find($id);

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        return response()->json($this->graphPayload($device->serial_no));
    }

    public function refresh($id)
    {
        $device = Device::find($id);
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }
        $log = DeviceLog::where('serial_no', $device->serial_no)->latest('updated_at')->first();
        if (!$log) {
            return response()->json(['error' => 'No matching data found'], 404);
        }

        $log->updated_at_pst = Carbon::parse($log->updated_at, 'UTC')
            ->timezone('America/Los_Angeles')
            ->format('F j, Y, g:i A T');

        return response()->json($log);
    }

    public function updateSolarTracker(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|boolean',
            'motor_speed' => 'required|numeric|min:-100|max:100',
            'serial_no' => 'required|string|max:255',
        ]);

        $device = Device::where('serial_no', $data['serial_no'])->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $mode = (int) $data['mode'];
        $panel = SolarTrackerRemoteControl::updateOrCreate(
            ['serial_no' => $data['serial_no']],
            ['mode' => $mode, 'motor_speed' => $mode === 0 ? 0 : $data['motor_speed']]
        );

        return response()->json([
            'success' => true,
            'message' => 'Solar panel updated successfully.',
            'mode' => (int) $panel->mode,
            'motor_speed' => (float) $panel->motor_speed,
        ]);
    }

    public function getSolarTrackerStatus(Request $request)
    {
        $data = $request->validate(['serial_no' => 'required|string|max:255']);
        $device = Device::where('serial_no', $data['serial_no'])->first();
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }
        $panel = SolarTrackerRemoteControl::where('serial_no', $data['serial_no'])->first();

        if (!$panel) {
            return response()->json(['success' => false, 'message' => 'Panel not found'], 404);
        }

        return response()->json([
            'success' => true,
            'mode' => $panel->mode,
            'motor_speed' => $panel->motor_speed,
        ]);
    }

    public function fetchGraphData(Request $request, $id)
    {
        return $this->getDeviceData($id);
    }

    private function statusPayload($serialNo)
    {
        $status = DeviceLog::where('serial_no', $serialNo)->latest('updated_at')->first();
        if (!$status) {
            $status = (object) [
                'cts' => 0, 'state' => 0, 'updated_at_pst' => 0, 'ps1' => 0,
                'ps_avg' => 0, 'motor_speed' => 0, 'ps2' => 0, 'pds' => 0, 'temp' => 0,
            ];
        } else {
            $status->updated_at_pst = $status->updated_at->timezone('America/Los_Angeles')->format('F j, Y, g:i A T');
            foreach (['cts', 'state', 'ps1', 'ps_avg', 'motor_speed', 'ps2', 'pds', 'temp'] as $field) {
                $status->{$field} = $status->{$field} ?? 0;
            }
        }

        return array_merge([
            'latestStatus' => $status,
            'stateMessage' => $status->state,
            'ctsValue' => $status->cts,
        ], $this->graphPayload($serialNo));
    }

    private function graphPayload($serialNo)
    {
        return ['graph' => app(SolarTrackerGraphData::class)->forSerial($serialNo)];
    }
}
