<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Config;
use App\Models\Software;

class AdminPanel extends Component
{
	public $config;
	public $software;
	protected $listeners = ['showMessage'];

	public function render()
	{
		$this->config = Config::all();
		$software = Software::all();
		$this->software = [];
		foreach ($software as $soft) {
			$this->software[] = $soft->toArray();
		}

		return view('livewire.admin-panel');
	}

	public function saveConfig($key, $value)
	{
		Config::where('key', $key)->first()->update(['value' => $value]);
		session()->flash('message', $key.": ".$value." saved!.");
	}

	public function saveSoftware($id, $key, $value)
	{
		Software::find($id)->update([$key => $value]);
		session()->flash('message', $key.": ".$value." saved!.");
	}

	public function showMessage($message)
	{
		session()->flash('message', $message);
	}
}
