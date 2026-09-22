@php
    $usesVue = config('frontend.vue3.view_device');
@endphp
@extends('layouts.app')

@section('content')
@if ($usesVue)
    @include('frontend.mount', ['page' => 'view-device', 'props' => [
        'editing' => ['initiallyOpen' => request()->boolean('edit'), 'url' => route('edit-device', $device->id), 'id' => $device->id],
        'values' => $device->only(['name', 'serial_no', 'sku', 'order_no', 'address_1', 'address_2', 'city', 'address_state', 'zip_code', 'country', 'latitude', 'longitude']),
        'device' => ['name' => $device->name, 'serial' => $device->serial_no, 'details' => [
            'Name' => $device->name, 'SKU' => $device->sku,
            'Serial No.' => $device->serial_no,
            'Address' => implode(' ', array_filter([$device->address_1, $device->address_2, $device->city, $device->address_state, $device->zip_code])),
        ]],
        'status' => ['State' => $stateMessage, 'PS1' => $latestStatus->ps1, 'PS Average' => $latestStatus->ps_avg,
            'Motor Speed' => $latestStatus->motor_speed, 'CTS' => $ctsValue,
            'Updated' => $latestStatus->updated_at_pst ?: 'N/A', 'PS2' => $latestStatus->ps2,
            'PDS' => $latestStatus->pds, 'Temperature (°C)' => $latestStatus->temp],
        'remote' => $remoteControl, 'points' => $graph['points'], 'csrfToken' => csrf_token(),
        'links' => ['update' => route('devices.update', $device->id), 'dashboard' => route('dashboard'), 'edit' => route('edit-device', $device->id), 'remote' => route('update-solar-tracker')],
    ]])
@else
<div class="container">
    <nav aria-label="Breadcrumb" class="mb-3">
        <ol class="list-unstyled d-flex flex-wrap small text-muted mb-0">
            <li><a href="{{ route('dashboard') }}" class="text-muted text-nowrap">Dashboard</a><span class="mx-2" aria-hidden="true">›</span></li>
            <li aria-current="page">{{ $device->name ?: 'View Device' }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">View Device</div>
                <div class="card-body">
                    <p><strong>Name:</strong> {{ $device->name }}</p>
                    <p><strong>SKU:</strong> {{ $device->sku }}</p>
                    <p><strong>Serial No.:</strong> {{ $device->serial_no }}</p>
                    <p><strong>Address:</strong><br>{{ $device->address_1 }} {{ $device->address_2 }}<br>{{ $device->city }}, {{ $device->address_state }} {{ $device->zip_code }}</p>
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
                        <p id="remote-control-help" class="text-muted small">Enable Remote Control to set the motor speed. Switching modes resets the manual motor command to Stop (0).</p>
                        <div data-remote-controls role="group" aria-label="Motor controls">
                            <div class="d-flex justify-content-between gap-2">
                                <label class="form-label" for="remote-motor-speed">Motor speed</label>
                                <output id="remote-speed-value" for="remote-motor-speed" data-remote-speed-value>Stop (0)</output>
                            </div>
                            <input type="range" class="form-range" id="remote-motor-speed"
                                data-remote-speed min="-100" max="100" step="10" value="0"
                                aria-valuetext="Stop (0)" aria-describedby="remote-speed-help remote-saved-speed"
                                @if($remoteControl['mode'] !== 1) disabled @endif>
                            <div class="d-flex justify-content-between small text-muted mb-2" aria-hidden="true">
                                <span>Down (-100)</span><span>Stop (0)</span><span>Up (100)</span>
                            </div>
                            <p id="remote-speed-help" class="text-muted small mb-1">Drag and release to save, or use the arrow keys. Negative values move Down, positive values move Up, and 0 stops the motor.</p>
                            <p id="remote-saved-speed" class="small mb-0">Saved motor speed: <strong data-remote-saved-speed>{{ $remoteControl['motor_speed'] }}</strong></p>
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
            <p class="text-muted small">Points show raw readings. Dashed lines fit all valid readings in the selected range; a trend needs at least two distinct timestamps.</p>
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
