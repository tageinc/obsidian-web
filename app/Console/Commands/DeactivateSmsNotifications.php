<?php

namespace App\Console\Commands;

use App\Models\DeviceRegister;
use Illuminate\Console\Command;

class DeactivateSmsNotifications extends Command
{
    protected $signature = 'device:deactivate-sms-notifications {--apply : Persist the deactivation}';

    protected $description = 'Reports or disables legacy SMS notification preferences without deleting device records.';

    public function handle()
    {
        $query = DeviceRegister::where('sms_notification', true);
        $count = $query->count();

        if (!$this->option('apply')) {
            $this->line("{$count} device SMS preferences would be disabled. Run with --apply after production approval.");
            return self::SUCCESS;
        }

        $query->update(['sms_notification' => false]);
        $this->info("Disabled {$count} device SMS preferences. No device records were deleted.");

        return self::SUCCESS;
    }
}
