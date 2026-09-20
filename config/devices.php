<?php

return [
    // Matches the existing energy-monitor communication window.
    'telemetry_freshness_seconds' => max(1, (int) env('DEVICE_TELEMETRY_FRESHNESS_SECONDS', 600)),
    'telemetry_per_minute' => max(1, (int) env('DEVICE_TELEMETRY_PER_MINUTE', 1000)),
];
