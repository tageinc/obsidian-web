<div>
	<form class="form-inline mb-2">
		<div class="input-group input-group-sm">
			<input wire:model="searchBy" type="text" class="form-control" aria-label="Small" placeholder="Search" aria-describedby="inputGroup-sizing-sm">
			<div class="input-group-append">
				<span class="input-group-text" id="inputGroup-sizing-sm"><i class="fas fa-search"></i></span>
			</div>
		</div>
		<div class="ml-4">Users: {{ $users->total() }}</div>
	</form>
	<div class="card">					
		<table class="table table-hover">
			<thead class="bg-light">
				<tr>
					<th scope="col">Name</th>
					<th scope="col">Email</th>
					<th scope="col">Last Login Date</th>
					<th scope="col">Actions</th>
				</tr>
			</thead>
			<tbody>
				@forelse($users as $a_user)
				<tr>
					<td><a href="{{route('users.show', $a_user->id)}}">{{$a_user->name ?? $a_user->email}}</a></td>
					<td>{{explode('@',$a_user->email)[0].'@'.$a_user->company->domain()}}</td>
					<td>{{$a_user->last_login_at}}</td>
					
					<td>
						@if(auth()->user()->isAdmin())
						<a href="{{route('users.edit', $a_user->id)}}">Edit</a> | 
						<a href="{{ route('users.index') }}" 
							onclick="event.preventDefault(); 
							document.getElementById( 
							'delete-form-{{$a_user->id}}').submit();" class="text-danger"> 
							Delete  
						</a> 
						<form id="delete-form-{{$a_user->id}}"  
							action="{{route('users.destroy', $a_user->id)}}" 
							method="post"> 
							@csrf @method('DELETE')
						</form>
						@else
						<div></div>
						@endif 
					</td>
					
				</tr>
				@empty
				<tr>
					<td colspan="3">No users available.</td>
				</tr>
				@endforelse
			</tbody>
		</table>

		<div class="col-12 d-flex justify-content-center">
			{{$users->links()}}
		</div>
	</div>
</div>
