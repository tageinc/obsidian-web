<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        // Add exception types here that you do not want to log
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Exception $e) {
            //
        });

        // Custom render for specific exceptions
        $this->renderable(function (TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error'   => 'Session expired',
                    'message' => 'Your session has expired. Please log in again.',
                ], 419);
            }

            // Send the user to the recovery page; that page performs the refresh
            // with an explicit POST and falls back to login when needed.
            if ($request->user()) {
                return redirect()->route('session.expired')
                    ->with('error', 'Your session has expired. Please log in again.');
            }

            // Unauthenticated: redirect to login page.
            return redirect()->route('login')->with('message', 'Sorry, your session seems to have expired. Please try logging in again.');
        });
    }
}
