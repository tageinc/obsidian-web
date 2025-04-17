<?php

namespace App\Http\Livewire;

use Livewire\Component;

class LicensesTable extends Component
{
    public function render()
    {
    	$licenses = auth()->user()->licenses;
    	//session()->flash('success', 'okas');
        return view('livewire.licenses-table', compact('licenses'));
    }
}
