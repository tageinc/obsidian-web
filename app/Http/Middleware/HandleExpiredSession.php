<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * HandleExpiredSession – centralized expired-session / CSRF-419 router.
 *
 * Inspired by TAGCSOFT's approach: unauthenticated requests land on a single
 * mobile-friendly page (web) or get a 401 JSON blob (API) so clients can
 * react programmatically instead of getting a redirect loop on SPA/mobile.
 */
class HandleExpiredSession
{
    public function handle(Request $request, Closure $next)
    {
        // Skip if already on the expired page or its refresh endpoint – avoid loops.
        if (
            $request->routeIs('session.expired')
            || $request->routeIs('session.expire.refresh')
        ) {
            return $next($request);
        }

        if (! $request->user()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'Session expired',
                    'message' => 'Your session has expired. Please log in again.',
                ], 401);
            }

            // web page: show mobile-friendly countdown and auto-redirect.
            return redirect()
                ->route('session.expired')
                ->with('error', 'Your session has expired. Please log in again.');
        }

        return $next($request);
    }
}
