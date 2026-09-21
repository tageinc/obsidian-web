<?php

namespace App\Http\Middleware;

use App\Services\RedisWorkloads;
use Closure;

class LogRequests
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Only allowlisted route names and aggregate counts enter the optional store.
        // Never log URLs, request payloads, identities, or authentication material.
        app(RedisWorkloads::class)->countRoute(optional($request->route())->getName());

        return $response;
    }
}
