@php
    $usesVue = config('frontend.vue3.public_pages');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @include('frontend.mount', ['page' => 'public', 'props' => [
        'mode' => 'thank-you',
        'links' => ['createDevice' => route('create-device'), 'deviceManager' => route('device-manager')],
    ]])
@else

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You</title>
    <!-- Add any additional CSS or JS you need here -->
</head>
<body>
    <div class="container" style="text-align: center; padding: 50px;">
        <h1>Thank You for Creating Your Device!</h1>
        <p>Your device has been successfully registered.</p>

        <!-- Create Another Device Button -->
        <a href="{{ route('create-device') }}" class="btn btn-primary" style="margin-right: 10px;">Create Another Device</a>

        <!-- View My Devices Button -->
        <a href="{{ route('device-manager') }}" class="btn btn-secondary">View My Devices</a>
    </div>
</body>
</html>
@endif
@endsection
