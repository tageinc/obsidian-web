<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\DeviceRemoteController;
use App\Http\Controllers\Api\DeviceLogController;
use App\Http\Controllers\Api\DeviceSoftwareController;
use App\Http\Controllers\DeviceManagerController;
use App\Http\Controllers\ViewDeviceController;
use App\Http\Controllers\EditDeviceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CreateDeviceController;

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
Route::post('/login', [LoginController::class, 'apiLogin'])->name('api.login');
Route::post('/logout', [LoginController::class, 'logout'])->name('api.logout');
Route::post('/create-device', [CreateDeviceController::class, 'apiCreateDevice']); //register device


// Routes that require Sanctum authentication and logging
Route::middleware(['log.requests', 'auth:sanctum'])->group(function () {


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
        Route::put('{id}/alias', [EditDeviceController::class, 'apiUpdateProductAlias'])->name('device.api-update-product-alias');
        Route::put('{id}/address2', [EditDeviceController::class, 'apiUpdateAddress2'])->name('device.api-update-address2');
        Route::put('{id}/zipcode', [EditDeviceController::class, 'apiUpdateZipCode'])->name('device.api-update-zipcode');
        Route::put('{id}/statecity', [EditDeviceController::class, 'apiUpdateStateCity'])->name('device.api-update-statecity');
        Route::put('{id}/statusnotification', [EditDeviceController::class, 'apiUpdateStatusNotification'])->name('device.api-update-status-notification');
        Route::post('{id}/archive', [DeviceManagerController::class, 'archive'])->name('device.archive-api');
    });
});
