<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class PurchaseController extends Controller{
	public function index(){
		$products = Product::all();
		$licenses = Cache::get('licenses');
		if(!isset($licenses)){
			$licenses = array('licenses' => [], 'total' => 0);
		}
		$licenses['types'] = $products;
		$this->licenses = $licenses;
		//dd($licenses);
		return view('purchase', compact('licenses'));
	}
}
