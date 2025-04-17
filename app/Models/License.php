<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class License extends Model
{
	use HasFactory;

	protected $guarded = [];

	public static function createKey(){
		if (function_exists('com_create_guid') === true) {
			return trim(com_create_guid(), '{}');
		}

		$data = openssl_random_pseudo_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}

	public static function createForUserWithKey(User $user, Product $product, $order_id, $subscription_id, $key){
		Schema::disableForeignKeyConstraints();
		$license = new License;
		$license->key = $key;
		$license->product_id = $product->id;
		$license->user_id = $user->id;
		$license->order_id = $order_id;
		$license->subscription_id = $subscription_id;
		$license->save();
		Schema::enableForeignKeyConstraints();
		return $license;
	}


	public static function createSubscription(User $user, $amount, $product_name, $description, $interval_length, $billing_details, $card_details, $key, $i){
		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName(env("API_LOGIN_ID"));
		$merchantAuthentication->setTransactionKey(env("TRANSACTION_KEY"));
		$subscription = new AnetAPI\ARBSubscriptionType();
		$subscription->setName($product_name);
		$creditCard = new AnetAPI\CreditCardType();
		$creditCard->setCardNumber($card_details["number"]);
		$creditCard->setExpirationDate($card_details["exp_date"]);
		$creditCard->setCardCode($card_details["code"]);
		$paymentOne = new AnetAPI\PaymentType();
		$paymentOne->setCreditCard($creditCard);
		$invoice = sprintf('%06d', Order::all()->count() + $i);
		$order = new AnetAPI\OrderType();
		$order->setInvoiceNumber($invoice);
		$order->setDescription($description." - key[".$key."]");
		$subscription->setOrder($order);
		$customer = new AnetAPI\CustomerType();
		$customer->setType("individual");
		$customer->setId($user->id);
		$customer->setEmail($user->email);
		$subscription->setCustomer($customer);
		$billTo = new AnetAPI\NameAndAddressType();
		$billTo->setFirstName($card_details["name"]);
		$billTo->setLastName($card_details["name"]);
		$subscription->setBillTo($billTo);
		$interval = new AnetAPI\PaymentScheduleType\IntervalAType();
		$interval->setLength($interval_length);
		$interval->setUnit("days");
		$paymentSchedule = new AnetAPI\PaymentScheduleType();
		$paymentSchedule->setInterval($interval);
		$start_date = new \DateTime(now());
		$paymentSchedule->setStartDate($start_date);
		$paymentSchedule->setTotalOccurrences("9999");
		$paymentSchedule->setTrialOccurrences("0");
		$subscription->setPaymentSchedule($paymentSchedule);
		$subscription->setAmount($amount);
		$subscription->setTrialAmount("0.00");
		$payment = new AnetAPI\PaymentType();
		$payment->setCreditCard($creditCard);
		$subscription->setPayment($payment);
		$request = new AnetAPI\ARBCreateSubscriptionRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$refId = 'ref_' . time();
		$request->setRefId( $refId);
		$request->setSubscription($subscription);
		$controller = new AnetController\ARBCreateSubscriptionController($request);
		if(env("APP_ENV") == "production")
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
		}
		else
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
		}
		$final_response = ["id" => null, "message" => ""];

		if(($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
		{
			$final_response["id"] = $response->getSubscriptionId();
			$final_response["message"] = $response->getMessages();
		}
		else
		{
			$final_response["message"] = $response->getMessages();
		}
		return $final_response;
	}
	
	public static function cancelSubscription($subscription_id){
		$ref_id = 'ref' . time();
		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName(env("API_LOGIN_ID"));
		$merchantAuthentication->setTransactionKey(env("TRANSACTION_KEY"));
		$request = new AnetAPI\ARBCancelSubscriptionRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$request->setRefId($ref_id);
		$request->setSubscriptionId($subscription_id);
		$controller = new AnetController\ARBCancelSubscriptionController($request);
		if(env("APP_ENV") == "production")
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
		}
		else
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
		}
		Log::info('AuthorizeNet Response: '.json_encode($response));
		$final_response = ["id" => null, "message" => ""];
		if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
		{
			$final_response["id"] =  $subscription_id;
			$final_response["message"] = $response->getMessages();
		}
		else
		{
			$final_response["message"] = $response->getMessages();
		}
		return $final_response;
	}

	public static function updateSubscription($billing_details, $card_details, $subscription_id)
	{
		$ref_id = 'ref' . time();
		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName(env("API_LOGIN_ID"));
		$merchantAuthentication->setTransactionKey(env("TRANSACTION_KEY"));
		$subscription = new AnetAPI\ARBSubscriptionType();
		$creditCard = new AnetAPI\CreditCardType();
		$creditCard->setCardNumber($card_details["number"]);
		$creditCard->setExpirationDate($card_details["exp"]);
		$creditCard->setCardCode($card_details["cvc"]);
		$payment = new AnetAPI\PaymentType();
		$payment->setCreditCard($creditCard);
		$subscription->setPayment($payment);
		$request = new AnetAPI\ARBUpdateSubscriptionRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$request->setRefId($ref_id);
		$request->setSubscriptionId($subscription_id);
		$request->setSubscription($subscription);
		$controller = new AnetController\ARBUpdateSubscriptionController($request);
		if(env("APP_ENV") == "production")
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
		}
		else
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
		}
		Log::info('AuthorizeNet Response: '.json_encode($response));
		$final_response = ["id" => null, "message" => ""];
		if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
		{
			$final_response["id"] =  $subscription_id;
			$final_response["message"] = $response->getMessages();
		}
		else
		{
			$final_response["message"] = $response->getMessages();
		}
		return $final_response;
	}

	public static function getSubscriptionStatus($subscription_id){
		$ref_id = 'ref' . time();
		$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
		$merchantAuthentication->setName(env("API_LOGIN_ID"));
		$merchantAuthentication->setTransactionKey(env("TRANSACTION_KEY"));
		$request = new AnetAPI\ARBGetSubscriptionStatusRequest();
		$request->setMerchantAuthentication($merchantAuthentication);
		$request->setRefId($ref_id);
		$request->setSubscriptionId($subscription_id);
		$controller = new AnetController\ARBGetSubscriptionStatusController($request);
		if(env("APP_ENV") == "production")
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
		}
		else
		{
			$response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
		}
		Log::info('AuthorizeNet Response: '.json_encode($response));
		$final_response = ["id" => null, "message" => "", "status" => null];
		if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
		{
			$final_response["id"] =  $subscription_id;
			$final_response["message"] = $response->getMessages();
			$final_response["status"] = $response->getStatus();
		}
		else
		{
			$final_response["message"] = $response->getMessages();
		}
		return $final_response;
		
	}

	public static function boot()
	{
		parent::boot();

		static::creating(function (License $license) {
			$license->key = License::createKey();
		});
	}


	public function order()
	{
		return $this->belongsTo(Order::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class);
	}
	
	public function device()
	{
		return $this->belongsTo(DeviceRegister::class, 'device_id');
	}
	
	public function products()
	{
		return $this->belongsTo(Product::class, 'product_id');
	}

	public function remove()
    {
    	Schema::disableForeignKeyConstraints();
        $this->device_id = 0;
        $this->save();
        Schema::enableForeignKeyConstraints();
    }
	
	public function cancel()
	{
		License::cancelSubscription($this->subscription_id);
        $this->delete();
	}
}
