<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TheftVandalismMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name; // Declare the property to hold the name
    public $serialNo; // Declare the property to hold the serial number
    public $address1; // Declare the property for address_1
    public $latitude; // Declare the property for latitude
    public $longitude; // Declare the property for longitude

    /**
     * Create a new message instance.
     *
     * @param string $name The name of the user
     * @param string $serialNo The serial number of the device
     * @param string $address1 The address associated with the device
     * @param float $latitude The latitude of the device location
     * @param float $longitude The longitude of the device location
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

        return $this->markdown('emails.theft_vandalism')
                    ->subject('Theft or Vandalism Alert')
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
