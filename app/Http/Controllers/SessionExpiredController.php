<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionExpiredController extends Controller
{
    /**
     * Display a mobile-friendly session-expired page.
     */
    public function index(Request $request)
    {
        // Allow up to 30 s of countdown before auto-redirect.
        $countdown = (int) $request->query('countdown', 30);
        $countdown   = max(5, min(60, $countdown));

        // Pass an optional message from flash (token mismatch vs session expiry).
        $message = null;
        if ($request->filled('message')) {
            $message = $request->input('message');
        } elseif ($request->session()->has('error')) {
            $message = $request->session()->pull('error');
        }

        return view('errors.419-expired', compact('countdown', 'message'));
    }

    /**
     * Refresh the current session without requiring re-authentication.
     * Returns a redirect on web and JSON on API requests.
     */
    public function refresh(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'error'   => 'Unauthenticated',
                'message' => 'Your session has expired. Please log in again.',
            ], 401);
        }

        // Laravel's regenerateSession is safe to call for authenticated users.
        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Session refreshed successfully.',
            ], 200);
        }

        return redirect()->back()->with('status', 'Session refreshed.');
    }
}
