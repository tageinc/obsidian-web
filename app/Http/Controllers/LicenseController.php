<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\License;
use Illuminate\Support\Facades\Schema;

class LicenseController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'isManager']);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //$licenses = auth()->user()->licenses;
        return view('licenses.index');
    }

    public function assign(Request $request)
    {
        $license = License::find($request->license_id);
        if($request->action == "assign") 
        {
            $license->user_id = $request->user_id;
            $license->save();
            return back()->with("success", "License assigned.");
            
        } else {
            Schema::disableForeignKeyConstraints();
            $license->user_id = 0;
            $license->save();
            Schema::enableForeignKeyConstraints();
            return back()->with("success", "License unassigned.");
        }
    }

    public function cancel(Request $request)
    {
        $license = License::find($request->license_id);
        $response = License::cancelSubscription($license->subscription_id);
        if($response["id"] != null)
        {
            $license->delete();
            return back()->with("success", "Subscription cancelled.");
        }
        else
        {
            return back()->with("failure", $response["message"]);
        }       
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        dd($request);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


}
