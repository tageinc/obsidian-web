<?php

namespace App\Http\Livewire;

use Livewire\Component;

class CheckoutCard extends Component
{
	public $licenses = [];

	public function mount($licenses)
	{
        foreach ($licenses['licenses'] as $key => $value) {
            if($licenses['licenses'][$key]['quantity'] == 0)
            {
                unset($licenses['licenses'][$key]);
            }
        }

		$this->licenses = $licenses;

    }

    public function render()
    {
     return view('livewire.checkout-card');
 }

 public function add($request)
 {
   dd($request);
}

public function quantity_change($value)
{
    dd($value);
}

public function adda()
{
    //dd($this->licenses['licenses'][0]['quantity']);
    $this->licenses['licenses'][0]['quantity'] = "250";
}
}
