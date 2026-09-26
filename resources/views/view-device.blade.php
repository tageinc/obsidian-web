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
        'status' => $telemetry['status'],
        'remote' => $remoteControl, 'points' => $telemetry['graph']['points'], 'csrfToken' => csrf_token(),
        'links' => ['telemetry' => route('devices.telemetry', $device->id), 'report' => route('devices.report', $device->id), 'retire' => ((int) Auth::id() === (int) $device->user_id && $device->state === 'active') ? route('retireDevice', $device->id) : null, 'reactivate' => ((int) Auth::id() === (int) $device->user_id && $device->state === 'inactive') ? route('reactivateDevice', $device->id) : null, 'update' => route('devices.update', $device->id), 'dashboard' => route('dashboard'), 'edit' => route('edit-device', $device->id), 'remote' => route('update-solar-tracker')],
    ]])
@else
<style>
    #legacy-device-view .cts-badge { display: inline-block; padding: .3rem .65rem; border-radius: 999px; font-size: .8rem; font-weight: 600; line-height: 1.25; }
    #legacy-device-view .cts-badge--open { color: #fff; background: #198754; }
    #legacy-device-view .cts-badge--closed { color: #495057; background: #e9ecef; }
    #legacy-device-view .motor-speed-scale { position: relative; height: 2.1rem; margin: -.25rem .5rem 0; }
    #legacy-device-view .motor-speed-tick { position: absolute; top: 0; width: 0; height: .35rem; border-left: 1px solid #adb5bd; }
    #legacy-device-view .motor-speed-tick--major { height: .5rem; border-left-color: #6c757d; }
    #legacy-device-view .motor-speed-tick-label { position: absolute; top: .65rem; left: 0; color: #6c757d; font-size: .7rem; line-height: 1; transform: translateX(-50%); white-space: nowrap; }
    #legacy-device-view .motor-speed-tick:first-child .motor-speed-tick-label { transform: none; }
    #legacy-device-view .motor-speed-tick:last-child .motor-speed-tick-label { transform: translateX(-100%); }
</style>
<div class="container" id="legacy-device-view">
    <div class="dropdown float-end">
        <button type="button" class="btn btn-link text-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Device actions">⋮</button>
        <div class="dropdown-menu dropdown-menu-end">
            <a class="dropdown-item" href="{{ route('edit-device', $device->id) }}">Edit</a>
            @if ((int) Auth::id() === (int) $device->user_id)
                @if ($device->state === 'active')
                    <form method="POST" action="{{ route('retireDevice', $device->id) }}">@csrf<button class="dropdown-item text-danger" type="submit">Inactivate</button></form>
                @elseif ($device->state === 'inactive')
                    <form method="POST" action="{{ route('reactivateDevice', $device->id) }}">@csrf<button class="dropdown-item" type="submit">Reactivate</button></form>
                @endif
            @endif
        </div>
    </div>
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
                            <p><strong>CTS:</strong>
                                @if ($legacyReadings['ctsState'] !== null)
                                    <span class="cts-badge cts-badge--{{ strtolower($legacyReadings['ctsState']) }}">{{ $legacyReadings['ctsState'] }}</span>
                                @else
                                    <span data-cts-reading>{{ is_scalar($legacyReadings['cts']) ? $legacyReadings['cts'] : '—' }}</span>
                                @endif
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Updated:</strong> {{ $latestStatus->updated_at_pst ?? 'N/A' }}</p>
                            <p><strong>PS2:</strong> {{ $latestStatus->ps2 }}</p>
                            <p><strong>PDS:</strong> {{ $latestStatus->pds }}</p>
                            <p><strong>Temperature:</strong> {{ $legacyReadings['temperature'] ?? '—' }} °F</p>
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
                            <div class="motor-speed-scale" aria-hidden="true">
                                @foreach (range(-100, 100, 10) as $tick)
                                    <span class="motor-speed-tick{{ $tick % 20 === 0 ? ' motor-speed-tick--major' : '' }}" style="left: {{ ($tick + 100) / 2 }}%">
                                        @if ($tick % 20 === 0)<span class="motor-speed-tick-label">{{ $tick }}</span>@endif
                                    </span>
                                @endforeach
                            </div>
                            <p id="remote-speed-help" class="text-muted small mb-1">Drag and release to save, or use the arrow keys. Set a value from -100 to 100 in steps of 10; 0 stops the motor.</p>
                            <p id="remote-saved-speed" class="small mb-0">Saved motor speed: <strong data-remote-saved-speed>{{ $remoteControl['motor_speed'] }}</strong></p>
                        </div>
                        <p class="small mt-2 mb-0" data-remote-feedback role="status" aria-live="polite"></p>
                    </div>
                </div>
        </div>
    </div>

    <div class="card mb-3" id="solar-tracker-graphs">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span>Solar Tracker History <small class="text-muted">Sensor readings</small></span>
            <div class="btn-group btn-group-sm" aria-label="Graph time range">
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="1">1 hour</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="12">12 hours</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="24">24 hours</button>
                <button type="button" class="btn btn-outline-secondary" data-graph-hours="0">All</button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">Points show individual sensor readings; temperatures are in °F. Dashed lines fit all valid readings in the selected range; a trend needs at least two distinct timestamps.</p>
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
