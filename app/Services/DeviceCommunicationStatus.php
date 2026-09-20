<?php

namespace App\Services;

use App\Models\Api\SolarTrackerLog;
use App\Models\Api\EnergyMonitorLog;

class DeviceCommunicationStatus
{
    public function latest($device)
    {
        $model = [1 => SolarTrackerLog::class, 2 => EnergyMonitorLog::class][(int) $device->hardware_id] ?? null;
        return $model ? $model::where('serial_no', $device->serial_no)->latest('created_at')->first() : null;
    }

    public function isFresh($log): bool
    {
        return $log && $log->created_at
            && $log->created_at->lte(now())
            && $log->created_at->gt(now()->subSeconds(config('devices.telemetry_freshness_seconds')));
    }

    public function classify($device, $geocode, $log): string
    {
        if (!in_array((int) $device->hardware_id, [1, 2], true)) {
            return 'unknown';
        }
        if (!$this->isFresh($log)) {
            return 'offline';
        }
        if ((int) $device->hardware_id === 1) {
            return $log->state ?: 'online';
        }
        if ($geocode->latitude != $device->latitude || $geocode->longitude != $device->longitude) {
            return 'theft vandalism';
        }
        if ($log->temp !== null && ($log->temp > 40 || $log->temp < -10)) {
            return 'extreme weather';
        }
        if ($log->v_batt !== null && $log->v_batt < 5) {
            return 'low voltage';
        }
        return 'online';
    }
}
