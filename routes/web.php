<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CreateDeviceController;
use App\Http\Controllers\EditDeviceController;
use App\Http\Controllers\ViewDeviceController;
use App\Http\Controllers\AdminControlCenterController;
use App\Http\Controllers\ThankYouController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\DeviceManagerController;
use App\Http\Controllers\Delta;


/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Enabling Laravel's default authentication routes with email verification
Auth::routes(['verify' => true]);


Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/email/verify', 'Auth\VerificationController@show')->name('verification.notice');

Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

Route::get('/email/verify/{id}/{hash}', [App\Http\Controllers\Auth\VerificationController::class, 'verify'])->name('verification.verify');

Route::middleware(['auth', 'verified'])->group(function () {

Route::get('/delta', [Delta::class, 'showDelta'])->name('delta');
    Route::get('/dashboard', [DeviceManagerController::class, 'index'])->name('dashboard');
    Route::get('/create-device', [CreateDeviceController::class, 'index'])->name('create-device');
    Route::post('/create-device', [CreateDeviceController::class, 'createDevice'])->name('create-device.store');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/update-name', [ProfileController::class, 'updateName'])->name('profile.updateName');
    Route::put('/profile/update-email', [ProfileController::class, 'updateEmail'])->name('profile.updateEmail');
    Route::put('/profile/update-phone-number', [ProfileController::class, 'updatePhoneNumber'])->name('profile.updatePhoneNumber');
    Route::put('/profile/update-password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');
    Route::get('/device-manager', [HomeController::class, 'index'])->name('device-manager');
    Route::post('/retire-device/{id}', [DeviceManagerController::class, 'retire'])->name('retireDevice');
    Route::post('/reactivate-device/{id}', [DeviceManagerController::class, 'reactivate'])->name('reactivateDevice');
    Route::get('/edit-device/{id}', [EditDeviceController::class, 'index'])->name('edit-device');
    Route::patch('/devices/{id}', [EditDeviceController::class, 'patch'])->name('devices.update');
    Route::put('/edit-device/{id}', [EditDeviceController::class, 'update'])->name('device.update');
    Route::put('/edit-device/{id}/address1', [EditDeviceController::class, 'updateAddress1'])->name('update.address1');
    Route::put('/edit-device/{id}/address2', [EditDeviceController::class, 'updateAddress2'])->name('update.address2');
    Route::put('/edit-device/{id}/zipcode', [EditDeviceController::class, 'updateZipCode'])->name('update.zipcode');
    Route::put('/edit-device/{id}/statecity', [EditDeviceController::class, 'updateStateCity'])->name('update.statecity');
    Route::get('/developer-workspace', [AdminControlCenterController::class, 'index'])->middleware('browser.developer')->name('developer-workspace');
    Route::get('/admin-control-center', [AdminControlCenterController::class, 'legacyRedirect'])->middleware('browser.developer')->name('admin-control-center');
    Route::post('/upload-firmware', [AdminControlCenterController::class, 'uploadFirmware'])->middleware('browser.developer')->name('uploadFirmware');
    Route::post('/upload-config', [AdminControlCenterController::class, 'uploadConfig'])->middleware('browser.developer')->name('uploadConfig');
    Route::get('/firmware-file/version/{version?}', [AdminControlCenterController::class, 'serveFirmwareByVersion']);
    Route::get('/config-file/version/{version?}', [AdminControlCenterController::class, 'serveConfigByVersion']);
    Route::get('/firmware-file/prefix/{prefix?}', [AdminControlCenterController::class, 'serveFirmwareByPrefix']);
    Route::get('/config-file/prefix/{prefix?}', [AdminControlCenterController::class, 'serveConfigByPrefix']);
    Route::get('/firmware-version/{prefix?}', [AdminControlCenterController::class, 'getLatestFirmwareVersionNumber']);
    Route::get('/config-version/{prefix?}', [AdminControlCenterController::class, 'getLatestConfigVersionNumber']);
    Route::get('/devices/{id}', [ViewDeviceController::class, 'show'])->middleware('browser.device')->name('devices.show');
    Route::post('/device/refresh/{id}', [ViewDeviceController::class, 'refresh'])->middleware('browser.device')->name('device.refresh');
    Route::get('/all-devices', [DeviceManagerController::class, 'allDevices'])->name('all-devices');
    Route::get('/paginated-devices', [DeviceManagerController::class, 'paginatedDevices'])->name('paginated-devices');
    Route::get('/thank-you', [ThankYouController::class, 'index'])->name('thank-you');
    Route::get('/device/{id}/fetch-graph-data', 'ViewDeviceController@fetchGraphData')->middleware('browser.device')->name('device.fetchGraphData');
    Route::put('/device/{id}/update-status-notification', 'EditDeviceController@updateStatusNotification')->name('update.status_notification');
    Route::put('/device/{id}/update-device-name', [EditDeviceController::class, 'updateDeviceName'])->name('update.name');
    Route::get('/device/{id}/refresh', 'ViewDeviceController@refresh')->middleware('browser.device')->name('device.refresh');
    Route::post('/update-solar-tracker', [ViewDeviceController::class, 'updateSolarTracker'])->middleware('browser.device')->name('update-solar-tracker');
});

Route::get('/contact-us', [ContactUsController::class, 'index'])->name('contact-us');
