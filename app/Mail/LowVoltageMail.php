<?php

namespace App\Mail;

class LowVoltageMail extends DeviceStatusMail
{
    public $name;
    public $serialNo;
    public $address1;
    public $latitude;
    public $longitude;

    /**
     * Create a new message instance.
     *
     * @param string $name
     * @param string $serialNo
     * @param string $address1
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct($name, $serialNo, $address1, $latitude, $longitude)
    {
        $this->name = $name;
        $this->serialNo = $serialNo;
        $this->address1 = $address1;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$this->latitude},{$this->longitude}";

        return $this->markdown('emails.low_voltage')
                    ->subject('Low Voltage Alert')
                    ->with([
                        'name' => $this->name,
                        'serial_no' => $this->serialNo,
                        'address_1' => $this->address1,
                        'latitude' => $this->latitude,
                        'longitude' => $this->longitude,
                        'google_maps_url' => $googleMapsUrl
                    ]);
    }
}
