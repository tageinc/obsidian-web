<?php

namespace App\Mail;

class DeviceStatusChangedMail extends DeviceStatusMail
{
    public function __construct(
        public $name,
        public $serialNo,
        public $address1,
        public $latitude,
        public $longitude,
        public ?string $previousStatus,
        public string $status
    ) {
    }

    public function build()
    {
        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";

        return $this->markdown('emails.device_status_changed')
            ->subject('Device Status Changed')
            ->with([
                'name' => $this->name,
                'serial_no' => $this->serialNo,
                'address_1' => $this->address1,
                'previous_status' => $this->previousStatus,
                'status' => $this->status,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'google_maps_url' => $googleMapsUrl,
            ]);
    }
}
