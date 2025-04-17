<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Api\EnergyMonitorLog;
use App\Models\DeviceRegister;
use App\Models\GeoCode;
use Carbon\Carbon;
use App\Mail\LowVoltageMail;
use App\Mail\ExtremeWeatherMail;
use App\Mail\TheftVandalismMail;
use App\Models\Api\SolarTrackerLog;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;




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
        $twilio = new Client(config('services.twilio.sid'), config('services.twilio.token'));

        $deviceRegisters = DeviceRegister::with('license')->get(); // Eager load the license

        foreach ($deviceRegisters as $deviceRegister) {
            $serial_no = $deviceRegister->serial_no;
            //if (isset($deviceRegister->license) && $deviceRegister->license->device_id != 0) {
                //$this->line('Checking device with serial number: ' . $serial_no);
                //$this->line('Passed the if condition: License Device ID = ' . $deviceRegister->license->device_id . ' for Serial No: ' . $serial_no);
                $solarLog = SolarTrackerLog::where('serial_no', $serial_no)->latest('created_at')->first();
                // Log::info("SolarLog for device {$serial_no}: " . json_encode($solarLog));

                if ($solarLog && $solarLog->state) {
                    $geocode = GeoCode::where('serial_no', $serial_no)->first();
                    if ($geocode) {
                        $geocode->status = $solarLog->state;
                        $geocode->updated_at = $solarLog->updated_at; // Update the updated_at field
                        $geocode->save();
                        Log::info("Updated GeoCode for device {$serial_no} with status: {$solarLog->state} and updated_at: {$solarLog->updated_at}");
                    }
                    
                }
                
                $geocode = GeoCode::where('serial_no', $serial_no)->first();

                if (!$geocode) {
                    $this->error("No Geocode entry found for serial number: {$serial_no}");
                    continue; // Skip this iteration if no geocode found
                }

                $lastHourLogExists = EnergyMonitorLog::where('serial_no', $serial_no)
                    ->where('created_at', '>', Carbon::now()->subMinutes(10))
                    ->exists();

                $deviceRegister = DeviceRegister::where('serial_no', $serial_no)->first();

                // Convert the boolean value to a more readable format
                $statusNotificationEnabled = $deviceRegister->status_notification ? 'Enabled' : 'Disabled';

                // Display the status notification along with other device info
                $this->line("Status Notification for device {$serial_no}: {$statusNotificationEnabled}");

                $geocode = GeoCode::where('serial_no', $serial_no)->first();


                $isRegistered = $deviceRegister !== null;
                $lastRecord = EnergyMonitorLog::where('serial_no', $serial_no)
                    ->latest('created_at')
                    ->first();

                // Check for battery status, location mismatch for theft detection
                $sixHoursAgo = Carbon::now()->subHours(6);
                $averagePower = EnergyMonitorLog::where('serial_no', $serial_no)
                    ->where('created_at', '>', $sixHoursAgo)
                    ->average('i_batt');

                    if (!$solarLog) {
                        Log::info("No SolarTrackerLog found for device {$serial_no}");
                        if (!$isRegistered) {
                            $this->updateDeviceStatus($serial_no, 'online unregistered');
                        } elseif ($geocode && $deviceRegister && ($geocode->latitude != $deviceRegister->latitude || $geocode->longitude != $deviceRegister->longitude)) {
                            // Log::info("Comparing device {$serial_no} geocode and device register locations.");
                            // Log::info("GeoCode lat: {$geocode->latitude}, lng: {$geocode->longitude}");
                            // Log::info("DeviceRegister lat: {$deviceRegister->latitude}, lng: {$deviceRegister->longitude}");
                            $this->updateDeviceStatus($serial_no, 'theft vandalism');
                        } elseif (!$lastHourLogExists) {
                            $this->updateDeviceStatus($serial_no, 'offline');
                        } elseif ($lastRecord && ($lastRecord->temp > 40 || $lastRecord->temp < -10)) { //need windspeeds 
                            $this->updateDeviceStatus($serial_no, 'extreme weather');
                        } elseif ($lastRecord && $lastRecord->v_batt < 5) {
                            $this->updateDeviceStatus($serial_no, 'low voltage');
                        } elseif ($lastRecord && $lastRecord->v_batt < 5 && $averagePower < 0) {
                            $this->updateDeviceStatus($serial_no, 'battery dead');
                        } else {
                            // Default status if none of the above conditions are met
                            $this->updateDeviceStatus($serial_no, 'online');
                        }
                    }

                // Assume $shouldUpdateStatus is your condition that determines whether to run the update logic
                if ($deviceRegister && $deviceRegister->status_notification) {
                    $newStatus = $this->determineNewStatus($serial_no); 

                    $this->line("INSIDE email IF Device {$serial_no} current status: {$geocode->status}, new status: {$newStatus}");

                    // Check if the status has changed
                    if ($geocode->status !== $newStatus) {
                            $this->info("Status change detected for device {$serial_no}. New status: {$newStatus}");
                            $this->info("Updating device {$serial_no} to new status: {$newStatus}");

                            $this->updateDeviceStatus($serial_no, $newStatus); // Update the status
                            $this->sendNotificationEmail($serial_no, $newStatus); // Send notification
                    }
                } else {
                        
                }

                if ($deviceRegister && $deviceRegister->sms_notification) {
                    $newStatus = $this->determineNewStatus($serial_no); 
                    $this->line("INSIDE sms IF  1 Device {$serial_no} current status: {$geocode->status}, new status: {$newStatus}");

                    // Check if the status has changed
                    if ($geocode->status !== $newStatus) {
                            Log::info("Status change SMS detected for device {$serial_no}. New status: {$newStatus}");
                            $this->info(" SMS Updating device {$serial_no} to new status: {$newStatus}");

                            $this->updateDeviceStatus($serial_no, $newStatus); // Update the status
                            $this->sendNotificationSMS($twilio, $serial_no, $newStatus); // Send notification
                    }
                } else {
                        
                }

                
            //} else {
                // Log the situation where the license is not set or device_id is zero
            //    $this->line("Device with serial number {$serial_no} has no license or the license device_id is zero.");
            //}

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
            case 'extreme weather':
                $address1 = $deviceInfo ? $deviceInfo->address_1 : 'No address available';
                Mail::to($recipientEmail)->send(new ExtremeWeatherMail($user->name,$serial_no, $address1, $latitude, $longitude));
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

    private function determineRecipientPhoneNumber($serial_no)
{
    // Retrieve the device register entry using the serial number
    $deviceRegister = DeviceRegister::where('serial_no', $serial_no)->first();

    // Check if the device register entry was not found
    if (!$deviceRegister) {
        $this->info("Failed to find device register entry for serial number: {$serial_no}");
        return 'fallback_phone_number'; // Fallback phone number if no device is found
    }

    // Retrieve the user associated with the device using the user_id
    $user = User::find($deviceRegister->user_id);

    // Check if the user is not found or the phone number is not available
    if (!$user || empty($user->phone_number)) {
        $this->info("No phone number available for device with serial number: {$serial_no} or user not found");
        return 'fallback_phone_number'; // Fallback if no phone number is found
    }

    // Output the use of the phone number for SMS notifications
    $this->info("Using phone number '{$user->phone_number}' for SMS notification for device with serial number: {$serial_no}");

    // Return the user's phone number
    return $user->phone_number;
}



    private function determineNewStatus($serial_no)
    {
        // Fetch the most recent status for the given serial_no based on the updated_at timestamp
        $geocode = GeoCode::where('serial_no', $serial_no)->orderBy('updated_at', 'desc')->first();

        if ($geocode) {
            // Return the current status as the "new status"
            return $geocode->status;
        } else {
            // Handle the case where there's no geocode record found for the serial_no
            return 'status unknown from determineNewStatus'; // Or any default status you prefer
        }
    }


    private function sendNotificationSMS($twilio, $serial_no, $newStatus)
    {
        // Retrieve the device register entry using the serial number
        $deviceRegister = DeviceRegister::where('serial_no', $serial_no)->first();
        if (!$deviceRegister) {
            $this->info("Device register entry not found for serial number: {$serial_no}");
            return;
        }
    
        // Retrieve the user associated with the device using the user_id
        $user = User::find($deviceRegister->user_id);
    
        if (!$user) {
            $this->info("User not found for device with serial number: {$serial_no}");
            return;
        }

        $geocode = GeoCode::where('serial_no', $serial_no)->first();
    if (!$geocode) {
        $this->info("Failed to find geocode entry for serial number: {$serial_no}");
        return;
    }
    
        // Retrieve the phone number
        $phoneNumber = $this->determineRecipientPhoneNumber($serial_no);
    
        if ($phoneNumber === 'fallback_phone_number') {
            $this->info("No phone number available or found for device with serial number: {$serial_no}");
            return;
        }
    
        // Get the name from the user and the alias from the device register
        $name = $user->name; // Assuming 'name' is the attribute for user's name in the User model
        $alias = $deviceRegister->alias ? $deviceRegister->alias : "your device";

        $googleMapsUrl = "https://www.google.com/maps/search/?api=1&query={$geocode->latitude},{$geocode->longitude}";

    
        // Construct message using user name and device alias
        $message = "Hello {$name},\n\n" .
               "This is Obsidian, the device with serial #{$serial_no} alias '{$alias}' is in the {$newStatus} state.\n\n" .
               "Latitude: {$geocode->latitude}\n" .
               "Longitude: {$geocode->longitude}\n" .
               "View location: {$googleMapsUrl}\n\n" .
               "Please contact info@tezca.net or (949)-529-6737 for further assistance.\n\n" .
               "Log in to view the device at: https://obsidian.tezca.net/login";    
        try {
            $twilio->messages->create($phoneNumber, [
                'from' => config('services.twilio.from'), // Your Twilio number
                'body' => $message
            ]);
            $this->info("SMS notification sent to {$phoneNumber} for device with serial number {$serial_no} with status {$newStatus}.");
        } catch (\Exception $e) {
            $this->info("Failed to send SMS to {$phoneNumber}: " . $e->getMessage());
        }
    }
}
