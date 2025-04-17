<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThankYouController extends Controller
{
    public function index()
    {
        return view('thank-you');
    }
}
