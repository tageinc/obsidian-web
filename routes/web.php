<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DeviceRegisterController;
use App\Http\Controllers\EditDeviceController;
use App\Http\Controllers\DeviceInfoController;
use App\Http\Controllers\AdminControlCenterController;
use App\Http\Controllers\ThankYouController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\UpdateController;
//use App\Http\Controllers\EnergyMonitorController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DeviceManagerController;
use App\Http\Controllers\SubscriptionManagerController;
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

Route::get('/checkoutapi', [CheckoutController::class, 'showCheckoutForm']);
Route::post('/purchase-checkout-api', [CheckoutController::class, 'purchaseApi'])->name('purchase-checkout-api');

Route::get('/checkout-success', function() {
    return view('checkout.success');
})->name('checkout.success');

Route::get('/checkout-error', function() {
    return view('checkout.error');
})->name('checkout.error');

Route::get('/update-success', function() {
    return view('update.success');
})->name('update.success');

Route::get('/update-error', function() {
    return view('update.error');
})->name('update.error');

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/email/verify', 'Auth\VerificationController@show')->name('verification.notice');

Route::post('/email/resend', 'Auth\VerificationController@resend')->name('verification.resend');
Route::post('/email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

Route::get('/email/verify/{id}/{hash}', [App\Http\Controllers\Auth\VerificationController::class, 'verify'])->name('verification.verify');

Route::middleware(['auth', 'verified'])->group(function () {

Route::get('/delta', [Delta::class, 'showDelta'])->name('delta');
    Route::get('/dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::get('/device-register', [DeviceRegisterController::class, 'index'])->name('device-register');
    Route::post('/dataInsert', [DeviceRegisterController::class, 'dataInsert'])->name('dataInsert');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile/update-name', [ProfileController::class, 'updateName'])->name('profile.updateName');
    Route::put('/profile/update-email', [ProfileController::class, 'updateEmail'])->name('profile.updateEmail');
    Route::put('/profile/update-phone-number', [ProfileController::class, 'updatePhoneNumber'])->name('profile.updatePhoneNumber');
    Route::put('/profile/update-password', [ProfileController::class, 'updatePassword'])->name('profile.updatePassword');
    Route::get('/device-manager', [DeviceManagerController::class, 'index'])->name('device-manager');
	Route::get('/subscription-manager', [SubscriptionManagerController::class, 'index'])->name('subscription-manager');
    Route::get('/assign-license/{id}', [SubscriptionManagerController::class, 'assignLicense'])->name('assign-license');
    Route::get('/cancel-subscription/{id}', [SubscriptionManagerController::class, 'cancelSubscription'])->name('cancel-subscription');
    Route::get('/delete-device/{id}', [DeviceManagerController::class, 'delete'])->name('deleteDevice');
    Route::get('/edit-device/{id}', [EditDeviceController::class, 'index'])->name('edit-device');
    Route::put('/edit-device/{id}/address1', [EditDeviceController::class, 'updateAddress1'])->name('update.address1');
    Route::put('/edit-device/{id}/address2', [EditDeviceController::class, 'updateAddress2'])->name('update.address2');
    Route::put('/edit-device/{id}/zipcode', [EditDeviceController::class, 'updateZipCode'])->name('update.zipcode');
    Route::put('/edit-device/{id}/statecity', [EditDeviceController::class, 'updateStateCity'])->name('update.statecity');
    Route::get('/admin-control-center', [AdminControlCenterController::class, 'index'])->name('admin-control-center');
    Route::post('/upload-firmware', [AdminControlCenterController::class, 'uploadFirmware'])->name('uploadFirmware');
    Route::post('/upload-config', [AdminControlCenterController::class, 'uploadConfig'])->name('uploadConfig');
    Route::get('/firmware-file/version/{version?}', [AdminControlCenterController::class, 'serveFirmwareByVersion']);
    Route::get('/config-file/version/{version?}', [AdminControlCenterController::class, 'serveConfigByVersion']);
    Route::get('/firmware-file/prefix/{prefix?}', [AdminControlCenterController::class, 'serveFirmwareByPrefix']);
    Route::get('/config-file/prefix/{prefix?}', [AdminControlCenterController::class, 'serveConfigByPrefix']);
    Route::get('/firmware-version/{prefix?}', [AdminControlCenterController::class, 'getLatestFirmwareVersionNumber']);
    Route::get('/config-version/{prefix?}', [AdminControlCenterController::class, 'getLatestConfigVersionNumber']);
    Route::get('/device-info/{id}', [DeviceInfoController::class, 'index'])->name('device-info');
    Route::post('/device/refresh/{id}', [DeviceInfoController::class, 'refresh'])->name('device.refresh');
    Route::get('/all-devices', [DeviceManagerController::class, 'allDevices'])->name('all-devices');
    Route::get('/paginated-devices', [DeviceManagerController::class, 'paginatedDevices'])->name('paginated-devices');
    Route::get('/thank-you', [ThankYouController::class, 'index'])->name('thank-you');
    Route::get('/update', [UpdateController::class, 'index'])->name('update');
	Route::get('/purchase', [PurchaseController::class, 'index'])->name('purchase');
	Route::post('/checkout', 'CheckoutController@store')->name('checkout.store');
	Route::post('/purchase-checkout', [CheckoutController::class, 'purchase'])->name('purchase-checkout');
	//Route::get('/energy-monitor/{id}', [EnergyMonitorController::class, 'index'])->name('energy-monitor');
    Route::get('/device/{id}/fetch-graph-data', 'DeviceInfoController@fetchGraphData')->name('device.fetchGraphData');
    Route::put('/device/{id}/update-status-notification', 'EditDeviceController@updateStatusNotification')->name('update.status_notification');
    Route::put('/device/{id}/update-sms-notification', 'EditDeviceController@updateSMSNotification')->name('update.sms_notification');
    Route::put('/device/{id}/update-product-alias', [EditDeviceController::class, 'updateProductAlias'])->name('update.alias');
    Route::get('/devices/{id}/latest-solar-data', [DeviceInfoController::class, 'fetchLatestSolarData']);
    Route::get('/device/{id}/refresh', 'DeviceInfoController@refresh')->name('device.refresh');
    Route::post('/update-solar-tracker', [DeviceInfoController::class, 'updateSolarTracker'])->name('update-solar-tracker');
});

Route::get('/contact-us', [ContactUsController::class, 'index'])->name('contact-us');
