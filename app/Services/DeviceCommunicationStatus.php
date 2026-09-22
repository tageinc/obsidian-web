<?php

namespace App\Services;

use App\Models\Api\SolarTrackerLog;

class DeviceCommunicationStatus
{
    public function latest($device)
    {
        return SolarTrackerLog::where('serial_no', $device->serial_no)->latest('created_at')->first();
    }

    public function isFresh($log): bool
    {
        return $log && $log->created_at
            && $log->created_at->lte(now())
            && $log->created_at->gt(now()->subSeconds(config('devices.telemetry_freshness_seconds')));
    }

    public function classify($device, $geocode, $log): string
    {
        if (!$this->isFresh($log)) {
            return 'offline';
        }
        return $log->state ?: 'online';
    }
}
