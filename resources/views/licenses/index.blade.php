@extends('layouts.app')

@section('content')
<div class="container">
	<div class="row justify-content-center">
		<div class="col-md-12">
			<div class="h3 mb-0">{{ auth()->user()->company->name }}'s Team</div>
			<div class="d-flex justify-content-between mb-4 align-items-center">
				<div>
					
					<div class="h3 mb-0">{{ __('Product Management') }}</div>
				</div>
				<a href="{{route('licenses.create')}}" class="btn btn-primary">Add licenses</a>
			</div>
			<livewire:licenses-table/>
		</div>
	</div>
</div>
@endsection
