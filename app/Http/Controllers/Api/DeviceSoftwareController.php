<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use App\Models\DeviceRegister;

class DeviceSoftwareController extends Controller
{
	/**
	 * Get the latest version of the config
	 * @param prefix Alphanumeric representation of a product
	 * @return The string represents the latest version of the config table 
	 */
	public function getLatestConfigVersionNumber($prefix = null)
	{
		$latestConfig = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestConfig){
			$latestVersion = array('version' => (string)$latestConfig->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
		return $json;
	}

	/**
	 * Get the latest version of the firmware
	 * @param prefix Alphanumeric representation of a product
	 * @return The string represents the latest version of the config table 
	 */
	public function getLatestFirmwareVersionNumber($prefix = null)
	{
		$latestFirmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
		if($latestFirmware){
			$latestVersion = array('version' => (string)$latestFirmware->version);
			$json = json_encode($latestVersion);
		}else{
			$json = "{\"version\":\"0\"}";
		}
		return $json;
	}

	/**
	 * Serve the firmware file.
	 *
	 * @param  string|null  $version
	 * @return \Illuminate\Http\Response
	 */
	public function serveFirmwareByVersion($version = null)
	{
		Log::info('Firmware request received. version: ' . $version);
		Log::info('Full Request URL: ' . request()->fullUrl());

		if ($version) {
			$firmware = FirmwareVersions::where('version', $version)->first();
			if ($firmware) {
				Log::info('Specific firmware version requested: ' . $firmware->version);
			} else {
				Log::info('Requested firmware version not found: ' . $version);
			}
		} else {
			$firmware = FirmwareVersions::latest()->first();
			Log::info('No version version requested. Serving latest firmware version: ' . $firmware->version);
		}

		if (!$firmware || !Storage::exists($firmware->file_path)) {
			Log::error('Firmware file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
			abort(404, 'Firmware file not found.');
		}

		return Storage::download($firmware->file_path);
	}

	/**
	 * Serve the configuration file.
	 *
	 * @param  string|null  $version
	 * @return \Illuminate\Http\Response
	 */
	public function serveConfigByVersion($version = null)
	{
		Log::info('Configuration file request received. Version: ' . $version);
		Log::info('Full Request URL: ' . request()->fullUrl());

		if ($version) {
			$config = ConfigVersions::where('version', $version)->first();
			if ($config) {
				Log::info('Specific config version requested: ' . $config->version);
			} else {
				Log::info('Requested config version not found: ' . $version);
			}
		} else {
			$config = ConfigVersions::latest()->first();
			Log::info('No specific version requested. Serving latest config version: ' . $config->version);
		}

		if (!$config || !Storage::exists($config->file_path)) {
			Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
			abort(404, 'Configuration file not found.');
		}

		return Storage::download($config->file_path);
	}
	
	/**
	 * Servers the firmware based on the prefix
	 * @param string|null $prefix
	 * @return \Illuminate\Support\Facades\Storage
	 */
	public function serveFirmwareByPrefix($prefix = null)
	{
		Log::info('Firmware file request received. Prefix: ' . $prefix);
		Log::info('Full Request URL: ' . request()->fullUrl());

		if ($prefix) {
			$firmware = FirmwareVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
			if ($firmware) {
				Log::info('Specific firmware prefix requested: ' . $firmware->prefix);
			} else {
				Log::info('Requested firmware prefix not found: ' . $prefix);
			}
		} else {
			Log::info('No specific prefix requested. Failed to serve.');
		}

		if (!$firmware || !Storage::exists($firmware->file_path)) {
			Log::error('Configuration file not found or does not exist. File path: ' . ($firmware ? $firmware->file_path : 'N/A'));
			abort(404, 'Configuration file not found.');
		}

		return Storage::download($firmware->file_path);
	}

    /**
	 * Servers the config based on the prefix
	 * @param string|null $prefix
	 * @return \Illuminate\Support\Facades\Storage
	 */
	public function serveConfigByPrefix($prefix = null)
	{
		Log::info('Configuration file request received. Version: ' . $prefix);
		Log::info('Full Request URL: ' . request()->fullUrl());

		if ($prefix) {
			$config = ConfigVersions::where('prefix', $prefix)->orderBy('version', 'desc')->first();
			if ($config) {
				Log::info('Specific config prefix requested: ' . $config->prefix);
			} else {
				Log::info('Requested config prefix not found: ' . $prefix);
			}
		} else {
			Log::info('No specific prefix requested. Failed to serve config.');
		}

		if (!$config || !Storage::exists($config->file_path)) {
			Log::error('Configuration file not found or does not exist. File path: ' . ($config ? $config->file_path : 'N/A'));
			abort(404, 'Configuration file not found.');
		}

		return Storage::download($config->file_path);
	}
}
