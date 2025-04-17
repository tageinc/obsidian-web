<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Support\Facades\Log;

class VerifyCsrfToken extends Middleware
{

protected function tokensMatch($request)
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        Log::info('CSRF Token from request: ', ['token' => $token]);
        Log::info('CSRF Token from session: ', ['session_token' => $request->session()->token()]);

        return parent::tokensMatch($request);
    }
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [

    'purchase-checkout-api',
    'update-name',
    'api/login',
    'api/logout',
    'api/all-devices-api',
    ];
}
