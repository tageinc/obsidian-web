<?php

namespace App\Http\Livewire;

use Livewire\Component;

class ChangeUserRole extends Component
{
    public $user;
    public $role_id;

    public function mount($user)
    {
        $this->user = $user;

        if($user->company->admin_id == $user->id)
            $this->role_id = 1;
        elseif($user->company->manager_id == $user->id)
            $this->role_id = 2;
        else
            $this->role_id = 3;
    }

    public function render()
    {
        return view('livewire.change-user-role');
    }

    // role_id
    // 1: admin
    // 2: manager
    // 3: user
    public function setRole($role_id)
    {
        if(auth()->user()->id != auth()->user()->company->admin()->id)
        {
            abort(401);
        }

        switch ($role_id) {
            case 1:
            auth()->user()->company->setAdmin($this->user);
            break;
            case 2:
            auth()->user()->company->setManager($this->user);
            break;
            case 3:
            auth()->user()->company->setUser($this->user);
            break;
        }

        return redirect(route('users.show', ['user' => $this->user->id]));
    }
}
