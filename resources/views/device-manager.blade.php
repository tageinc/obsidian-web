@php
    $usesVue = config('frontend.vue3.dashboard');
@endphp
@extends('layouts.app')

@section('content')
@php
        $creation = null;
        if ($creationEnabled) {
            $modalSubmission = old('_creation_modal') === '1';
            $creationErrors = $modalSubmission ? \App\Support\DeviceCreationData::errors($errors->messages()) : [];
            $creationError = $modalSubmission ? session('error') : null;
            $creation = [
                'csrfToken' => csrf_token(),
                'action' => route('create-device.store'),
                'values' => \App\Support\DeviceCreationData::values(Auth::user(), $modalSubmission),
                'errors' => $creationErrors,
                'sessionError' => $creationError,
                'initiallyOpen' => request()->boolean('create') || ($modalSubmission && (!empty($creationErrors) || !empty($creationError))),
            ];
        }
@endphp
@if ($usesVue)
    @php
        $dashboardText = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;
        $dashboardDevices = $devices->getCollection()->map(function ($device) use ($dashboardText) {
            $serial = $dashboardText($device->serial_no);
            $address = implode(', ', array_filter([
                $dashboardText($device->address_1), $dashboardText($device->address_2),
            ], fn ($line) => $line !== null));
            return [
                'id' => $device->id,
                'name' => $dashboardText($device->name) ?? $serial ?? 'Device '.$device->id,
                'serial' => $serial,
                'sku' => $dashboardText($device->sku),
                'address' => $address !== '' ? $address : null,
                'state' => $device->state,
                'status' => $device->status,
                'lastUpdated' => $device->last_updated,
                'links' => [
                    'view' => route('devices.show', $device->id),
                    'edit' => route('edit-device', $device->id),
                    'retire' => $device->state === 'active' ? route('retireDevice', $device->id) : null,
                    'reactivate' => $device->state === 'inactive' ? route('reactivateDevice', $device->id) : null,
                ],
            ];
        })->values()->all();
        $dashboardPagination = [
            'currentPage' => $devices->currentPage(),
            'perPage' => (int) $pagination_size,
            'lastPage' => $devices->lastPage(),
            'total' => $devices->total(),
            'from' => $devices->firstItem(),
            'to' => $devices->lastItem(),
            'sizes' => array_values(array_unique([(int) env('PAGINATION_SIZE', 10), 10, 20, 30, (int) $pagination_size])),
            'links' => $devices->appends(['show' => $pagination_size, 'search' => $search, 'status' => $statusFilter, 'state' => $stateFilter])->linkCollection()->map(function ($link) {
                return ['url' => $link['url'], 'label' => html_entity_decode(strip_tags($link['label']), ENT_QUOTES, 'UTF-8'), 'active' => $link['active']];
            })->all(),
        ];
    @endphp
    @include('frontend.mount', ['page' => 'dashboard', 'props' => [
        'csrfToken' => csrf_token(),
        'devices' => $dashboardDevices,
        'creation' => $creation,
        'search' => $search,
        'statusFilter' => $statusFilter,
        'statusOptions' => $statusOptions,
        'stateFilter' => $stateFilter,
        'stateOptions' => $stateOptions,
        'pagination' => $dashboardPagination,
        'mapEndpoints' => ['all' => route('all-devices', array_filter(['search' => $search, 'status' => $statusFilter, 'state' => $stateFilter], fn ($value) => $value !== '')), 'paginated' => route('paginated-devices', array_filter(['search' => $search, 'status' => $statusFilter, 'state' => $stateFilter], fn ($value) => $value !== ''))],
        'links' => ['dashboard' => route('dashboard'), 'profile' => route('profile'), 'create' => route('create-device')],
        'success' => session('success'),
        'sessionError' => $creation && $creation['initiallyOpen'] ? null : session('error'),
    ]])
@else
{{ \App\Support\FrontendAssets::tags() }}
<style>
    .status-dot {
        height: 10px;
        width: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }

    .online {
        background-color: #00FF00;
    }

    .online-unregistered {
        background-color: #0000FF;
    }

    .offline {
        background-color: #808080;
    }

    .low-voltage {
        background-color: #FFFF00;
    }

    .extreme-weather {
        background-color: #FFA500;
    }

    .theft-vandalism {
        background-color: #800080;
    }

    .battery-dead {
        background-color: #FF0000;
    }


    .idle { background-color: #000000; } /* Black */
    .set-up { background-color: #FA8072; } /* Salmon */
    .calibration { background-color: #A52A2A; } /* Brown */
    .solar-track { background-color: #008000; } /* Green */
    .sleep { background-color: #ADD8E6; } /* Light Blue */
    .safe { background-color: #800080; } /* Purple */
	.remote-control { background-color: #FFA500; } /* Orange */
    .gray { background-color: #808080; } /* Gray */
</style>
<div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="text-muted mb-2">Manage your devices and their locations.</p>
        </div>
    </div>
    @if (session('success'))
    <div
        style="color: green; background-color: lightgreen; border: 1px solid green; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('success') }}
    </div>
    @endif
    @if (session('error'))
    <div
        style="color: red; background-color: pink; border: 1px solid red; padding: 10px; margin-top: 10px; font-size: 16px; text-align: center;">
        {{ session('error') }}
    </div>
    @endif
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div id="map" style="height: 400px;"></div>
            <div class="card mt-3">
                <div class="card-header">{{ __('Device Manager') }}</div>
                <div class="card-body">
                    <form action="{{ route('dashboard') }}" method="GET" class="mb-3" role="search">
                        <label for="device-name-search" class="form-label">Search device names</label>
                        <div class="d-flex gap-2">
                            <input id="device-name-search" class="form-control" type="search" name="search" value="{{ $search }}" maxlength="255" placeholder="Search by name">
                            <div class="order-last d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                                <div data-status-filter-root data-props="{{ json_encode(['value' => $statusFilter, 'options' => $statusOptions, 'stateValue' => $stateFilter, 'stateOptions' => $stateOptions]) }}"></div>
                                @include('frontend.mount', ['page' => 'create-device-launcher', 'props' => ['creation' => $creation]])
                            </div>
                            <input type="hidden" name="show" value="{{ $pagination_size }}">
                            <button class="btn btn-primary" type="submit">Search</button>
                            @if ($search !== '')
                                <a class="btn btn-outline-secondary" href="{{ route('dashboard', ['show' => $pagination_size, 'status' => $statusFilter, 'state' => $stateFilter]) }}">Clear</a>
                            @endif
                        </div>
                        @error('search')<p class="text-danger mt-1" role="alert">{{ $message }}</p>@enderror
                    </form>
                    <div class="row">
                        <div class="col-md-2"><strong>Name</strong></div>
                        <div class="col-md-4"><strong>Status</strong></div>
                        <div class="col-md-3"><strong>Last updated</strong></div>
                        <div class="col-md-3"><strong>Actions</strong></div>
                    </div>
                    @forelse ($devices as $device)
                    <div class="row mt-2">
                        <div class="col-md-2"><a href="{{ route('devices.show', $device->id) }}">{{ $device->name ?: $device->serial_no }}</a></div>
                        <div class="col-md-4">

						<span class="status-dot {{ strtolower(str_replace(' ', '-', $device->status)) }}"></span>{{ $device->status }}
						<p>Last updated at: {{ $device->last_updated }}</p>
                        </div>
                        <div class="col-md-3">{{ $device->last_updated }}</div>
                        <div class="col-md-3">
                            <details>
                                <summary aria-label="Actions for {{ $device->name ?: 'device '.$device->id }}" class="btn btn-outline-secondary btn-sm">…</summary>
                                <div class="d-flex flex-column align-items-start gap-2 p-2">
                                    <a href="{{ route('edit-device', $device->id) }}" class="text-primary">Edit</a>
                                    @if ($device->state === 'active')
                                        <form method="POST" action="{{ route('retireDevice', $device->id) }}">@csrf<button class="btn btn-link text-danger p-0" type="submit">Retire</button></form>
                                    @elseif ($device->state === 'inactive')
                                        <form method="POST" action="{{ route('reactivateDevice', $device->id) }}">@csrf<button class="btn btn-link text-primary p-0" type="submit">Reactivate</button></form>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </div>
                    @empty
                    @if ($devices->total() > 0)
                        <p class="text-muted mt-3 mb-0">No devices on this page. Choose another page to see matching devices.</p>
                    @elseif ($search !== '')
                        <p class="text-muted mt-3 mb-0">No devices match this name search. Try another name or clear the search.</p>
                    @else
                        <p class="text-muted mt-3 mb-0">No devices registered yet. Use Create + to add your first device.</p>
                    @endif
                    @endforelse
                    <!-- Show Devices Features -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <form action="{{ route('dashboard') }}" method="GET">
                                <input type="hidden" name="search" value="{{ $search }}">
                                @foreach ($statusFilter as $status)<input type="hidden" name="status[]" value="{{ $status }}">@endforeach
                                @foreach ($stateFilter as $state)<input type="hidden" name="state[]" value="{{ $state }}">@endforeach
                                <select name="show" onchange="this.form.submit()">
                                    <option value="{{ env('PAGINATION_SIZE', 10) }}" {{
                                        $pagination_size==env('PAGINATION_SIZE', 10) ? ' selected' : '' }}>Show (Default
                                        {{ env('PAGINATION_SIZE', 10) }})</option>
                                    <option value="10" {{ $pagination_size==10 ? ' selected' : '' }}>Show 10</option>
                                    <option value="20" {{ $pagination_size==20 ? ' selected' : '' }}>Show 20</option>
                                    <option value="30" {{ $pagination_size==30 ? ' selected' : '' }}>Show 30</option>
                                    <!-- Add more options as needed -->
                                </select>
                            </form>
                            {{ $devices->appends(['show' => $pagination_size, 'search' => $search, 'status' => $statusFilter, 'state' => $stateFilter])->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var map = L.map('map').setView([33.7263, -117.9190], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors'
        }).addTo(map);

        const stateColors = {
        'online': '#00FF00',
        'online-unregistered': '#0000FF',
        'offline': '#808080',
        'low-voltage': '#FFFF00',
        'extreme-weather': '#FFA500',
        'theft-vandalism': '#800080',
        'battery-dead': '#FF0000',
        'unknown': '#808080', // Default color for unknown or other statuses
        // Added new statuses with corresponding colors
        'idle': '#000000', // Black
        'set-up': '#FA8072', // Salmon
        'calibration': '#A52A2A', // Brown
        'solar-track': '#008000', // Green
        'sleep': '#ADD8E6', // Light Blue
        'safe': '#800080', // Purple
		'remote-control': '#FFA500', // Orange
        'gray': '#808080' // Gray
    };

        function loadDevices(page = 1, pagination_size = 10) {
            var url = '/paginated-devices';
            url += '?page=' + page + '&show=' + pagination_size;
            url += '&search=' + encodeURIComponent(@json($search));
            @json($statusFilter).forEach(status => { url += '&status[]=' + encodeURIComponent(status); });
            @json($stateFilter).forEach(state => { url += '&state[]=' + encodeURIComponent(state); });

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const previousError = document.getElementById('legacy-map-error');
                    if (previousError) previousError.remove();
                    map.eachLayer(function (layer) {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });

                    let devices = data.data; // This line might need adjustment based on the actual data structure
                    devices.forEach(function (device) {
                        var state = device.status ? device.status.toLowerCase().replace(' ', '-') : 'unknown';
                        var color = stateColors[state] || '#808080'; // Default to grey if no match found

                        var svgIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: "<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 24 24'><path fill='" + color + "' d='M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z'/></svg>",
                            iconSize: [30, 42], // Adjusted to match Part A
                            iconAnchor: [15, 42] // Adjusted to match Part A
                        });

                        if (device.latitude && device.longitude) {

                            const popup = document.createElement('div');
                            [
                                ['Name', device.name || ''],
                                ['Address', (device.address_1 || 'No Address') + ', ' + (device.address_2 || '')],
                                ['Last Updated', device.last_updated || 'No data'],
                            ].forEach(function ([label, value]) {
                                const heading = document.createElement('strong');
                                heading.textContent = label + ': ';
                                popup.append(heading, document.createTextNode(String(value)), document.createElement('br'));
                            });
                            L.marker([device.latitude, device.longitude], { icon: svgIcon }).addTo(map).bindPopup(popup);
                        }
                    });
                })
                .catch(() => {
                    const mapElement = document.getElementById('map');
                    let feedback = document.getElementById('legacy-map-error');
                    if (!feedback) {
                        feedback = document.createElement('p');
                        feedback.id = 'legacy-map-error';
                        feedback.setAttribute('role', 'alert');
                        mapElement.after(feedback);
                    }
                    feedback.textContent = 'Device locations could not load. Refresh the page to try again.';
                });
        }

        var showSelect = document.querySelector('select[name="show"]');

        showSelect.addEventListener('change', function () {
            loadDevices(1, this.value);
        });


        // Initial load
        var initialPage = new URLSearchParams(window.location.search).get('page') || 1;
        var initialShow = showSelect.value;
        loadDevices(initialPage, initialShow);

        // Handle browser navigation events
        window.onpopstate = function (event) {
            var newParams = new URLSearchParams(window.location.search);
            var newPage = newParams.get('page') || 1;
            var newShow = newParams.get('show') || showSelect.value;
            loadDevices(newPage, newShow);
        };
    });
</script>

@endif
@endsection
