<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticateExternalApi
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');
        $key = ExternalApiKey::resolve($request->bearerToken());
        if (!$key) {
            return response()->json(['message' => 'Invalid or inactive external API key.'], 401);
        }
        Auth::shouldUse('api');
        Auth::guard('api')->setUser($key->user);
        $request->setUserResolver(fn () => $key->user);
        $request->attributes->set('external_api_key_id', $key->id);
        $key->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
