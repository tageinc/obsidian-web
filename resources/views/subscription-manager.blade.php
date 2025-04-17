@extends('layouts.app')

@section('content')

<div class="container">
    @if (session('success'))
    <div style="color: green; background-color: lightgreen; border: 1px solid green; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div style="color: red; background-color: pink; border: 1px solid red; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('error') }}
    </div>
    @endif
	<div class="row justify-content-center">
		<div style="text-align: right;">
			<p>
				<a class="square-button" href="{{ route('purchase') }}">Purchase</a>
				<a class="square-button" href="{{ route('update') }}">Update Billing</a>
			</p>
		</div>
		<div class="col-md-12">	
			<div class="card">
				<div class="card-header">
					{{ __('Subscription Manager') }}   
				</div>
				<div class="card-body">
					<div class="row">
						<div class="col-md-3"><strong>Hardware</strong></div>
						<div class="col-md-2"><strong>Alias</strong></div>
						<div class="col-md-4"><strong>Key</strong></div>	
						<div class="col-md-2"><strong>Actions</strong></div>
					</div>
							<!-- Show Rows -->
							@foreach ($devices as $device)
							<div class="row mt-2">
								<div class="col-md-3">{{ $device->hardware->name }}</div>
								<div class="col-md-2">{{ $device->alias }}</div>
								@if ($device->license)
									<div class="col-md-4">{{ $device->license->key }}</div>	
									<div class="col-md-2">
										<a href="{{ route('cancel-subscription', ['id' => $device->license->id]) }}" class="text-danger cancel-link">Cancel</a>
									</div>
								@else
									<div class="col-md-4">No license</div>	
									<div class="col-md-2">
										<a href="{{ route('assign-license', ['id' => $device->id]) }}" class="text-primary">Assign</a>
									</div>
								@endif
							</div>
							@endforeach
							

						<!-- Show Devices Features -->
						<div class="row mt-4">
							<div class="col-md-12">
								<form action="{{ route('subscription-manager') }}" method="GET">
									<select name="show" onchange="this.form.submit()">
										<option value="{{ env('PAGINATION_SIZE', 10) }}"{{ $pagination_size == env('PAGINATION_SIZE', 10) ? ' selected' : '' }}>Show (Default {{ env('PAGINATION_SIZE', 10) }})</option>
										<option value="10"{{ $pagination_size == 10 ? ' selected' : '' }}>Show 10</option>
										<option value="20"{{ $pagination_size == 20 ? ' selected' : '' }}>Show 20</option>
										<option value="30"{{ $pagination_size == 30 ? ' selected' : '' }}>Show 30</option>
										<!-- Add more options as needed -->
									</select>
								</form>
								{{ $devices->appends(['show' => $pagination_size])->links() }}
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
	document.addEventListener("DOMContentLoaded", function () {
		// Attach click event listener to all delete links
		document.querySelectorAll(".cancel-link").forEach(function (link) {
		  link.addEventListener("click", function (event) {
			// Prevent the default action
			event.preventDefault();
			// Show confirmation dialog
			if (confirm("Are you sure you want to cancel your subscription?")) {
			  // If confirmed, proceed with the deletion
			  window.location.href = this.href;
			}
			// Else, do nothing
		  });
		});
	  });
</script>
@endsection
