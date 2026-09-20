<?php

namespace App\Http\Controllers;

use App\Models\Api\SolarTrackerLog;
use App\Models\DeviceRegister;
use App\Models\SolarTrackerRemoteControl;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DeviceInfoController extends Controller
{
    public function index($id)
    {
        $device = DeviceRegister::find($id);

        if (!$device) {
            return redirect()->route('device-manager')->with('error', 'Device not found');
        }

        if ((int) $device->hardware_id !== 1) {
            return redirect()->route('device-manager')->with('error', 'This device type is archived and is no longer available.');
        }

        return view('device-info', array_merge([
            'device' => $device,
        ], $this->statusPayload($device->serial_no)));
    }

    public function getLatestStatusJson($serialNo)
    {
        $device = DeviceRegister::where('serial_no', $serialNo)->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        if ((int) $device->hardware_id !== 1) {
            return response()->json(['error' => 'Unsupported hardware type'], 410);
        }

        return response()->json(array_merge(['device' => $serialNo], $this->statusPayload($serialNo)));
    }

    public function getDeviceData($id)
    {
        $device = DeviceRegister::find($id);

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        if ((int) $device->hardware_id !== 1) {
            return response()->json(['error' => 'Unsupported hardware type'], 410);
        }

        return response()->json($this->graphPayload($device->serial_no));
    }

    public function refresh($id)
    {
        $device = DeviceRegister::find($id);
        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }
        if ((int) $device->hardware_id !== 1) {
            return response()->json(['error' => 'Unsupported hardware type'], 410);
        }

        $log = SolarTrackerLog::where('serial_no', $device->serial_no)->latest('updated_at')->first();
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

        $device = DeviceRegister::where('serial_no', $data['serial_no'])->first();
        if (!$device || (int) $device->hardware_id !== 1) {
            return response()->json(['error' => 'Unsupported hardware type'], 410);
        }

        SolarTrackerRemoteControl::updateOrCreate(
            ['serial_no' => $data['serial_no']],
            ['mode' => $data['mode'], 'motor_speed' => $data['motor_speed']]
        );

        return response()->json(['success' => true, 'message' => 'Solar panel updated successfully.']);
    }

    public function getSolarTrackerStatus(Request $request)
    {
        $data = $request->validate(['serial_no' => 'required|string|max:255']);
        $device = DeviceRegister::where('serial_no', $data['serial_no'])->first();
        if (!$device || (int) $device->hardware_id !== 1) {
            return response()->json(['error' => 'Unsupported hardware type'], 410);
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

    public function getTempAverages($serialNo)
    {
        return $this->averages($serialNo, 'temp');
    }

    public function getPSAverages($serialNo)
    {
        return $this->averages($serialNo, 'ps_avg');
    }

    public function getmotorAvgs($serialNo)
    {
        return $this->averages($serialNo, 'motor_speed');
    }

    private function statusPayload($serialNo)
    {
        $status = SolarTrackerLog::where('serial_no', $serialNo)->latest('updated_at')->first();
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
        return [
            'tempAverages' => $this->getTempAverages($serialNo),
            'psAverages' => $this->getPSAverages($serialNo),
            'motorAvgs' => $this->getmotorAvgs($serialNo),
        ];
    }

    private function averages($serialNo, $field)
    {
        $values = SolarTrackerLog::where('serial_no', $serialNo)
            ->latest('updated_at')
            ->limit(9000)
            ->pluck($field);

        $averages = [];
        foreach ($values->chunk(360) as $chunk) {
            $averages[] = round((float) $chunk->avg(), 3);
        }

        return array_reverse($averages);
    }
}
