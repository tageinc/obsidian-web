@extends('layouts.app')

@section('content')
<div class="container">
    <a href="{{ route('device-manager') }}" class="btn btn-secondary mb-3">Back</a>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">Device Info</div>
                <div class="card-body">
                    <p><strong>Hardware:</strong> {{ $device->hardware->name }}</p>
                    <p><strong>Alias:</strong> {{ $device->alias }}</p>
                    <p><strong>SKU:</strong> {{ $device->sku }}</p>
                    <p><strong>Serial No.:</strong> {{ $device->serial_no }}</p>
                    <p><strong>Address:</strong><br>{{ $device->address_1 }} {{ $device->address_2 }}<br>{{ $device->city }}, {{ $device->state }} {{ $device->zip_code }}</p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header">Current Status</div>
                    <div class="card-body row">
                        <div class="col-md-6">
                            <p><strong>State:</strong> {{ $stateMessage }}</p>
                            <p><strong>PS1:</strong> {{ $latestStatus->ps1 }}</p>
                            <p><strong>PS Average:</strong> {{ $latestStatus->ps_avg }}</p>
                            <p><strong>Motor Speed:</strong> {{ $latestStatus->motor_speed }}</p>
                            <p><strong>CTS:</strong> {{ $ctsValue }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Updated:</strong> {{ $latestStatus->updated_at_pst ?? 'N/A' }}</p>
                            <p><strong>PS2:</strong> {{ $latestStatus->ps2 }}</p>
                            <p><strong>PDS:</strong> {{ $latestStatus->pds }}</p>
                            <p><strong>Temperature:</strong> {{ $latestStatus->temp }} °C</p>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Remote Control</div>
                    <div class="card-body">
                        <button class="btn btn-primary" data-speed="20">Up</button>
                        <button class="btn btn-secondary" data-speed="0">Stop</button>
                        <button class="btn btn-primary" data-speed="-20">Down</button>
                    </div>
                </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-speed]').forEach(function (button) {
    button.addEventListener('click', function () {
        fetch('{{ route('update-solar-tracker') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({mode: true, motor_speed: Number(button.dataset.speed), serial_no: '{{ $device->serial_no }}'})
        });
    });
});
</script>
@endsection
