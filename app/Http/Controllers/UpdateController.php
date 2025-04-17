<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\License;
use Illuminate\Support\Facades\Cache;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderMail;
use Illuminate\Support\Facades\Log;

class UpdateController extends Controller{

	public function index(Request $request){
		$user = Auth::user();
		return view('update', ['user' => $user]);	
	}

	public function update(Request $request){
		$user = Auth::user();
		if(isset($user)){
			$billing_details = [
				'city' => $request->billing_city,
				'country' => $request->billing_country,
				'line1' => $request->billing_address,
				'line2' => $request->billing_address_2,
				'postal_code' => $request->billing_zip_code,
				'state' => $request->billing_state
			];
			
			$card_details = [
				'name' => $request->name_on_card,
				'number' => str_replace('-', '', str_replace(' ', '', (string)$request->card_number)),
				'exp_date' => str_replace('/', '', str_replace(' ', '', (string)$request->card_exp)),
				'code' => (string)$request->card_cvc
			];
			foreach($user->licenses as $license){
				$res = License::updateSubscription($billing_details, $card_details, $license->subscription_id);
				if($res['id'] == null)
				{
					return view('update.error');
				}
			}
			return view('update.success');
		}
		return view('update.error');
	}
}
