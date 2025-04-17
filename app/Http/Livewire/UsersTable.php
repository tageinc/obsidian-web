<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\User;
use Livewire\WithPagination;

class UsersTable extends Component
{
	public $searchBy = "";

	use WithPagination;

	protected $paginationTheme = 'bootstrap';

	public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
    	$query = '%'.$this->searchBy.'%';
    	if($this->searchBy != "") {
    		$this->resetPage();
    	}
    	//$users = auth()->user()->registeredUsers()->latest()->paginate(10);
    	$users = auth()->user()->company->users()
    		->where(function($users) use ($query){
    		return $users->where('name', 'LIKE', $query)
    		->orWhere('email', 'LIKE', $query);
    	})->latest()->paginate(10);
    	//session()->flash('success', 'okas');
        return view('livewire.users-table', compact('users'));
    }
}
