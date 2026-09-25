<?php

namespace App\Services;

use App\Models\Api\DeviceLog;
use App\Models\Device;

/** The same read-only snapshot initializes and refreshes the device workspace. */
class DeviceTelemetryData
{
    public function forDevice(Device $device): array
    {
        $graph = app(SolarTrackerGraphData::class)->forSerial($device->serial_no);
        $lastPoint = $graph['points'] ? $graph['points'][count($graph['points']) - 1] : null;
        // Use the graph's last record so equal timestamps choose the same reading.
        $latest = $lastPoint ? DeviceLog::find($lastPoint['id']) : null;

        return [
            'status' => [
                'State' => $latest ? $latest->state : null,
                'PS1' => $lastPoint['ps1'] ?? null,
                'PS Average' => $lastPoint['ps_avg'] ?? null,
                'Motor Speed' => $lastPoint['motor_speed'] ?? null,
                'CTS' => $latest ? $latest->cts : null,
                'Updated' => $latest ? $latest->updated_at->copy()
                    ->timezone(SolarTrackerGraphData::TIMEZONE)->format('F j, Y, g:i A T') : 'N/A',
                'PS2' => $lastPoint['ps2'] ?? null,
                'PDS' => $lastPoint['pds'] ?? null,
                'Temperature (°C)' => $lastPoint['temp'] ?? null,
            ],
            'graph' => $graph,
            'latest_reading' => $lastPoint ? [
                'id' => $lastPoint['id'],
                'timestamp' => $lastPoint['timestamp'],
                'epoch_ms' => $lastPoint['epoch_ms'],
            ] : null,
        ];
    }
}
