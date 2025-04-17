@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Delta Sampling') }}</div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div id="gauge" style="width:400px; height:320px;"></div>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group">
                                <li class="list-group-item">
                                    <strong>Accumulative SPB:</strong> 
                                    {{ number_format($accumulative_spb, 2) }}
                                </li>
                                <li class="list-group-item" style="color: green;">
                                    <strong>Maximum SPB for the Day:</strong> 
                                    {{ number_format($max_spb_value, 5) }}
                                </li>
                                <li class="list-group-item" style="color: #FF6633;">
                                    <strong>Minimum SPB for the Day:</strong> 
                                    {{ number_format($min_spb_value, 5) }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include JustGage and its dependencies -->
<script src="https://cdn.jsdelivr.net/npm/raphael@2.3.0/raphael.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/justgage@1.3.1/justgage.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var latestSpbValue = parseFloat("{{ $latest_spb_value }}");
        var minSpbValue = parseFloat("{{ $min_spb_value }}");
        var maxSpbValue = parseFloat("{{ $max_spb_value }}");

        // Log values to console
        console.log("Latest SPB Value: ", latestSpbValue);
        console.log("Min SPB Value: ", minSpbValue);
        console.log("Max SPB Value: ", maxSpbValue);

        var gauge = new JustGage({
            id: "gauge",
            value: latestSpbValue,
            min: 0, // Adjust the minimum value to 0 for better visualization
            max: maxSpbValue,
            title: "SPB (USD)",
            label: "",
            gaugeWidthScale: 0.6,
            counter: true,
            decimals: 10, // Increase the precision to show more decimal places
            hideMinMax: false,
            levelColors: [
                "#00FF00"
            ],
            customSectors: [
                {
                    color: "#FFA500", // Orange sector for the lowest value
                    lo: minSpbValue - (maxSpbValue * 0.01),
                    hi: minSpbValue + (maxSpbValue * 0.01)
                },
                {
                    color: "#000000", // Black sector for the latest value
                    lo: latestSpbValue - (maxSpbValue * 0.01),
                    hi: latestSpbValue + (maxSpbValue * 0.01)
                },
                {
                    color: "#00FF00", // Green sector for the maximum value
                    lo: maxSpbValue - (maxSpbValue * 0.01),
                    hi: maxSpbValue + (maxSpbValue * 0.01)
                }
            ],
            pointer: true,
            pointerOptions: {
                color: '#0000FF' // Pointer color (blue)
            },
            gaugeColor: '#C0C0C0', // Gauge background color (white)
            gaugeFillColor: '#F0F0F0', // Gauge fill color (light gray)
            titleFontColor: '#FF0000', // Title text color (red)
            labelFontColor: '#000000', // Label text color (blue)
            valueFontColor: '#000033' // Value text color (green)
        });
    });
</script>
@endsection
