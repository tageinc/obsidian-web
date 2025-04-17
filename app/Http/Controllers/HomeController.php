<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DeviceRegister;
use App\Models\User;
use Illuminate\Support\Facades\Auth; // Import Auth
use Illuminate\Support\Facades\View; // Import View
use App\Services\TwilioService;

// this is the dashboard controller

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        // Get the authenticated user
        $user = Auth::user();

        // Retrieve address details from the user model
        $address_1 = $user->address_1;
		$address_2 = $user->address_2;
        $city = $user->city;
        $state = $user->state;
        $country = $user->country;
        $zip_code = $user->zip_code;

        // Count the number of matching rows in the DeviceRegister table (if needed)
        $deviceRegisterCount = DeviceRegister::where('user_id', $user->id)->count();

        // Pass the address details to the view
        return view('dashboard', [
            'address_1' => $address_1,
			'address_2' => $address_2,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'zip_code' => $zip_code,
            'deviceRegisterCount' => $deviceRegisterCount,
        ]);
    }
}
