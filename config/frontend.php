<?php

return [
    // Release flags are server-owned. Each route retains its Laravel middleware.
    'vue3' => collect([
        'profile', 'device_register', 'device_edit', 'dashboard',
        'device_info', 'auth', 'admin', 'public_pages', 'workspace',
    ])->mapWithKeys(function ($page) {
        return [$page => (bool) env('FRONTEND_VUE3_'.strtoupper($page), env('FRONTEND_VUE3_ENABLED', false))];
    })->all(),
    'dev_server' => env('FRONTEND_DEV_SERVER'),
];
