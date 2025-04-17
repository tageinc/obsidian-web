@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header custom-card-header">{{ __('Purchase') }}</div>
                <div class="card-body">
                    <div>   
					<form method="POST" action="{{ route('checkout.store') }}">
						 
							@foreach($licenses['types'] as $license)  
							@csrf
							<div style="display: flex; justify-content: center; align-content: space-between;"> 
								<div style="width: 70%">
									<ul>
										<li>{{ $license->name }}</li>
										<ul>
											
											<li><b>${{ $license->price_per_quantity }}</b>/device/mo</li>
											<li>Monitoring & Control</li>
											<li>Real-time Geolocation</li>
										</ul>
									</ul>
								</div>
								<div style="width: 30%">
									<labels>Device:</label>
									<input class="card_input" name="{{$license->slug}}" style="width: 60px;" type="number" maxlength="3" name="" value="0" min="0" max="9999">
								</div>
							</div>
							 @endforeach
						<center>
							<button class="btn btn-primary">Checkout</button>
						</center>
					</form>
				   
				</div>
								
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
