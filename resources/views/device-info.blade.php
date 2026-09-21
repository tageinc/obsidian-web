@php
    $usesVue = config('frontend.vue3.device_info');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @include('frontend.mount', ['page' => 'device-info', 'props' => [
        'device' => ['alias' => $device->alias, 'serial' => $device->serial_no, 'details' => [
            'Hardware' => $device->hardware->name, 'Alias' => $device->alias, 'SKU' => $device->sku,
            'Serial No.' => $device->serial_no,
            'Address' => implode(' ', array_filter([$device->address_1, $device->address_2, $device->city, $device->state, $device->zip_code])),
        ]],
        'status' => ['State' => $stateMessage, 'PS1' => $latestStatus->ps1, 'PS Average' => $latestStatus->ps_avg,
            'Motor Speed' => $latestStatus->motor_speed, 'CTS' => $ctsValue,
            'Updated' => $latestStatus->updated_at_pst ?: 'N/A', 'PS2' => $latestStatus->ps2,
            'PDS' => $latestStatus->pds, 'Temperature (°C)' => $latestStatus->temp],
        'remote' => $remoteControl, 'points' => $graph['points'], 'csrfToken' => csrf_token(),
        'links' => ['dashboard' => route('dashboard'), 'remote' => route('update-solar-tracker')],
    ]])
@else
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

                <div class="card mb-3" id="solar-tracker-remote">
                    <div class="card-header">Remote Control</div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="remote-control-mode" data-remote-toggle
                                aria-describedby="remote-control-help"
                                @if($remoteControl['mode'] === 1) checked @endif>
                            <label class="form-check-label" for="remote-control-mode">Remote control mode</label>
                        </div>
                        <p class="mb-2">Mode: <strong data-remote-mode>{{ $remoteControl['mode'] === 1 ? 'Remote Control' : 'Automatic' }}</strong></p>
                        <p id="remote-control-help" class="text-muted small">Enable Remote Control to use Up, Stop, and Down. Switching modes resets the manual motor command to Stop.</p>
                        <div data-remote-controls role="group" aria-label="Motor controls">
                            <button type="button" class="btn btn-primary" data-speed="20" @if($remoteControl['mode'] !== 1) disabled @endif>Up</button>
                            <button type="button" class="btn btn-secondary" data-speed="0" @if($remoteControl['mode'] !== 1) disabled @endif>Stop</button>
                            <button type="button" class="btn btn-primary" data-speed="-20" @if($remoteControl['mode'] !== 1) disabled @endif>Down</button>
                        </div>
                        <p class="small mt-2 mb-0" data-remote-feedback role="status" aria-live="polite"></p>
                    </div>
                </div>
        </div>
    </div>

    <div class="card mb-3" id="solar-tracker-graphs">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Solar Tracker History <small class="text-muted">Raw readings</small></span>
            <div class="btn-group btn-group-sm" aria-label="Graph time range">
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="1">1 hour</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="12">12 hours</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="24">24 hours</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="0">All</button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small" data-graph-range-label></p>
            <p class="text-muted" data-graph-empty hidden>No telemetry was recorded for this time range.</p>
            <div class="row g-3">
                <div class="col-12"><div style="height: 260px"><canvas data-chart="temperature"></canvas></div></div>
                <div class="col-md-6"><div style="height: 220px"><canvas data-chart="panel"></canvas></div></div>
                <div class="col-md-6"><div style="height: 220px"><canvas data-chart="motor"></canvas></div></div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/solar-tracker-graph.js') }}"></script>
<script src="{{ asset('js/solar-tracker-remote.js') }}"></script>
<script>
SolarTrackerRemote.render(document.getElementById('solar-tracker-remote'), {
    ...@json($remoteControl),
    serial_no: @json($device->serial_no),
    endpoint: @json(route('update-solar-tracker')),
    csrfToken: @json(csrf_token())
});
SolarTrackerGraph.render(document.getElementById('solar-tracker-graphs'), @json($graph['points']));
</script>
@endif
@endsection
