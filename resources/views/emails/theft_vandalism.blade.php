@component('mail::message')
# Possible Theft or Vandalism

Hello {{ $name }},

The device with serial number {{ $serial_no }} located at {{ $address_1 }} may have been tampered with or moved from its original location.

Latitude: {{ $latitude }}  
Longitude: {{ $longitude }}

@component('mail::button', ['url' => 'https://obsidian.tezca.net/login'])
View Device
@endcomponent

@component('mail::button', ['url' => $google_maps_url])
View Location
@endcomponent

Thank you for your prompt attention to this matter,<br>
{{ config('app.name') }}
@endcomponent
