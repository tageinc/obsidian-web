<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RootMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if(auth()->user()->company->domain() == "hydronium.com" ||
            auth()->user()->company->domain() == "tagccorp.com")
        {
            return $next($request);
        }
        else
        {
            return abort(401);
        }
    }
}
