@extends('layouts.app')
@section('content')
<div class="container" style="text-align: center; padding: 50px;">
	<h1>Thank you for your order!</h1>
	<p>
	We appreciate your business!
    If you have any questions, please email
	<a href="mailto:info@tezca.net">info@tezca.net</a>
	</p>
	<!-- View My Devices Button -->
	<a href="{{ route('home') }}" class="btn btn-secondary">Go back to home</a>
</div>
@endsection