<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\VerifiesEmails;
use Illuminate\Auth\Events\Verified;
use App\Models\User;

class VerificationController extends Controller
{
    use VerifiesEmails;

    protected $redirectTo = '/login';

    public function __construct()
    {
        $this->middleware('auth')->only('show');
        $this->middleware('signed')->only('verify');
        $this->middleware('throttle:6,1')->only('verify', 'resend');
    }

    public function resend(Request $request)
    {
        $email = $request->input('email') ?? $request->user()?->email ?? session('login_email');
        if (empty($email)) {
            return back()->with('error', 'No email provided.');
        }

        $request->merge(['email' => $email]);
        $request->validate(['email' => 'required|email|max:255']);

        $user = User::where('email', $email)->first();
        if (!$user) {
            return back()->with('error', 'No user found with that email.');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect($this->redirectPath())->with('error', 'This email is already verified.');
        }

        try {
            $user->sendEmailVerificationNotification();
            return back()->with('status', 'Verification email sent to: ' . $email);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send verification email.');
        }
    }

    public function verify(Request $request)
    {
        // Expiration is required, including for older links that were signed forever.
        // Logged-out recipients can request a fresh link from the existing login form.
        $expires = $request->query('expires');
        abort_unless(is_scalar($expires) && ctype_digit((string) $expires), 403);

        $userId = $request->route('id');
        $user = User::find($userId);

        if (!$user) {
            return redirect($this->redirectPath());
        }

        $hash = $request->route('hash');
        abort_unless(is_string($hash) && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if ($user->hasVerifiedEmail()) {
            return redirect($this->redirectPath());
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect($this->redirectPath())->with('verified', true);
    }

}
