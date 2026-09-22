<?php

namespace App\Console\Commands;

use App\Mail\LowVoltageMail;
use App\Mail\TheftVandalismMail;
use App\Models\Device;
use App\Models\GeoCode;
use App\Services\AppUpdateDelivery;
use App\Services\DeviceCommunicationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateDeviceStatus extends Command
{
    protected $signature = 'device:check-status';

    protected $description = 'Checks device status and queues status-change emails';

    public function handle(DeviceCommunicationStatus $communication, AppUpdateDelivery $delivery)
    {
        foreach (Device::cursor() as $device) {
            // The status and database mail job commit together. If enqueueing
            // fails, the next check can still detect and notify this transition.
            DB::transaction(function () use ($device, $communication, $delivery) {
                $geocode = GeoCode::where('serial_no', $device->serial_no)->lockForUpdate()->first();
                if (!$geocode) {
                    $this->error('Device has no geocode entry; status cannot be persisted.');
                    return;
                }

                $status = $communication->classify($device, $geocode, $communication->latest($device));
                $changed = $geocode->status !== $status;
                $geocode->status = $status;
                $geocode->save();

                $mailClass = [
                    'low voltage' => LowVoltageMail::class,
                    'theft vandalism' => TheftVandalismMail::class,
                ][$status] ?? null;

                if (!$changed || !$device->status_notification || !$mailClass) {
                    return;
                }

                $owner = $device->user;
                if (!$owner || !filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                    $this->warn('Device status updated without email: no valid owner recipient.');
                    return;
                }

                $delivery->queue($owner, new $mailClass(
                    $owner->name,
                    $device->serial_no,
                    $device->address_1,
                    $geocode->latitude,
                    $geocode->longitude
                ));
                $this->info('Device status email queued.');
            });
        }

        return 0;
    }
}
