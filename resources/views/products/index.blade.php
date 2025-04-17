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
				@if(auth()->user()->isAdmin())
				<div style="text-align: right;">
					<a href="{{route('purchase')}}" class="btn btn-primary">Add licenses</a>
					<a href="{{route('update')}}" class="btn btn-primary">Update Payment Method</a>
				</div>
				@endif
			</div>
			<div>
				@if(count($licenses) > 0)
				@foreach($licenses['products'] as $key => $product)
				<div class="card mb-4">
					<div class="card-body">
						<div class="card">
							<div class="p-3 d-flex justify-content-between align-items-center">
								<div class="d-flex  justify-content-between align-items-center">
									<div class="mr-3" style="width: 64px; height: 64px; display: block;">
										<img style="width: 100%;" src="{{$product['product']->software->icon_url}}">
									</div>
									<div>
										{{$product['product']->software->name.' - '.$product['product']->name}}
									</div>
								</div>
								<div>
									<span class="text-muted mr-2">{{ $product['assigned'] ? $product['assigned'] : 0 }} / {{ $product['total'] }} licenses used</span>
								</div>
							</div>
						</div>
						<form id="theForm" method="POST" action="{{ route('licenses.cancel') }}" > 
						@csrf
						<input type="hidden" id="license_id" name="license_id" value="">
						@foreach($product['licenses'] as $license)
						<div style="line-height: 3em;">
							<div class="p-3 d-flex justify-content-between">
								@if($license->user_id != 0)
								<a href="{{route('users.show', ['user' => $license->user_id])}}" style="width: 30vw; color: var(--blue);">{{$license->key}}</a>
								@else
								<div style="width: 30vw;">{{$license->key}}</div>
								@endif
								<div>
									Created: {{ date_format(date_create($license->created_at), "Y-m-d")}}
								</div>
								<div style="text-align: right;">
									<div>
										<span class="text-muted mr-2">
											<a onclick="cancel({{ $license->id }})" class="btn btn-outline-danger">Cancel</a>
										</span>
									</div>
								</div>
							</div>
						</div>
						@endforeach
						</form>
					</div>
				</div>
				@endforeach
				@else
				<div>No licenses to show.</div>
				@endif
			</div>	
		</div>
	</div>
</div>
</div>
</div>
<script>
	function cancel(license_id){
		document.getElementById("license_id").value = license_id;
		document.getElementById("theForm").submit();
	}
</script>
@endsection
