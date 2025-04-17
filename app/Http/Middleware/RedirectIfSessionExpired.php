<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfSessionExpired
{
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            // If user is not authenticated, redirect to login page
            return redirect('/login')->with('error', 'Your session has expired. Please log in again.');
        }
        return $next($request);
    }
}
