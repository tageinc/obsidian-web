<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Register; // Replace with your Device model
use App\Models\Weather; // Replace with your Weather model
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SyncWeatherData extends Command
{
    protected $signature = 'sync:weather-data';
    protected $description = 'Sync weather data from devices table to Weather every 4 hours';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            //Log::info('Weather Data:', ['msg'=>'Beginning the weather table update ...']);
            
            // Fetch the weather data first and set a local variable to the environmental variable called windspeed limit
            $windSpeedLimit = env('WINDSPEED_LIMIT');
            $weatherArray = Weather::select('id', 'longitude', 'latitude')->get();
            
            if ($weatherArray) {

                foreach ($weatherArray as $w){
                    
                    $weatherDataId = $w->id;
                    $longitude = $w->longitude;
                    $latitude = $w->latitude;
        
                    // Now you have longitude and latitude in $longitude and $latitude variables
                    $location = $latitude  . ',' . $longitude;
        
                    // Use $location with your API logic here
                    // Example: Make an API request with $location
        
                    // Get weather data using Tomorrow.io API
                    $apiKey = env('TOMORROWIO_KEY');
                    $client = new Client();
        
                    $response = $client->get("https://api.tomorrow.io/v4/timelines?location=$location&fields=windSpeed&timesteps=1h&units=metric&apikey=$apiKey"); 
                    $weatherData = json_decode($response->getBody(), true);
    
                    // Log the contents of $weatherData
                    //Log::info('Weather Data:', ['weatherArray' => $w]);
        
                    // Extract wind speed
                    $windSpeed = $weatherData['data']['timelines'][0]['intervals'][0]['values']['windSpeed'];
                    // Extract wind speed for the next day (1D)
                    //Log::info('Wind Speed:', ['windSpeed' => $windSpeed]);
        
                    $windSpeed1D = $weatherData['data']['timelines'][0]['intervals'][24]['values']['windSpeed'];
                    // Extract current temperature (now)
                    //Log::info('Wind Speed next day:', ['windSpeed1D' => $windSpeed1D]);
    
                    // Update the Weather with the retrieved data
                    Weather::where('id', $weatherDataId)->update(['wind_speed_now' => $windSpeed,'wind_speed_1D' => $windSpeed1D]);
                    
					// The logic has been turned off because safety should be determined by the solar tracker
                    /*
					if($windSpeed1D > $windSpeedLimit){
                        //Log::info('1-day forecast for wind speed exceeds the limit. Setting esp timeout to 2 days.');
                        // Update the row where $w variable id is equal to the id of the row in the table. In that row, set the espTimeout to 2880.0.
                        Weather::where('id', $weatherDataId)->update(['esp_timeout' => 2880.0]);
                    }else if($windSpeed > $windSpeedLimit){
                        //Log::info('Wind speed exceeds the limit. Setting esp timeout to 1 day.');
                        // Update the row where $w variable id is equal to the id of the row in the table. In that row, set the espTimeout to 1440.0.
                        Weather::where('id', $weatherDataId)->update(['esp_timeout' => 1440.0]);
                    }else{
                        //Log::info('Wind speed is below the limit. Safe for ESP.');
                        // Update the row where $w variable id is equal to the id of the row in the table. In that row, set the espTimeout to 0.
                        Weather::where('id',  $weatherDataId)->update(['esp_timeout' => 0.0]);
                    }
					*/
                    $this->info('Weather data updated successfully.');
                } 
            }else {
                $this->error('No data found in the weather table.');
            }
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
    

}


