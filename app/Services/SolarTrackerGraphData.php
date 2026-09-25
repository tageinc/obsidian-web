<?php

namespace App\Services;

use App\Models\Api\DeviceLog;

/** Returns individual solar tracker readings without aggregation or resampling. */
class SolarTrackerGraphData
{
    public const TIMEZONE = 'America/Los_Angeles';
    public const DISPLAY_FORMAT = 'M j, Y, g:i A T';

    /**
     * @return array{timezone: string, date_time_format: string, points: array<int, array<string, mixed>>}
     */
    public function forSerial(string $serialNo, int $limit = 9000): array
    {
        $logs = DeviceLog::where('serial_no', $serialNo)
            ->whereNotNull('updated_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'updated_at', 'data'])
            ->reverse();

        // Preserve duplicate instants as separate readings, ordered by their IDs.
        $points = $logs
            ->map(function ($log) {
                $recordedAt = $log->updated_at->copy()->timezone(self::TIMEZONE);

                return [
                    'id' => $log->id,
                    'timestamp' => $recordedAt->toIso8601String(),
                    'epoch_ms' => $recordedAt->valueOf(),
                    'label' => $recordedAt->format(self::DISPLAY_FORMAT),
                    'temp' => $this->reading($log->temp),
                    'ps1' => $this->reading($log->ps1),
                    'ps2' => $this->reading($log->ps2),
                    'ps_avg' => $this->reading($log->ps_avg),
                    'pds' => $this->reading($log->pds),
                    'motor_speed' => $this->reading($log->motor_speed),
                ];
            })
            ->values()
            ->all();

        return [
            'timezone' => self::TIMEZONE,
            'date_time_format' => self::DISPLAY_FORMAT,
            'points' => $points,
        ];
    }

    private function reading($value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
