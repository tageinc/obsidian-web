<?php

return [
    // Release flags are server-owned. Each route retains its Laravel middleware.
    'vue3' => collect([
        'profile', 'create_device', 'device_edit', 'dashboard',
        'view_device', 'auth', 'developer', 'public_pages', 'workspace',
    ])->mapWithKeys(function ($page) {
        $fallback = $page === 'developer'
            ? env('FRONTEND_VUE3_ADMIN', env('FRONTEND_VUE3_ENABLED', false))
            : env('FRONTEND_VUE3_ENABLED', false);

        return [$page => (bool) env('FRONTEND_VUE3_'.strtoupper($page), $fallback)];
    })->all(),
    'dev_server' => env('FRONTEND_DEV_SERVER'),
];
