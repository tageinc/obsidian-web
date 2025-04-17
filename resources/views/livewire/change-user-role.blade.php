<div style="display: flex; align-items: center;">
    <button wire:click="setRole(1)" class="btn btn-sm mr-1 {{ $role_id === 1 ? 'btn-primary' : 'btn-secondary'}}">Admin</button>
    <button wire:click="setRole(2)" class="btn btn-sm mr-1 {{ $role_id === 2 ? 'btn-primary' : 'btn-secondary'}}">Manager</button>
    <button wire:click="setRole(3)" class="btn btn-sm mr-1 {{ $role_id === 3 ? 'btn-primary' : 'btn-secondary'}}">User</button>
</div>