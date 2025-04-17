@extends('layouts.app')
@section('content')
<div class="container" style="text-align: center; padding: 50px;">
	<h1>We have an error with your order!</h1>
	<p>
	Please contact us!
	<a href="mailto:info@tezca.net">info@tezca.net</a>
	</p>
	<!-- View My Devices Button -->
	<a href="{{ route('home') }}" class="btn btn-secondary">Go back to home</a>
</div>
@endsection