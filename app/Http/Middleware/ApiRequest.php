<?php

// app/Http/Middleware/ApiRequest.php
namespace App\Http\Middleware;

use Closure;

class ApiRequest
{
    public function handle($request, Closure $next)
    {
        if ($request->expectsJson()) {
            return $next($request);
        }

        return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }
}
