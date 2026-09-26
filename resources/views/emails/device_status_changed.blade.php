@component('mail::message')
# Device Status Changed

Hello {{ $name }},

The device with serial number {{ $serial_no }} located at {{ $address_1 }} has changed status.

Previous status: {{ $previous_status ?? 'Unknown' }}

Current status: {{ $status }}

Latitude: {{ $latitude }}  
Longitude: {{ $longitude }}

@component('mail::button', ['url' => route('login')])
View Device
@endcomponent

@component('mail::button', ['url' => $google_maps_url])
View Location
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
