<?php

namespace App\Notifications;

use App\Models\Device;
use Illuminate\Notifications\Notification;

class DeviceStatusChangedActivity extends Notification
{
    private array $activity;

    public function __construct(Device $device, ?string $previousStatus, string $status)
    {
        $name = trim((string) $device->name);
        $label = $name === '' ? $device->serial_no : $name.' ('.$device->serial_no.')';
        $previousLabel = $previousStatus === null || $previousStatus === '' ? 'Unknown' : $previousStatus;

        $this->activity = [
            'title' => 'Device status changed',
            'body' => $label.': '.$previousLabel.' → '.$status,
            'url' => '/devices/'.$device->id,
            'device_id' => (int) $device->id,
            'serial_no' => $device->serial_no,
            'device_name' => $device->name,
            'previous_status' => $previousStatus,
            'status' => $status,
        ];
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->activity;
    }
}
