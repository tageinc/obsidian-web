<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bounded duplicate detection for ESP32 telemetry.
 *
 * Device firmware POSTs /api/log every N minutes. Network retries can cause the
 * same record to arrive twice. We deduplicate by serial_no + data payload within
 * a bounded window so the second arrival is observable (409) rather than silently
 * accepted as success.
 *
 * Contract with device firmware:
 *   - 200 → {"msg":"success"}          New telemetry accepted.
 *   - 409 → {"msg":"duplicate", "retry_after":N}  Already accepted; no write.
 *   - 422 → {"msg":"invalid telemetry"}             Schema violation.
 *   - 4xx (any other)  Non-retryable by device.
 */
class TelemetryDuplicateDetector
{
    /**
     * Check whether the given serial_no + data combination is within the
     * dedup window. If it is, mark it as seen and return true so the caller
     * treats the request as a duplicate (409).  If not present, write the
     * marker and return false so the caller proceeds with normal ingestion.
     */
    public function checkAndMark(string $serialNo, string $dataHash, int $windowSeconds): bool
    {
        $key = 'telemetry_dedup:' . md5($serialNo) . ':' . md5($dataHash);

        // Try to add with TTL; returns false if key already exists.
        $result = Cache::add($key, 1, $windowSeconds);

        if ($result === false) {
            // Already seen within the window. Ensure TTL is still set (edge case).
            Log::notice('device.telemetry_duplicate', [
                'serial_hash' => hash('sha256', $serialNo),
                'window_seconds' => $windowSeconds,
            ]);
            return true; // duplicate
        }

        return false; // first arrival
    }
}
