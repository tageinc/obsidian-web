<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;

class LogRequests
{
    public function handle($request, Closure $next)
    {
        // Define the routes you want to log
        $routesToLog = [
            'api.login',
            'api.logout',
            'api.checkout',
            'profile.update-name',
            'profile.update-email',
            'profile.update-phone-number',
            'profile.update-password',
            'device.all-devices-api',
            'device.update-solar-tracker',
            'device.get-solar-tracker-status',
            'device.get-device-data',
            'device.get-latest-status-json',
            'checkout.subscribe',
            'device.api-update-address1',
            'device.api-update-product-alias',
            'device.api-update-address2',
            'device.api-update-zipcode',
            'device.api-update-statecity',
            'device.api-update-status-notification',
            'device.api-update-sms-notification',
            'device.delete',
        ];

        // Get the current route name
        $currentRouteName = Route::currentRouteName();

        // Only log requests for the specified routes
        if (in_array($currentRouteName, $routesToLog)) {
            // Increment the count for this route in the cache
            Cache::increment("api_count:{$currentRouteName}");

            // Log the individual request
            Log::info("Request made to route: $currentRouteName with URL: " . $request->fullUrl());

            // Track and log requests per minute
            $this->trackRequestsPerMinute();

            // Calculate the total API calls for all the specified routes
            $total = 0;
            foreach ($routesToLog as $route) {
                $total += Cache::get("api_count:{$route}", 0);
            }

            // Log the total number of API calls
           // Log::info("Total API calls made to specified routes: $total");
        }

        return $next($request);
    }

    protected function trackRequestsPerMinute()
    {
        // Get the current minute in the format 'Y-m-d H:i'
        $currentMinute = now()->format('Y-m-d H:i');
        $cacheKey = 'requests_per_minute_' . $currentMinute;

        // Increment the request count for the current minute
        $requestCount = Cache::increment($cacheKey);

        // Set an expiration time of 2 minutes for the cache key to avoid memory leaks
        Cache::put($cacheKey, $requestCount, now()->addMinutes(2));

        // Check if this is the first request for the new minute
        if ($requestCount === 1) {
            // Get the previous minute's key and count
            $previousMinute = now()->subMinute()->format('Y-m-d H:i');
            $previousMinuteKey = 'requests_per_minute_' . $previousMinute;
            $previousMinuteCount = Cache::pull($previousMinuteKey);

            // Log the previous minute's total request count if available
            if ($previousMinuteCount) {
                Log::info("Total requests in minute $previousMinute: $previousMinuteCount");
            }
        }
    }
}
