@extends('layouts.app')

@section('content')
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
            <form id="allDevicesForm">
                <input type="checkbox" name="showAll" id="showAllDevicesCheckbox"> Show All Devices
            </form>
            <div class="card">
                <div class="card-header">{{ __('Device Manager') }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2"><strong>Hardware</strong></div>
                        <div class="col-md-2"><strong>Alias</strong></div>
                        <div class="col-md-4"><strong>Status</strong></div>
                        <div class="col-md-3"><strong>Actions<strong></div>
                    </div>
                    @foreach ($devices as $device)
                    <div class="row mt-2">
                        <div class="col-md-2">{{ $device->hardware->name }}</div>
                        <div class="col-md-2">{{ $device->alias }}</div>
                        <div class="col-md-4">
						@if($device->hardware_id == 1)
						<span class="status-dot {{ strtolower(str_replace(' ', '-', $device->state)) }}"></span>{{ $device->state }}
						<p>Last updated at: {{ $device->last_updated }}</p>
						@elseif(in_array($device->hardware_id, [2, 3]))
							<span class="status-dot {{ strtolower(str_replace(' ', '-', $device->state)) }}"></span>{{ $device->state }}
							<p>Last updated at: {{ $device->last_updated }}</p>
						@endif
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('device-info', $device->id) }}" class="text-primary">View</a>
                            |
                            <a href="{{ route('edit-device', $device->id) }}" class="text-primary">Edit</a>
                            |
                            <a href="{{ route('deleteDevice', $device->id) }}"
                                class="text-danger delete-link">Remove</a>
                        </div>
                    </div>
                    @endforeach
                    <!-- Show Devices Features -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <form action="{{ route('device-manager') }}" method="GET">
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
                            {{ $devices->appends(['show' => $pagination_size])->links() }}
                        </div>
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

        function loadDevices(page = 1, showAll = false, pagination_size = 10) {
            console.log("Loading devices for page: " + page + ", show all: " + showAll + ", number of devices to show: " + pagination_size); // Logs when devices are being loaded

            var url = showAll ? '/all-devices' : '/paginated-devices';
            url += '?page=' + page + '&show=' + pagination_size;

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Fetched data:", data); // Logs the fetched data

                    map.eachLayer(function (layer) {
                        if (layer instanceof L.Marker) {
                            map.removeLayer(layer);
                        }
                    });

                    let devices = showAll ? data : data.data; // This line might need adjustment based on the actual data structure
                    console.log("Devices array after processing:", devices); // Logs the devices array after processing

                    devices.forEach(function (device) {
                        var state = device.state ? device.state.toLowerCase().replace(' ', '-') : 'unknown';
                        var color = stateColors[state] || '#808080'; // Default to grey if no match found

                        console.log("Processing device:", device); // Logs each device being processed


                        var svgIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: "<svg xmlns='http://www.w3.org/2000/svg' width='30' height='30' viewBox='0 0 24 24'><path fill='" + color + "' d='M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z'/></svg>",
                            iconSize: [30, 42], // Adjusted to match Part A
                            iconAnchor: [15, 42] // Adjusted to match Part A
                        });

                        if (device.latitude && device.longitude) {

                            L.marker([device.latitude, device.longitude], { icon: svgIcon }).addTo(map).bindPopup(
                                `<strong>Hardware:</strong> ${device.hardware.name}<br>` +
                                `<strong>Alias:</strong> ${device.alias}<br>` +
                                `<strong>Address:</strong> ${device.address_1 || 'No Address'}, ${device.address_2 || ''}<br>` +
                                `<strong>Last Updated:</strong> ${device.last_updated || 'No data'}`
                            );
                        }
                    });
                })
                .catch(error => {
                    console.error('Fetch error:', error); // Logs if there's an error during fetch
                });
        }

        var showSelect = document.querySelector('select[name="show"]');
        var showAllCheckbox = document.getElementById('showAllDevicesCheckbox');

        showSelect.addEventListener('change', function () {
            loadDevices(1, showAllCheckbox.checked, this.value);
        });

        showAllCheckbox.addEventListener('change', function () {
            loadDevices(1, this.checked, showSelect.value);
        });

        // Initial load
        var initialPage = new URLSearchParams(window.location.search).get('page') || 1;
        var initialShow = showSelect.value;
        loadDevices(initialPage, showAllCheckbox.checked, initialShow);

        // Handle browser navigation events
        window.onpopstate = function (event) {
            var newParams = new URLSearchParams(window.location.search);
            var newPage = newParams.get('page') || 1;
            var newShow = newParams.get('show') || showSelect.value;
            loadDevices(newPage, showAllCheckbox.checked, newShow);
        };
    });
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Attach click event listener to all delete links
        document.querySelectorAll(".delete-link").forEach(function (link) {
            link.addEventListener("click", function (event) {
                // Prevent the default action
                event.preventDefault();
                // Show confirmation dialog
                if (confirm("Are you sure you want to delete this device?")) {
                    // If confirmed, proceed with the deletion
                    window.location.href = this.href;
                }
                // Else, do nothing
            });
        });
    });
</script>
@endsection