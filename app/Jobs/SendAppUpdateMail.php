<?php

namespace App\Jobs;

use App\Mail\AppUpdateMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;

class SendAppUpdateMail implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Rate-limit releases are not delivery failures. Bound retries by time and
    // actual exceptions so a burst does not exhaust the delivery attempts.
    public $tries = 0;
    public $maxExceptions = 5;
    public $timeout = 30;
    public int $expiresAt;

    public function __construct(public int $recipientId, public AppUpdateMail $mail)
    {
        $this->onConnection('app-updates');
        $this->onQueue('mail');
        $this->expiresAt = now()->addDay()->timestamp;
    }

    public function backoff(): array
    {
        return [60, 120, 300, 600];
    }

    public function retryUntil(): int
    {
        return $this->expiresAt;
    }

    public function middleware(): array
    {
        return [new RateLimited('outbound-mail')];
    }

    public function handle(): void
    {
        $recipient = User::find($this->recipientId);
        if (!$recipient || !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)
            || !$this->mail->shouldSendTo($recipient)) {
            return;
        }

        $mail = clone $this->mail;
        $mail->to = $mail->cc = $mail->bcc = [];
        // Mailable::send performs delivery directly, including when a future
        // mail type implements ShouldQueue; there must not be a second job.
        $mail->to($recipient->email)->send(app('mailer'));
    }
}
