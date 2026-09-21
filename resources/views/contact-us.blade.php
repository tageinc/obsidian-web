@php
    $usesVue = config('frontend.vue3.public_pages');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @include('frontend.mount', ['page' => 'public', 'props' => [
        'mode' => 'contact',
        'contact' => ['phoneHref' => 'tel:+019494902059', 'phoneLabel' => '(949)-490-2059', 'email' => 'info@tezca.net'],
    ]])
@else

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us</title>
    <!-- Add any additional CSS or JS you need here -->
</head>
<body>
    <div class="container" style="text-align: center; padding: 50px;">
        <h1>Contact Us</h1>
		<p>Phone #:
			<a href="tel:+019494902059"> (949)-490-2059</a>
		</p>
		<p>Email: 
			<a href="mailto:info@tezca.net">info@tezca.net</a>
		</p>
    </div>
</body>
</html>
@endif
@endsection
