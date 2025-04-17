@extends('layouts.app')

@section('content')
<div class="container">
	<a href="{{route('products.index')}}" class="btn btn-primary mb-4">back</a>
	<div class="row justify-content-center">
		<div class="col-md-12">
			<div class="card mb-4">

				<div class="card-header">{{ __('Products') }}</div>

				<div class="card-body">
					@forelse($products as $product)
					<div class="card">
						<div class="p-3 d-flex justify-content-between align-items-center">
							<div>
								{{ $product->name }}
							</div>
							<form method="POST" action="{{ route('products.store') }}">
								@csrf
								<div class="d-flex" style="align-items: center; width: auto; justify-content: flex-end;">
									<span class="mr-2">Quantity:</span>
									<input type="hidden" name="product_id" value="{{$product->id}}">
									<input class="form-control mr-2" type="number" name="quantity" min="1" value="1" style="max-width: 30%;" required/>
									<input type="submit" class="btn btn-primary" value="Add" />
								</div>
							</form>
						</div>
					</div>
					@empty
					<div>No products available.</div>
					@endforelse
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
