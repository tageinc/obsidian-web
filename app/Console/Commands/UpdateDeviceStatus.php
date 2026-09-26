<?php

namespace App\Console\Commands;

use App\Mail\DeviceStatusChangedMail;
use App\Mail\LowVoltageMail;
use App\Mail\TheftVandalismMail;
use App\Models\Device;
use App\Models\GeoCode;
use App\Notifications\DeviceStatusChangedActivity;
use App\Services\AppUpdateDelivery;
use App\Services\DeviceCommunicationStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateDeviceStatus extends Command
{
    protected $signature = 'device:check-status';

    protected $description = 'Checks device status, records activity, and queues status-change emails';

    public function handle(DeviceCommunicationStatus $communication, AppUpdateDelivery $delivery)
    {
        foreach (Device::where('state', 'active')->cursor() as $device) {
            // The status, activity, and database mail job commit together. If enqueueing
            // fails, the next check can still detect and notify this transition.
            DB::transaction(function () use ($device, $communication, $delivery) {
                $device = Device::whereKey($device->id)->where('state', 'active')->lockForUpdate()->first();
                if (!$device) {
                    return;
                }

                $geocode = GeoCode::where('serial_no', $device->serial_no)->lockForUpdate()->first();
                if (!$geocode) {
                    $this->error('Device has no geocode entry; status cannot be persisted.');
                    return;
                }

                $status = $communication->classify($device, $geocode, $communication->latest($device));
                $previousStatus = $geocode->status;
                $changed = $previousStatus !== $status;
                $geocode->status = $status;
                $geocode->save();

                if (!$changed) {
                    return;
                }

                $owner = $device->user;
                if (!$owner) {
                    $this->warn('Device status updated without activity: no owner recipient.');
                    return;
                }

                $owner->notify(new DeviceStatusChangedActivity($device, $previousStatus, $status));
                if (!$owner->receivesAppActivityEmails()) {
                    return;
                }
                if (!filter_var($owner->email, FILTER_VALIDATE_EMAIL)) {
                    $this->warn('Device status updated without email: no valid owner recipient.');
                    return;
                }

                $mailData = [
                    $owner->name,
                    $device->serial_no,
                    $device->address_1,
                    $geocode->latitude,
                    $geocode->longitude
                ];
                $mail = match ($status) {
                    'low voltage' => new LowVoltageMail(...$mailData),
                    'theft vandalism' => new TheftVandalismMail(...$mailData),
                    default => new DeviceStatusChangedMail(...$mailData, previousStatus: $previousStatus, status: $status),
                };
                $delivery->queue($owner, $mail);
                $this->info('Device status email queued.');
            });
        }

        return 0;
    }
}
