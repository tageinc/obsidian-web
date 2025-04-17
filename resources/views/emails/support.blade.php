@component('mail::message')
<p><span style="font-weight: bold">From: </span>{{ $name }} ({{ $email }})</p>
<p><span style="font-weight: bold">Message:</span><br>
{{ $message }}
</p>
@endcomponent
