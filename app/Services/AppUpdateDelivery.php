<?php

namespace App\Services;

use App\Jobs\SendAppUpdateMail;
use App\Mail\AppUpdateMail;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

class AppUpdateDelivery
{
    public function queue($recipients, AppUpdateMail $mail): int
    {
        // Callers select authorized users; never look up a global audience by email.
        $users = collect($recipients instanceof User ? [$recipients] : $recipients)
            ->filter(fn ($user) => $user instanceof User && $user->exists)
            ->unique('id');

        foreach ($users as $user) {
            $message = clone $mail;
            $message->to = $message->cc = $message->bcc = [];
            Bus::dispatch(new SendAppUpdateMail((int) $user->id, $message));
        }

        return $users->count();
    }
}
