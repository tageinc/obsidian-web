<?php

return [
    'enabled' => env('REDIS_WORKLOADS_ENABLED', false),
    'versions_enabled' => env('REDIS_WORKLOADS_VERSIONS_ENABLED', true),
    'metrics_enabled' => env('REDIS_WORKLOADS_METRICS_ENABLED', true),
    'store' => 'redis-workloads',
    'prefix' => 'obsidian:'.env('APP_ENV', 'production').':v1:',
    'version_ttl' => 60,
    'minute_ttl' => 120,
    'day_ttl' => 172800,
    'metric_routes' => [
        'api.login', 'api.logout',
        'profile.update-name', 'profile.update-email',
        'profile.update-phone-number', 'profile.update-password',
        'device.all-devices-api', 'device.update-solar-tracker',
        'device.get-solar-tracker-status', 'device.get-device-data',
        'device.get-latest-status-json', 'device.api-update-address1',
        'device.api-update-product-alias', 'device.api-update-address2',
        'device.api-update-zipcode', 'device.api-update-statecity',
        'device.api-update-status-notification', 'device.delete',
    ],
];
