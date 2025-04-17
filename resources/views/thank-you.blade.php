@extends('layouts.app')

@section('content')

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
        <h1>Thank You for Registering Your Device!</h1>
        <p>Your device has been successfully registered.</p>
        
        <!-- Register Another Device Button -->
        <a href="{{ route('device-register') }}" class="btn btn-primary" style="margin-right: 10px;">Register Another Device</a>
        
        <!-- View My Devices Button -->
        <a href="{{ route('device-manager') }}" class="btn btn-secondary">View My Devices</a>
    </div>
</body>
</html>
@endsection