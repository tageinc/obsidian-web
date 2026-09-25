<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;

class ApiBrowserSession
{
    public function handle(Request $request, Closure $next)
    {
        // External integrations never authenticate with browser cookies.
        if ($request->is('api/external/*')) {
            $request->headers->set('Accept', 'application/json');
            return $next($request);
        }
        if ($request->header('Authorization')) {
            return $next($request);
        }
        $source = $request->header('Origin') ?: $request->header('Referer');
        $parts = is_string($source) ? parse_url($source) : false;
        $origin = $parts && isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : null;
        if ($origin !== $request->getSchemeAndHttpHost()) {
            return $next($request);
        }

        return app(Pipeline::class)->send($request)->through([
            EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            VerifyCsrfToken::class,
        ])->then($next);
    }
}
