<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Auth\Events\Verified;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/login';

    public function __construct()
    {
        // Middleware commented out for this example. Uncomment as needed.
        // $this->middleware('auth');
        // $this->middleware('signed')->only('verify');
        // $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function resend(Request $request)
    {
        $email = $request->input('email') ?? session('login_email');
        if (empty($email)) {
            Log::error('No email provided for verification resend.');
            return back()->with('error', 'No email provided.');
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            Log::error('No user found with email: ' . $email);
            return back()->with('error', 'No user found with that email.');
        }

        if ($user->hasVerifiedEmail()) {
            Log::info('User already verified: ' . $user->email);
            return redirect($this->redirectPath())->with('error', 'This email is already verified.');
        }

        try {
            $user->sendEmailVerificationNotification();
            Log::info('Verification email sent to: ' . $user->email);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: ' . $e->getMessage());
            return back()->with('error', 'Failed to send verification email.');
        }
        try {
            $user->sendEmailVerificationNotification();
            return back()->with('status', 'Verification email sent to: ' . $email);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send verification email.');
        }
        

        return back()->with('status', 'Verification email resent!');
    }

    public function verify(Request $request)
    {
        Log::info('Reached the verify method in VerificationController.');

        $userId = $request->route('id');
        $user = User::find($userId);

        if (!$user) {
            Log::error("No user found with ID: {$userId}");
            return redirect($this->redirectIfVerificationFails());
        }

        if ($user->hasVerifiedEmail()) {
            Log::info("User (ID: {$userId}) already has a verified email.");
            return redirect($this->redirectPath());
        }

        if ($user->markEmailAsVerified()) {
            Log::info("User (ID: {$userId}) email verified successfully.");
            event(new Verified($user));
        } else {
            Log::error("Failed to verify email for User (ID: {$userId}).");
        }

        return redirect($this->redirectPath())->with('verified', true);
    }

    protected function redirectIfVerificationFails()
    {
        Log::error("Failed to verify email ");
        return '/login';
    }
}
