<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\DeviceRemoteController;
use App\Http\Controllers\Api\DeviceLogController;
use App\Http\Controllers\Api\DeviceSoftwareController;
use App\Http\Controllers\DeviceManagerController;
use App\Http\Controllers\ViewDeviceController;
use App\Http\Controllers\DeviceHistoryReportController;
use App\Http\Controllers\DeviceTelemetryController;
use App\Http\Controllers\EditDeviceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CreateDeviceController;
use App\Http\Controllers\Api\ExternalApiController;
use App\Http\Controllers\DeveloperWorkspaceController;

Route::prefix('external/v1')->name('external.')->middleware(['auth.external', 'throttle:external-api'])->group(function () {
    Route::get('/', [ExternalApiController::class, 'capabilities'])->name('capabilities');
    Route::get('/devices', [ExternalApiController::class, 'devices'])->name('devices');
    Route::get('/devices/{id}', [ViewDeviceController::class, 'apiShow'])->name('devices.show');
    Route::patch('/devices/{id}', [EditDeviceController::class, 'patch'])->name('devices.update');
    Route::get('/devices/{id}/data', [ViewDeviceController::class, 'getDeviceData'])->name('devices.data');
    Route::get('/devices/{id}/report', [DeviceHistoryReportController::class, '__invoke'])->name('devices.report');
    Route::get('/devices/{id}/telemetry', [DeviceTelemetryController::class, '__invoke'])->name('devices.telemetry');
    Route::get('/remote-control', [ViewDeviceController::class, 'getSolarTrackerStatus'])->name('remote.show');
    Route::post('/remote-control', [ViewDeviceController::class, 'updateSolarTracker'])->name('remote.update');
    Route::get('/firmware', [ExternalApiController::class, 'firmware'])->name('firmware');
    Route::post('/firmware', [DeveloperWorkspaceController::class, 'uploadFirmware'])->name('firmware.store');
    Route::get('/firmware/{version}/download', [DeveloperWorkspaceController::class, 'serveFirmwareByVersion'])->name('firmware.download');
    Route::get('/configuration', [ExternalApiController::class, 'configuration'])->name('configuration');
    Route::post('/configuration', [DeveloperWorkspaceController::class, 'uploadConfig'])->name('configuration.store');
    Route::get('/configuration/{version}/download', [DeveloperWorkspaceController::class, 'serveConfigByVersion'])->name('configuration.download');
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Set a route for the device log controller
Route::post('/log', [DeviceLogController::class, 'logData'])->middleware('throttle:device-telemetry')->name('device.log-data');

Route::get('/firmware-file/version/{version?}', [DeviceSoftwareController::class, 'serveFirmwareByVersion'])->name('device.firmware-by-version');
Route::get('/config-file/version/{version?}', [DeviceSoftwareController::class, 'serveConfigByVersion'])->name('device.config-by-version');
Route::get('/firmware-file/prefix/{prefix?}', [DeviceSoftwareController::class, 'serveFirmwareByPrefix'])->name('device.firmware-by-prefix');
Route::get('/config-file/prefix/{prefix?}', [DeviceSoftwareController::class, 'serveConfigByPrefix'])->name('device.config-by-prefix');
Route::get('/firmware-version/{prefix?}', [DeviceSoftwareController::class, 'getLatestFirmwareVersionNumber'])->name('device.firmware-version');
Route::get('/config-version/{prefix?}', [DeviceSoftwareController::class, 'getLatestConfigVersionNumber'])->name('device.config-version');
Route::get('/remote-control/{serial_no?}', [DeviceRemoteController::class, 'getRemoteControlInfo'])->name('device.remote-control-info');

// Set a route for remote control socket
Route::post('/remote-control-set/{data?}', [DeviceRemoteController::class, 'remoteControl'])->name('device.remote-control-set');

// Public routes (without logging)
Route::post('/login', [LoginController::class, 'apiLogin'])->middleware('throttle:api-login')->name('api.login');
Route::post('/logout', [LoginController::class, 'apiLogout'])->middleware('auth:api')->name('api.logout');
Route::post('/create-device', [CreateDeviceController::class, 'apiCreateDevice']); //register device


// Application-owned bearer tokens, or CSRF-protected same-origin browser sessions.
Route::middleware(['log.requests', 'auth:api'])->group(function () {


    // Profile Routes
    Route::put('/update-name', [ProfileController::class, 'updateNameApi'])->name('profile.update-name');
    Route::post('/profile/update-email', [ProfileController::class, 'updateEmailApi'])->name('profile.update-email');
    Route::post('/profile/update-phone-number', [ProfileController::class, 'updatePhoneNumberApi'])->name('profile.update-phone-number');
    Route::post('/profile/update-password', [ProfileController::class, 'updatePasswordApi'])->name('profile.update-password');

    // Device Routes
    Route::get('/devices/{id}', [ViewDeviceController::class, 'apiShow'])->middleware('browser.device')->name('api.devices.show');
    Route::get('all-devices-api', [DeviceManagerController::class, 'allDevicesAPI'])->name('device.all-devices-api');
    Route::post('/update-solar-tracker', [ViewDeviceController::class, 'updateSolarTracker'])->name('device.update-solar-tracker');
    Route::get('/get-solar-tracker-status', [ViewDeviceController::class, 'getSolarTrackerStatus'])->name('device.get-solar-tracker-status');
    Route::get('/device/{id}/data', [ViewDeviceController::class, 'getDeviceData'])->name('device.get-device-data');
    Route::get('/solar-tracker/{serial_no}', [ViewDeviceController::class, 'getLatestStatusJson'])->name('device.get-latest-status-json');




    // Device routes with a prefix
    Route::prefix('device')->group(function () {
        Route::put('{id}/address1', [EditDeviceController::class, 'apiUpdateAddress1'])->name('device.api-update-address1');
        Route::put('{id}/name', [EditDeviceController::class, 'apiUpdateDeviceName'])->name('device.api-update-device-name');
        Route::put('{id}/address2', [EditDeviceController::class, 'apiUpdateAddress2'])->name('device.api-update-address2');
        Route::put('{id}/zipcode', [EditDeviceController::class, 'apiUpdateZipCode'])->name('device.api-update-zipcode');
        Route::put('{id}/statecity', [EditDeviceController::class, 'apiUpdateStateCity'])->name('device.api-update-statecity');
        Route::put('{id}/statusnotification', [EditDeviceController::class, 'apiUpdateStatusNotification'])->name('device.api-update-status-notification');
        Route::post('{id}/retire', [DeviceManagerController::class, 'retire'])->name('device.retire-api');
        Route::post('{id}/reactivate', [DeviceManagerController::class, 'reactivate'])->name('device.reactivate-api');
    });
});
