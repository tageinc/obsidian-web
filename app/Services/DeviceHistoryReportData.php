<?php

namespace App\Services;

use App\Models\Api\DeviceLog;
use App\Models\Device;
use App\Models\SolarTrackerRemoteControl;
use Carbon\CarbonImmutable;

class DeviceHistoryReportData
{
    public const READING_LIMIT = 9000;

    public function forDevice(
        Device $device,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?CarbonImmutable $generatedAt = null
    ): array {
        $graph = app(SolarTrackerGraphData::class)->forSerial($device->serial_no, self::READING_LIMIT);
        $start = $from->valueOf();
        $end = $to->valueOf();
        $points = array_values(array_filter($graph['points'], fn (array $point) =>
            $point['epoch_ms'] >= $start && $point['epoch_ms'] <= $end
        ));

        // The overview snapshot is independent of the historical period being reported.
        $log = DeviceLog::where('serial_no', $device->serial_no)
            ->whereNotNull('updated_at')
            ->orderByDesc('updated_at')->orderByDesc('id')->first();
        $latest = null;
        if ($log) {
            $latest = ['recorded_at' => CarbonImmutable::instance($log->updated_at)];
            foreach (DeviceLog::TELEMETRY_FIELDS as $field) {
                $latest[$field] = $log->{$field};
            }
        }

        return [
            'device' => $device->only([
                'id', 'name', 'serial_no', 'sku', 'order_no', 'state',
                'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country',
                'latitude', 'longitude',
            ]),
            'timezone' => SolarTrackerGraphData::TIMEZONE,
            'generated_at' => $generatedAt ?? CarbonImmutable::now('UTC'),
            'range' => ['from' => $from, 'to' => $to, 'start_ms' => $start, 'end_ms' => $end],
            'points' => $points,
            'available_count' => count($graph['points']),
            'reading_limit' => self::READING_LIMIT,
            'latest' => $latest,
            'control_mode' => (int) (SolarTrackerRemoteControl::where('serial_no', $device->serial_no)->value('mode') ?? 0),
        ];
    }
}
