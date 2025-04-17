<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\License;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProductController extends Controller
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
        $licenses = auth()->user()->company->licenses;
        if($licenses->count() > 0) {

            $collect = collect($licenses)->groupBy('product_id');

            $products = [];
            $lics = [];
            foreach($collect as $key => $value)
            {
                $products[] = [
                    'product' => $value[0]->product,
                    'licenses' => $value,
                    'assigned' => $value->where('user_id', '!=', 0)->count(),
                    'total' => $value->count()
                ];
            }

            $lic = [
                'products' => $products,
                'total' => $licenses->count()
            ];
        } else {
            $lic = [];
        }

        //dd($lic);

        return view('products.index', ['licenses' => $lic]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $products = Product::all();
        return view('products.create', compact('products'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $licenses = [];
        $now = now();
        $user_id = auth()->user()->id;

        for ($i=0; $i < $request->quantity; $i++) {
            $licenses[] = [
                'key' => License::createKey(),
                'product_id' => $request->product_id,
                'company_id' => auth()->user()->company->id,
                'user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('licenses')->insert($licenses);
        
        return redirect(route('products.index'))->with('success', 'Thank you for your purchase.');
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
