@extends('layouts.app')

@section('content')
<div class="container">
	<a href="{{route('users.index')}}" class="btn btn-primary mb-4">back</a>
	<div class="row justify-content-center">
		<div class="col-md-12">
			<div class="card mb-4">

				<div class="card-header">{{ __('Dashboard') }}</div>

				<div class="card-body">
					@if (session('status'))
					<div class="alert alert-success" role="alert">
						{{ session('status') }}
					</div>
					@endif

					<div class="h4">{{ $product->name }}</div>
					<hr>
					<div>
						<div><b>Email:</b> {{ $product->owner }}</div>
					</div>
				</div>
			</div>

			<div>
				<div class="mb-2 h5">
					{{ $user->name }}'s products access
				</div>
				<div>Assigned ({{ $user->licenses->count() }})</div>
				@forelse($user->licenses as $license)
				<div class="card">
					<div class="p-3 d-flex justify-content-between align-items-center">
						<div>
							{{$license->productVersion->product->name}} - {{$license->productVersion->name}}
						</div>
						<a href="" class="btn btn-secondary">Unassign</a>
					</div>
				</div>
				@empty
				<div>No products available.</div>
				@endforelse
			</div>
		</div>
	</div>
</div>
@endsection
