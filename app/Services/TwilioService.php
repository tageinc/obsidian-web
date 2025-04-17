<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;
    protected $from;

    public function __construct()
    {
        // Initialize the Twilio client with credentials from your Laravel config
        $this->client = new Client(config('services.twilio.sid'), config('services.twilio.token'));
        $this->from = config('services.twilio.from');
    }

    public function sendMessage($to, $message)
    {
        // Use the Twilio client to send an SMS
        return $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $message
        ]);
    }
}

