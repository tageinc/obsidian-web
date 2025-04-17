@component('mail::message')
# Low Voltage Alert

Hello {{ $name }},

The device with serial number {{ $serial_no }} located at {{ $address_1 }} is reporting low voltage levels. Please check the device's status.

Latitude: {{ $latitude }}  
Longitude: {{ $longitude }}

@component('mail::button', ['url' => 'https://obsidian.tezca.net/login'])
View Device
@endcomponent

@component('mail::button', ['url' => $google_maps_url])
View Location
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
