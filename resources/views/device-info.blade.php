@extends('layouts.app') @section('content')
<style>
    /* CSS styles inside the content section */
    .graph-container {
    height: 300px;
    width: 100%;
    margin-bottom: 20px;
    border: 1px solid #ddd;
    padding: 10px;
    background-color: #f9f9f9;
    }
    #batteryEnergyGraph {
    border: 1px solid #ddd;
    padding: 10px;
    background-color: #f9f9f9;
    margin-bottom: 20px; /* Spacing between each graph */
    }
    #emissionsGraph {
    border: 1px solid #ddd;
    padding: 10px;
    background-color: #f9f9f9;
    margin-bottom: 20px; /* Spacing between each graph */
    }
    #savingsGraph {
    border: 1px solid #ddd;
    padding: 10px;
    background-color: #f9f9f9;
    margin-bottom: 20px; /* Spacing between each graph */
    }
    @media print {
    .no-print {
    display: none;
    }
    }
    /* Style the tab */
.tab {
    overflow: hidden;
    border: 1px solid #ccc;
    background-color: #f1f1f1;
}

/* Style the buttons inside the tab */
.tab button {
    background-color: inherit;
    float: left;
    border: none;
    outline: none;
    cursor: pointer;
    padding: 10px 16px;
    transition: 0.3s;
    font-size: 17px;
}

/* Change background color of buttons on hover */
.tab button:hover {
    background-color: #ddd;
}

/* Create an active/current tablink class */
.tab button.active {
    background-color: #ccc;
}

/* Style the tab content (add this if you haven't already) */
.tabcontent {
    display: none;
    padding: 6px 12px;
    border: 0px solid #776e6e;
    border-top: none;
}

#Info {
    position: relative; /* This enables absolute positioning for child elements */
}

.refresh-container {
    position: absolute;
    top: 10px; /* Adjust as necessary for your layout */
    left: 10px; /* Adjust as necessary for your layout */
    z-index: 10; /* Ensure it sits above other content */
    
}

.refresh-btn {
    border: none;
    background: none;
    padding: 0;
    cursor: pointer; /* To indicate the image is clickable */
}

.refresh-btn img {
    width: 24px; /* Adjust the size as necessary */
    height: auto; /* Keeps the aspect ratio of the image */
}

.highlighted {
    border: 2px solid #007bff;
    background-color: #e7f1ff;
}

/* Hidden buttons initially */
.hidden {
    display: none;
}

.form-check-input {
    width: 40px;
    height: 20px;
    margin-left: 0;
    padding-right: 21px !important;
    border-right-width: 22px !important;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-color: #ddd;
    border-radius: 10px;
    position: relative;
    outline: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

.form-check-input:checked {
    background-color: #007bff;
}

.form-check-input::before {
    content: '';
    position: absolute;
    padding-right: 21px !important;
    border-right-width: 22px !important;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background-color: #fff;
    transform: translateX(1px);
    transition: transform 0.3s;
}

.form-check-input:checked::before {
    transform: translateX(20px);
}

.form-check-label {
    margin-left: 10px;
    cursor: pointer;
}

#autoImg,
#remoteImg {
    width: 48px;
    height: auto;
    display: none;
}

#remoteImg {
    display: none;
}



</style>
<div class="container">
	<a href="{{ route('device-manager') }}" class="btn btn-secondary mb-3" style="margin-bottom: -60px !important; margin-left: -70px;">Back</a>
	<div class="row justify-content-center">
			<div class="row">
				<div class="col-md-4">
                    <div class="card">
						<div class="card-header">{{ __('Device Info') }}</div>
                        <div class="card-body">
                            @if(isset($device))
                            <table class="table">
                                <tr>
                                    <td><strong>Hardware:</strong></td>
                                    <td>{{ $device->hardware->name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Alias:</strong></td>
                                    <td>{{ $device->alias}}</td>
                                </tr>
                                <tr>
                                    <td><strong>SKU:</strong></td>
                                    <td>{{ $device->sku }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Serial No.:</strong></td>
                                    <td>{{ $device->serial_no }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Address:</strong></td>
                                    <td>
                                        <address>
                                            {{ $device->address_1 }}, {{ $device->address_2 }}<br />
                                            {{ $device->city }}, {{ $device->state }}<br />
                                            {{ $device->zip_code }}
                                        </address>
                                    </td>
                                </tr>
                            </table>
                            @else
                            <p>Device not found.</p>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8"> 
                    <div class="card">
                        <div class="card-header">{{ __('Monitor') }}</div>
                        @if(!is_null($license))
						<div class="tab">
                            <button class="tablinks active" onclick="openTab(event, 'Graphs')">Graphs</button>
                            <button class="tablinks" onclick="openTab(event, 'Info')">Data</button>
                            @if(isset($device) && $device->hardware_id == 1)
                            <button class="tablinks" onclick="openTab(event, 'SpecialTab')">Remote Control</button>
                            @endif

                        </div>
                        <div class="card-body">
                            @if(isset($device))
                                @endif 
                                @if($device->hardware_id == 1)
                                <div id="Graphs" class="tabcontent" style="display: block;">
                                <div style="height: 300px; width: 100%">
                                    <canvas id="myChart"></canvas>
                                </div>
                                <div style="height: 300px; width: 100%">
                                    <canvas id="psChart"></canvas>
                                </div>
                                <div style="height: 300px; width: 100%">
                                    <canvas id="MotorChart"></canvas>
                                </div>
                                <button onclick="window.print();" class="btn btn-primary no-print">Print Graphs</button>
                               </div>
                                @elseif($device->hardware_id == 2 || $device->hardware_id == 3)
                                <!-- Section title for clarity -->
                  
                             <div id="Graphs" class="tabcontent" style="display: block;">
									<div id="realtimeGraphs">
										<!-- Container for the Battery Energy graph -->
										<select id="timeFrameSelect">
											<option value="today">Today</option>
											<option value="yesterday">Yesterday</option>
											<option value="past7days">Past 7 Days</option>
											<option value="monthToDate">Month to Date</option>
											<option value="yearToDate">Year to Date</option>
											<option value="lifetime">Lifetime</option>
										</select>
										<div id="savingsGraph" style="height: 300px; width: 100%; margin-bottom: 20px" class="graph-container">
											<canvas id="savingsGraphCanvas"></canvas>
										</div>
										<div id="pBattGraph" style="height: 300px; width: 100%; margin-bottom: 20px" class="graph-container">
											<canvas id="pBattGraphCanvas"></canvas>
										</div>
										<div id="pBattGraph" style="height: 300px; width: 100%; margin-bottom: 20px" class="graph-container">
											<canvas id="eBattGraphCanvas"></canvas>
										</div>
										<div id="pBattGraph" style="height: 300px; width: 100%; margin-bottom: 20px" class="graph-container">
											<canvas id="emissionsGraphCanvas"></canvas>
										</div>
                                        <button onclick="window.print();" class="btn btn-primary no-print">Print</button>
									</div>
								</div>
                                @endif
								


       
@if($device->hardware_id == 1)

<div id="Info" class="tabcontent">       
    <div class="refresh-container">
        <button id="refreshInfo" class="btn refresh-btn" data-device-id="{{ $device->id }}">
            <img src="https://tezca.net/obsidian/public/img/refresh.png" alt="Refresh" />
        </button>
    </div>                     
            <div class="row" style="margin-top: 50px;">
                <div class="col-md-6" >
                    @if(isset($latestStatus)) 
                    
                    @if($stateMessage == 'idle')
                    <p>
                        <strong>State:</strong>
                        <span style="color: black"
                            >{{ $stateMessage }}</span>
                    </p>
                    @elseif($stateMessage == 'Set up')
                    <p>
                        <strong>State:</strong>
                        <span style="color: salmon"
                            >{{ $stateMessage }}</span>
                    </p>
                    @elseif($stateMessage == 'calibration')
                    <p>
                        <strong>State:</strong>
                        <span style="color: brown"
                            >{{ $stateMessage }}</span>
                    </p>
                    @elseif($stateMessage == 'solar track')
                    <p>
                        <strong>State:</strong>
                        <span style="color: green"
                            >{{ $stateMessage }}</span>
                    </p>
                    @elseif($stateMessage == 'sleep')
                    <p>
                        <strong>State:</strong>
                        <span style="color: lightblue"
                            > {{ $stateMessage }}</span>
                    </p>
                    @elseif($stateMessage == 'safe')
                    <p>
                        <strong>State:</strong>
                        <span style="color: purple"
                            > {{ $stateMessage }}</span>
                    </p>
                    @else
                    <p><strong>State:</strong> {{ $stateMessage }}</p>
                    @endif @endif
                    <p><strong>PS1:</strong> {{ $latestStatus->ps1 }}</p>
                    <p><strong>PS Average:</strong> {{ $latestStatus->ps_avg }}</p>
                    <p>
                        <strong>Motor Speed:</strong> {{ $latestStatus->motor_speed }}
                    </p>
                    <p><strong>CTS Value:</strong> {{ $ctsValue }}</p>
                </div>
                <div class="col-md-6">
                    <p>
                        <strong>Updated on:</strong> {{ $latestStatus->updated_at_pst
                        ?? 'N/A' }}
                    </p>
                    <p><strong>PS2:</strong> {{ $latestStatus->ps2 }}</p>
                    <p><strong>PDS:</strong> {{ $latestStatus->pds }}</p>
                    <p>
                        <strong>Temp (°C):</strong> {{ $latestStatus->temp }}
                        <!--number_format(($latestStatus->temp * 9 / 5) + 32, 1) }} °C-->
						

                    </p>
                </div>
                <button onclick="window.print();" class="btn btn-primary no-print">Print Data</button>


                    </div>
 
</div>
@elseif($device->hardware_id == 2 || $device->hardware_id == 3)
<div id="Info" class="tabcontent">                            
    <div class="card" style="border-top-width: 0px; border-right-width: 0px; border-bottom-width: 0px; border-left-width: 0px;">
        <div class="card-body">
            <button id="refreshData" class="btn refresh-btn" data-device-id="{{ $device->id }}" style="border: none; background: none; padding: 0;">
                <img src="http://tezca.net/obsidian/public/img/refresh-icon3.png" alt="Refresh" style="width: 24px; height: auto;">
            </button>
            <table class="table table-bordered" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Data Point</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tablelatestLog as $key => $value)
                        <tr>
                            <td>{{ ucfirst(str_replace('_', ' ', $key)) }}</td>
                            <td>{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
</div>
@endif

@if(isset($device) && $device->hardware_id == 1)
<!-- New content for the special tab, only shown if hardware_id is 1 -->
<div id="SpecialTab" class="tabcontent">
    <!-- Make sure the main buttons have class "main" -->
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" id="toggleSwitch">
        <label class="form-check-label" for="toggleSwitch" id="toggleText">Automatic</label>
        <img src="{{ asset('img/auto2.png') }}" alt="Automatic" id="autoImg">
        <img src="{{ asset('img/remote.png') }}" alt="Remote Control" id="remoteImg">
    </div>

    <div class="row mt-3 hidden" id="controlButtons">
        <div class="col-md-3">
            <button class="btn" id="upButton">
                <div>Up</div>
                <img src="{{ asset('img/up.png') }}" alt="Up" style="width: 48px; height: auto;">
            </button>
        </div>
        <div class="col-md-2">
            <button class="btn" id="stopButton">
                <div>Stop</div>
                <img src="{{ asset('img/stop.png') }}" alt="Stop" style="width: 48px; height: auto;">
            </button>
        </div>
        <div class="col-md-12">
            <button class="btn" id="downButton">
                <img src="{{ asset('img/down.png') }}" alt="Down" style="width: 48px; height: auto;">
                <div>Down</div>
            </button>
        </div>
    </div>

 
</div>
@endif
@else 
							<div style="margin-left: 45%;">
								<a href="{{ route('subscription-manager') }}">
									<img src="{{ asset('img/sub-lock.png') }}" alt="Locked" style="width: 100px; height: auto; margin-left: -10px;"></img>
										<p>Click to Unlock</p>
								</a>
							</div>
@endif
                        </div>            
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment-timezone/0.5.33/moment-timezone-with-data.min.js"></script>
<script>
   document.addEventListener('DOMContentLoaded', function() {
    const toggleSwitch = document.getElementById('toggleSwitch');
    const toggleText = document.getElementById('toggleText');
    const controlButtons = document.getElementById('controlButtons');
    const autoImg = document.getElementById('autoImg');
    const remoteImg = document.getElementById('remoteImg');
    let motorSpeed = 0.0;

    toggleSwitch.addEventListener('change', function() {
        if (toggleSwitch.checked) {
            toggleText.innerHTML = 'Remote Control';
            controlButtons.classList.remove('hidden');
            autoImg.style.display = 'none';
            remoteImg.style.display = 'inline';
        } else {
            toggleText.innerHTML = 'Automatic';
            controlButtons.classList.add('hidden');
            autoImg.style.display = 'inline';
            remoteImg.style.display = 'none';
        }
		
		fetch('/update-solar-tracker', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': '{{ csrf_token() }}'
			},
			body: JSON.stringify({
				mode: toggleSwitch.checked,
				motor_speed: 0.0,
				serial_no: '{{ $device->serial_no }}'
			})
		})
		.then(response => response.json())
		/*.then(data => {
			console.log(data);
			if (data.success) {
				alert('Settings applied successfully.');
			} else {
				alert('An error occurred: ' + data.message);
			}
		})*/
		.catch(error => console.error('Error:', error));
		
    });

    document.getElementById('upButton').addEventListener('click', function() {   	
		fetch('/update-solar-tracker', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': '{{ csrf_token() }}'
			},
			body: JSON.stringify({
				mode: 1,
				motor_speed: 20.0,
				serial_no: '{{ $device->serial_no }}'
			})
		})
		.then(response => response.json())
		/*.then(data => {
			console.log(data);
			if (data.success) {
				alert('Settings applied successfully.');
			} else {
				alert('An error occurred: ' + data.message);
			}
		})*/
		.catch(error => console.error('Error:', error));
    });

    document.getElementById('downButton').addEventListener('click', function() {  
		fetch('/update-solar-tracker', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': '{{ csrf_token() }}'
			},
			body: JSON.stringify({
				mode: 1,
				motor_speed: -20.0,
				serial_no: '{{ $device->serial_no }}'
			})
		})
		.then(response => response.json())
		/*.then(data => {
			console.log(data);
			if (data.success) {
				alert('Settings applied successfully.');
			} else {
				alert('An error occurred: ' + data.message);
			}
		})*/
		.catch(error => console.error('Error:', error));
    });
	
	document.getElementById('stopButton').addEventListener('click', function() {  
		fetch('/update-solar-tracker', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-CSRF-TOKEN': '{{ csrf_token() }}'
			},
			body: JSON.stringify({
				mode: 1,
				motor_speed: 0.0,
				serial_no: '{{ $device->serial_no }}'
			})
		})
		.then(response => response.json())
		/*.then(data => {
			console.log(data);
			if (data.success) {
				alert('Settings applied successfully.');
			} else {
				alert('An error occurred: ' + data.message);
			}
		})*/
		.catch(error => console.error('Error:', error));
    });
	
    // Set initial state based on the checkbox status
    if (toggleSwitch.checked) {
        toggleText.innerHTML = 'Remote Control';
        controlButtons.classList.remove('hidden');
        autoImg.style.display = 'none';
        remoteImg.style.display = 'inline';
    } else {
        toggleText.innerHTML = 'Automatic';
        controlButtons.classList.add('hidden');
        autoImg.style.display = 'inline';
        remoteImg.style.display = 'none';
    }
});

</script>


<script>

    
@if($device->hardware_id == 1)
    document.getElementById('refreshInfo').addEventListener('click', function() {
    event.preventDefault();  // This line prevents the form from submitting traditionally.
    const deviceId = this.getAttribute('data-device-id');
    console.log(`Fetching data for device ID: ${deviceId}`);

    fetch(`/device/${deviceId}/refresh`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => Promise.reject(errorData));
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);  // Log the raw data received
            updateDeviceInfo(data);
            console.log('Device info updated successfully.');
        })
        .catch(error => {
            console.error('Error fetching and updating device data:', error);
        });
});

function updateDeviceInfo(data) {
    const firstColDiv = document.querySelector('#Info .row .col-md-6:first-child');
    firstColDiv.innerHTML = `<p><strong>State:</strong> <span style="color: ${getStateColor(data.state)}">${data.state}</span></p>
                             <p><strong>PS1:</strong> ${data.ps1}</p>
                             <p><strong>PS Average:</strong> ${data.ps_avg}</p>
                             <p><strong>Motor Speed:</strong> ${data.motor_speed}</p>
                             <p><strong>CTS Value:</strong> ${data.cts}</p>`;

							const secondColDiv = document.querySelector('#Info .row .col-md-6:nth-child(2)');
							secondColDiv.innerHTML = `<p><strong>Updated on:</strong> ${data.updated_at_pst ?? 'N/A'}</p>
                              <p><strong>PS2:</strong> ${data.ps2}</p>
                              <p><strong>PDS:</strong> ${data.pds}</p>
                              <p><strong>Temp (°C):</strong> ${data.temp}</p>`;
                         
                            
}

function celsiusToFarhenheit(tempCelsius) {
    // Converts Celsius to Fahrenheit and formats the string.
    const tempFahrenheit = (tempCelsius * 9 / 5) + 32;
    return `${tempFahrenheit.toFixed(1)} °F`;
}

function getStateColor(state) {
    switch(state) {
        case 'idle': return 'black';
        case 'Set up': return 'salmon';
        case 'calibration': return 'brown';
        case 'solar track': return 'green';
        case 'sleep': return 'lightblue';
        case 'safe': return 'purple';
        default: return 'gray';
    }
}
@endif
@if($device->hardware_id == 2)
function formatKey(key) {
    // Remove underscores and capitalize the first letter of the string
    let formattedKey = key.replace(/_/g, ' ');

    return formattedKey.charAt(0).toUpperCase() + formattedKey.slice(1).toLowerCase();
}

    document.getElementById('refreshData').addEventListener('click', function() {
        console.log('Refresh button clicked.'); // Log when the button is clicked
    
        const deviceId = this.getAttribute('data-device-id');
        console.log(`Fetching data for device ID: ${deviceId}`); // Log the device ID being used
        if (!deviceId) {
            console.error('Device ID is missing.');
            return;
        }
    
        fetch(`/devices/${deviceId}/latest-solar-data`)
            .then(response => {
                console.log('Response received'); // Log when the response is received
                if (!response.ok) {
                    console.error('Network response was not ok:', response);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Processing data', data); // Log the raw data received
                if (data.error) {
                    console.error('Error fetching data:', data.error);
                    return;
                }
        
                const tableBody = document.querySelector('#Info .card-body tbody');
                tableBody.innerHTML = ''; // Clear the current table body
                console.log('Table cleared, updating with new data.'); // Log after clearing the table
    
                // Assuming data keys are consistent and you want to display all as rows
                Object.keys(data).forEach(key => {
            const value = data[key];
            const formattedKey = formatKey(key); // Format the key for display
            const row = `<tr>
                            <td>${formattedKey}</td>
                            <td>${value}</td>
                         </tr>`;
            tableBody.innerHTML += row; // Add the new rows
        });
                console.log('Data update complete.'); // Log when data update is complete
            })
            .catch(error => {
                console.error('There was a problem with the fetch operation:', error);
            });
    });
    @endif

    </script>
    
      
<script>
    
    @if(isset($device))
        @if($device->hardware_id == 1)
        console.log("inside hardware 1");


        function openTab(evt, tabName) {
    // Declare all variables
    var i, tabcontent, tablinks;

    // Get all elements with class="tabcontent" and hide them
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    // Get all elements with class="tablinks" and remove the class "active"
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
    }

    // Set default open tab here by calling the function on document ready
    document.addEventListener("DOMContentLoaded", function() {
        // Automatically click the first active tab
        document.getElementsByClassName("tablinks active")[0].click();
    });


    const tempAverages = @json($tempAverages); // Replace this line with your actual data fetching mechanism

        // Log the tempAverages array to the console
    console.log("Temp Averages Array:", tempAverages);
    new Chart(document.getElementById("myChart"), {
    type: 'line',
    data: {
        labels: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], // Reversed order
        datasets: [{
            data: @json(array_reverse($tempAverages)), // Reverse the data array if not already reversed server-side
            label: "Temperature",
            borderColor: "#007BFF",
            backgroundColor: "#007BFF",
            pointRadius: 5,
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Temperature (C°)',
                },
                position: 'right' // Position the y-axis on the right
            },
            x: {
                reverse: true,
                title: {
                    display: true,
                    text: 'Time (hrs)',
                }
            }
        },
        title: {
            display: true,
            text: 'Temp Averages',
            fontSize: 16,
        },
        legend: {
            display: true,
            position: 'top',
        },
        tooltips: {
            backgroundColor: 'rgba(0, 0, 0, 0.7)',
            titleFontColor: '#fff',
            bodyFontColor: '#fff',
        }
    }
});

    
new Chart(document.getElementById("psChart"), {
    type: 'line',
    data: {
        labels: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], // Reversed order
        datasets: [{
            data: @json(array_reverse($psAverages)), // Assume the data is reversed in the server-side code
            label: "Light Intensity",
            borderColor: "#FF0000",
            backgroundColor: "#FF0000",
            pointRadius: 5,
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Light Intensity',
                },
                position: 'right'
            },
            x: {
                reverse: true,
                title: {
                    display: true,
                    text: 'Time (hrs)',
                }
            }
        },
        title: {
            display: true,
            text: 'PS Averages',
            fontSize: 16,
        },
        legend: {
            display: true,
            position: 'top',
        },
        tooltips: {
            backgroundColor: 'rgba(0, 0, 0, 0.7)',
            titleFontColor: '#fff',
            bodyFontColor: '#fff',
        }
    }
});

    
new Chart(document.getElementById("MotorChart"), {
    type: 'line',
    data: {
        labels: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24], // Reversed order
        datasets: [{
            data: @json(array_reverse($motorAvgs)), // Assume this is reversed in server-side code
            label: 'Motor Speed',
            borderColor: '#00FF00',
            backgroundColor: '#00FF00',
            pointRadius: 5,
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: false,
                title: {
                    display: true,
                    text: 'Motor Speed'
                },
                position: 'right',
            },
            x: {
				reverse: true,
                title: {
                    display: true,
                    text: 'Time (hrs)'
                },
            },
        },
        plugins: {
            legend: {
                display: true,
                position: 'top',
            },
            title: {
                display: true,
                font: {
                    size: 16,
                },
            },
            tooltip: {
                backgroundColor: '00FF00',
                titleColor: '#fff',
                bodyColor: '#fff',
            }
        }
    }
});
    @endif
    
    @if($device->hardware_id == 2 || $device->hardware_id == 3)
    console.log("inside hardware 2 and 3");

    
    function openTab(evt, tabName) {
    // Declare all variables
    var i, tabcontent, tablinks;

    // Get all elements with class="tabcontent" and hide them
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    // Get all elements with class="tablinks" and remove the class "active"
    tablinks = document.getElementsByClassName("tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    // Show the current tab, and add an "active" class to the button that opened the tab
    document.getElementById(tabName).style.display = "block";
    evt.currentTarget.className += " active";
    }

    // Set default open tab here by calling the function on document ready
    document.addEventListener("DOMContentLoaded", function() {
        // Automatically click the first active tab
        document.getElementsByClassName("tablinks active")[0].click();
    });
                



    $(document).ready(function() {
        
    
    var pBattChart; // Assuming a separate chart for pBatt  
    function fetchDataAndRenderPbattChart(timeFrame) {
        
      
    
    $.ajax({
    url: "{{ url('/device/' . $device->id . '/fetch-graph-data') }}", // Adjust accordingly
    type: 'GET',
    data: {timeFrame: timeFrame},
    success: function(data) {
    console.log("Received data for pBatt chart:", data);
    
    // var url = "{{ url('/device/' . $device->id . '/fetch-graph-data') }}";
    // console.log("The AJAX call URL is: ", url);
    
    
    var ctx = $('#pBattGraphCanvas').get(0).getContext('2d'); // Ensure you have a <canvas> element with this ID
    
      if (pBattChart) {
        pBattChart.destroy(); // Destroy the existing chart instance if present
    }
    console.log("Initializing pBatt chart");
    pBattChart = new Chart(ctx, {
        type: 'line', // Specify line chart type
        data: {
            labels: data.pBatt.labels, // Use labels from the pBatt dataset
            datasets: [{
                label: 'Battery Power (W)',
                backgroundColor: 'rgba(54, 162, 235, 0.2)',
                borderColor: 'rgba(54, 162, 235, 1)',
                data: data.pBatt.datasets[0].data, // Use pBatt data
                fill: false, // Set to false for a line chart without a filled area
                tension: 0.1 // Optional: adjust line smoothness
            }]
        },
        options: {
            scales: {
                y: {
					title: {
						display: true,
						text: "Battery Power (W)"
					},
                    beginAtZero: true,
					position: 'right'
                }
            }
        }
    });
    }, error: function(xhr, status, error) {
      console.error("Error fetching pBatt data:", error);
    }
    });
    
}
  
    
    var savingsChart; 
    function fetchDataAndRenderChart(timeFrame) {
    $.ajax({
    url: "{{ url('/device/' . $device->id . '/fetch-graph-data') }}", // Adjust accordingly
    type: 'GET',
    data: {timeFrame: timeFrame},
    success: function(data) {
        // Convert and format labels, setup chart, etc. (see previous examples for details)
        var ctx = $('#savingsGraphCanvas');
        if (savingsChart) {
            savingsChart.destroy();
        }
        savingsChart = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                scales: {
                    y: {
						title: {
							display: true,
							text: "Savings (U.S. Dollars)"
						},
                        beginAtZero: true,
						position: 'right'
                    }
                }
            }
        });
    }
    });
    }
    

    var eBattChart; 
    function fetchDataAndRenderEBattChart(timeFrame) {
    $.ajax({
    url: "{{ url('/device/' . $device->id . '/fetch-graph-data') }}",
    type: 'GET',
    data: {timeFrame: timeFrame},
    success: function(data) {
    // Assuming eBatt data is properly structured in your AJAX response
    var ctx = $('#eBattGraphCanvas').get(0).getContext('2d'); // Adjust ID for e_batt canvas
    if (eBattChart) {
      eBattChart.destroy();
    }
    eBattChart = new Chart(ctx, {
      type: 'bar', // Choose the chart type: 'line' for line chart, 'bar' for bar chart, etc.
      data: data.eBatt, // Make sure your backend structure matches this. Adjust if needed.
      options: {
          scales: {
              y: {
				  title: {
					display: true,
					text: "Battery Energy (WHr)"
				  },
                  beginAtZero: true,
				  position: 'right'
              }
          }
      }
    });
    }
    });
    }
    

    var emissionsChart;
    function fetchDataAndRenderEmissionsChart(timeFrame) {
    $.ajax({
    url: "{{ url('/device/' . $device->id . '/fetch-graph-data') }}",
    type: 'GET',
    data: {timeFrame: timeFrame},
    success: function(data) {
    // Assuming emissions data is properly structured in your AJAX response
    var ctx = $('#emissionsGraphCanvas').get(0).getContext('2d'); // Adjust ID for emissions canvas
    if (emissionsChart) {
      emissionsChart.destroy();
    }
    emissionsChart = new Chart(ctx, {
      type: 'bar', // Choose the chart type: 'line' for line chart, 'bar' for bar chart, etc.
      data: data.emissions, // Make sure your backend structure matches this. Adjust if needed.
      options: {
          scales: {
              y: {
				  title: {
					display: true,
					text: "CO2 Emissions Reduced (lbs)"
				  },
                  beginAtZero: true,
				  position: 'right',
              }
          },
          title: {
              display: false,
              text: 'Emissions vs Time' // Optional: Customize your chart title
          }
      }
    });
    }
    });
    }
    
    // Initial chart load
    fetchDataAndRenderPbattChart($('#timeFrameSelect').val());
    fetchDataAndRenderChart($('#timeFrameSelect').val());
    fetchDataAndRenderEBattChart($('#timeFrameSelect').val());
    fetchDataAndRenderEmissionsChart($('#timeFrameSelect').val());
    
    
    
    // Handle time frame changes
    $('#timeFrameSelect').change(function() {
    fetchDataAndRenderEBattChart($(this).val());
    fetchDataAndRenderPbattChart($(this).val());
    fetchDataAndRenderChart($(this).val());
    fetchDataAndRenderEmissionsChart($(this).val());
    
    });
    
    
    
    });
    
    
    
    // =================================
    
    //   console.log("Hourly p_batt Data:", @json($hours), @json($totalPBatt));
    
    // Initialize the p_batt bar chart
    new Chart(document.getElementById("pBattBarChart"), {
    
    
    type: 'line',
    data: {
    labels: @json($hours), // X-axis labels (hours of the day)
    datasets: [{
      label: 'Battery Power (W)',
      data: @json($totalPBatt), // Y-axis data
      backgroundColor: "rgba(54, 162, 235, 0.2)",
      borderColor: "rgba(54, 162, 235, 1)",
      borderWidth: 1
    }]
    },
    options: {
    scales: {
      y: {
          beginAtZero: true,
          title: {
              display: true,
              text: 'Battery Power (W)',
			  position: 'right'
          }
      },
      x: {
          title: {
              display: true,
              text: 'Hour of Day'
          }
      }
    },
    title: {
      display: false,
      text: 'Hourly Sum of p_batt for Today'
    }
    }
    });
    
    new Chart(document.getElementById("eBattBarChart"), {
    type: 'bar',
    data: {
    labels: @json($dates), // X-axis labels from $dates
    datasets: [{
    label: 'Daily Battery Energy (Whr)',
    data: @json($dailyEBatt), // Y-axis data from $dailyEBatt
    backgroundColor: "rgba(75, 192, 192, 0.2)",
    borderColor: "rgba(75, 192, 192, 1)",
    borderWidth: 1
    }]
    },
    options: {
    scales: {
    y: {
        beginAtZero: true,
        title: {
            display: true,
            text: 'Energy (WHr)',
			position: 'right'
        }
    },
    x: {
        title: {
            display: true,
            text: 'Date'
        }
    }
    },
    title: {
    display: false,
    text: 'Daily Battery Energy for the Last 7 Days'
    }
    }
    });
    @endif
@endif
</script>
@endsection