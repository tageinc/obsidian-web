<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SendRegistrationVerificationEmail extends SendEmailVerificationNotification
{
    private Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Registered $event)
    {
        try {
            parent::handle($event);
        } catch (\Throwable $exception) {
            // Registration already persisted an unverified account. Keep its verification
            // notice and resend path usable without exposing mail-provider diagnostics.
            Log::warning('auth.verification_delivery_failed', ['exception' => get_class($exception), 'source' => 'registration']);
            if ($this->request->hasSession()) {
                $this->request->session()->flash('error', 'Your account was created, but we could not send the verification email. Please try sending it again.');
            }
        }
    }
}
