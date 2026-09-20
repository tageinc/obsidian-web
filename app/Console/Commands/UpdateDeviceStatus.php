<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use App\Mail\LowVoltageMail;
use App\Mail\TheftVandalismMail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;




class UpdateDeviceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device:check-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the status of devices and updates it accordingly';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $communication = new \App\Services\DeviceCommunicationStatus;

        $deviceRegisters = DeviceRegister::get();

        foreach ($deviceRegisters as $deviceRegister) {
            if ((int) $deviceRegister->hardware_id !== 1) {
                continue;
            }

            $serial_no = $deviceRegister->serial_no;
            $geocode = GeoCode::where('serial_no', $serial_no)->first();
            if (!$geocode) {
                $this->error('Device has no geocode entry; status cannot be persisted.');
                continue;
            }
            $newStatus = $communication->classify($deviceRegister, $geocode, $communication->latest($deviceRegister));
            $this->updateDeviceStatus($serial_no, $newStatus);

            if ($geocode->status !== $newStatus) {
                if ($deviceRegister->status_notification) {
                    $this->sendNotificationEmail($serial_no, $newStatus);
                }
            }
        }
        return 0;
    }

    private function updateDeviceStatus($serialNo, $status)
    {
        // Update the status in the Geocode table for the device
        $geocode = GeoCode::where('serial_no', $serialNo)->first();
        if ($geocode) {
            $geocode->status = $status;
            $geocode->save();
        }
    }

    private function sendNotificationEmail($serial_no, $status)
    {
        $recipientEmail = $this->determineRecipientEmail($serial_no); 
        $deviceInfo = DeviceRegister::where('serial_no', $serial_no)->first();

	// Retrieve the user associated with the device 
    $user = User::find($deviceInfo->user_id); 
    if (!$user) { 
        $this->error("Failed to find user for device with serial number: {$serial_no}"); 
        return; 
    } 

    $geocode = GeoCode::where('serial_no', $serial_no)->first(); 
    if (!$geocode) { 
        $this->error("Failed to find geocode entry for serial number: {$serial_no}"); 
        return; 
    }

        $this->info("Attempting to send '{$status}' notification email to '{$recipientEmail}' for device {$serial_no}.");
        $latitude = $geocode->latitude; 
        $longitude = $geocode->longitude; 
        
        switch ($status) {
            case 'low voltage':
                $address1 = $deviceInfo ? $deviceInfo->address_1 : 'No address available';
                Mail::to($recipientEmail)->send(new LowVoltageMail($user->name,$serial_no, $address1, $latitude, $longitude));
                break;
            case 'theft vandalism':
                // Pass the address_1 from deviceInfo to the email, assuming it exists in DeviceRegister
                $address1 = $deviceInfo ? $deviceInfo->address_1 : 'No address available';
                Mail::to($recipientEmail)->send(new TheftVandalismMail($user->name, $serial_no, $address1, $latitude, $longitude));
                break;
            // Add more cases as needed
        }
        $this->info("Notification email for '{$status}' sent to '{$recipientEmail}' for device {$serial_no}.");
    }


    private function determineRecipientEmail($serial_no)
    {
        // Find the device register entry that matches the given serial number.
        $deviceRegister = DeviceRegister::where('serial_no', $serial_no)->first();

        if (!$deviceRegister) {
            // Log::error("Failed to find device register entry for serial number: {$serial_no}");
            return 'fallback@example.com'; // Fallback email if no device is found
        }

        // Using the user_id from the device register to find the corresponding user
        // The 'id' column in the users table is implicitly used by find()
        $user = User::find($deviceRegister->user_id);

        if (!$user) {
            Log::error("Failed to find user for device with serial number: {$serial_no} and user ID: {$deviceRegister->user_id}");
            return 'fallback@example.com'; // Fallback email if no user is found
        }

        Log::info("Sending email to '{$user->email}' for device with serial number: {$serial_no}");


        // Return the user's email
        return $user->email;

    }

}
