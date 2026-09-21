<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

abstract class AppUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    // Workflows can recheck access and preferences when the queued mail is sent.
    public function shouldSendTo(User $recipient): bool
    {
        return true;
    }
}
