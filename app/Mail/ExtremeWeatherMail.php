<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ExtremeWeatherMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name; // Variable for the user's name
    public $serialNo; // Variable for the serial number
    public $address1; // Declare the variable for address_1
    public $latitude;
    public $longitude;

    /**
     * Create a new message instance.
     *
     * @param string $name The name of the user
     * @param string $serialNo The serial number of the device
     * @param string $address1 The address associated with the device (newly added)
     * @param float $latitude
     * @param float $longitude
     */
    public function __construct($name,$serialNo, $address1, $latitude, $longitude) // Adjust to accept address_1
    {
	$this->name = $name; // Assign the name to the class property
        $this->serialNo = $serialNo; // Assign the serialNo to the class property
        $this->address1 = $address1; // Assign the address to the class property
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

        return $this->markdown('emails.extreme_weather') // Markdown template
                    ->subject('Extreme Weather Alert') // Email subject
                    ->with([
			'name' => $this->name, // Pass the name
                        'serial_no' => $this->serialNo, // Pass the serial number
                        'address_1' => $this->address1,  // Pass the address
			'latitude' => $this->latitude,
                        'longitude' => $this->longitude,
                        'google_maps_url' => $googleMapsUrl

                    ]); // Pass data to the view
    }
}
