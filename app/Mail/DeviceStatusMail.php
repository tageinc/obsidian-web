<?php

namespace App\Mail;

use App\Models\DeviceRegister;
use App\Models\User;

abstract class DeviceStatusMail extends AppUpdateMail
{
    public function shouldSendTo(User $recipient): bool
    {
        return DeviceRegister::where('serial_no', $this->serialNo)
            ->where('user_id', $recipient->id)
            ->where('status_notification', true)
            ->exists();
    }
}
