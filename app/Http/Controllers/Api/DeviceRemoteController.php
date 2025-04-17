<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Models\SolarTrackerRemoteControl;

class DeviceRemoteController extends Controller{
	
	const ERROR_RESPONSE = "{\"msg\":\"serial number null or not found\"}";
	
	/**
	 * Get a JSON of the remote control information that consists of modes, speeds, and other parameters
	 * @param serial_no
	 * @return
	 */
	public function getRemoteControlInfo($serial_no = null){
		if($serial_no){
			//Log::info('Remote control info requested. serial_no: ' . $serial_no);
			$remote_control = SolarTrackerRemoteControl::where('serial_no', $serial_no)->select('mode', 'motor_speed')->first();
			$json = json_encode($remote_control);
			return $json;
		}else{
			//Log::info('Remote control info requested. serial number is null.');
			return ERROR_RESPONSE;
		}
	}
	
	public function remoteControl(Request $request){		
		$validatedData = $request->validate([
            'mode' => 'required|boolean',
            'motor_speed' => 'required|numeric|min:-100|max:100',
            'serial_no' => 'required|string|max:255',
        ]);
    
        // Use updateOrCreate to insert a new row if the serial number does not exist, or update the existing row
        $panel = SolarTrackerRemoteControl::updateOrCreate(
            ['serial_no' => $validatedData['serial_no']], // Search criteria
            [
                'mode' => $validatedData['mode'], // Data to update or create
                'motor_speed' => $validatedData['motor_speed']
            ]
        );
    
        return response()->json(['success' => true, 'message' => 'Solar panel updated successfully.']);
	}
}