@component('mail::message')
# Extreme Weather Alert

Hello {{ $name }},

The device with serial number {{ $serial_no }} located at {{ $address_1 }} is experiencing extreme weather conditions. Please ensure it's secured and protected.

Latitude: {{ $latitude }}  
Longitude: {{ $longitude }}

@component('mail::button', ['url' => 'https://obsidian.tezca.net/login'])
View Device
@endcomponent

@component('mail::button', ['url' => $google_maps_url])
View Location
@endcomponent

Stay safe,<br>
{{ config('app.name') }}
@endcomponent
