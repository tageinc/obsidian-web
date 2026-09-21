<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
     * Redirect home and legacy device-manager URLs to the dashboard.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        // Keep home and legacy device-manager links on the shared dashboard.
        $request->session()->reflash();
        return redirect()->route('dashboard', $request->query());
    }
}
