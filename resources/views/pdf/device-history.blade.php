<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Device history report</title>
    <style>
        @page { margin: 38pt 42pt 46pt; }
        body { margin: 0; color: #172a43; font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.3; }
        h1 { margin: 5pt 0 4pt; font-size: 23pt; line-height: 1.2; font-weight: bold; letter-spacing: -0.6pt; }
        h2 { margin: 0 0 8pt; font-size: 13pt; line-height: 1.3; }
        h3 { margin: 0; font-size: 12pt; line-height: 1.3; }
        p { margin: 0; }
        .eyebrow { color: #2862c4; font-size: 8pt; letter-spacing: 1.4pt; font-weight: bold; }
        .muted { color: #52627a; }
        .small { font-size: 8pt; }
        .intro { padding-bottom: 12pt; border-bottom: 2pt solid #2862c4; margin-bottom: 15pt; }
        .created { margin-top: 7pt; font-size: 8pt; }
        .section { margin-bottom: 14pt; }
        .device-name { margin-bottom: 7pt; font-size: 15pt; font-weight: bold; overflow-wrap: break-word; }
        .details { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .details td { vertical-align: top; padding: 5pt 10pt 5pt 0; border-bottom: 0.5pt solid #e1e7ee; overflow-wrap: break-word; }
        .details .label { color: #52627a; font-size: 7.5pt; display: block; margin-bottom: 2pt; }
        .details .value { font-size: 9pt; }
        .range { background: #eff4fa; border: 0.6pt solid #d9e3ef; padding: 9pt 12pt; }
        .range-row { margin-top: 5pt; }
        .range-label { color: #52627a; display: inline-block; width: 37pt; }
        .reading-time { margin: -4pt 0 6pt; font-size: 8pt; color: #52627a; }
        .latest-section { page-break-inside: avoid; }
        .latest td { padding: 6pt 8pt; background: #f5f7fa; border: 2pt solid #ffffff; }
        .latest .value { font-size: 11.5pt; }
        .notes { font-size: 8pt; color: #52627a; }
        .notes p { margin-bottom: 5pt; }
        .graph-page { page-break-before: always; }
        .page-heading { border-bottom: 1pt solid #dce4ed; padding-bottom: 8pt; margin-bottom: 13pt; }
        .page-heading .subtitle { margin-top: 4pt; font-size: 8pt; color: #52627a; overflow-wrap: break-word; }
        .chart { border: 0.6pt solid #d9e3ef; margin-bottom: 13pt; padding: 10pt 10pt 7pt; page-break-inside: avoid; }
        .chart-head { margin: 0 2pt 6pt; }
        .chart-count { font-size: 7.5pt; color: #52627a; margin-top: 3pt; }
        .chart-image { width: 100%; height: auto; }
        .chart-note { margin: 4pt 2pt 0; color: #52627a; font-size: 7pt; }
    </style>
</head>
<body>
@php
    $device = $report['device'];
    $latest = $report['latest'];
    $display = static fn ($value) => $value === null || $value === '' ? 'N/A' : (string) $value;
    $date = static fn ($value) => $value->setTimezone($report['timezone'])->format('M j, Y, g:i:s A T (P)');
    $address = array_values(array_filter([
        $device['address_1'] ?? null, $device['address_2'] ?? null,
        trim(implode(' ', array_filter([$device['city'] ?? null, $device['address_state'] ?? null, $device['zip_code'] ?? null]))),
        $device['country'] ?? null,
    ], static fn ($value) => $value !== null && $value !== ''));
    $metrics = [
        ['State', 'state', ''], ['Temperature', 'temp_f', ' °F'], ['Motor speed', 'motor_speed', ''],
        ['PS1', 'ps1', ''], ['PS2', 'ps2', ''], ['PS average', 'ps_avg', ''],
        ['PDS', 'pds', ''], ['CTS', 'cts_state', ''],
    ];
@endphp
<div class="intro">
    <p class="eyebrow">OBSIDIAN / SOLAR TRACKER</p>
    <h1>Device history report</h1>
    <p class="muted">All measurements for the selected period, with the latest device reading.</p>
    <p class="created"><span class="muted">Created</span> {{ $date($report['generated_at']) }}</p>
</div>

<div class="section">
    <p class="device-name">{{ $display($device['name'] ?? null) }}</p>
    <table class="details">
        <tr>
            <td><span class="label">SERIAL NUMBER</span><span class="value">{{ $display($device['serial_no'] ?? null) }}</span></td>
            <td><span class="label">SKU</span><span class="value">{{ $display($device['sku'] ?? null) }}</span></td>
            <td><span class="label">ORDER NUMBER</span><span class="value">{{ $display($device['order_no'] ?? null) }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">INSTALLATION ADDRESS</span><span class="value">{{ $address ? implode(', ', $address) : 'N/A' }}</span></td>
            <td><span class="label">DEVICE STATUS</span><span class="value">{{ $display($device['state'] ?? null) }}</span></td>
        </tr>
        <tr>
            <td><span class="label">LATITUDE</span><span class="value">{{ $display($device['latitude'] ?? null) }}</span></td>
            <td><span class="label">LONGITUDE</span><span class="value">{{ $display($device['longitude'] ?? null) }}</span></td>
            <td><span class="label">CURRENT CONTROL MODE</span><span class="value">{{ ($report['control_mode'] ?? 0) === 1 ? 'Remote control' : 'Automatic' }}</span></td>
        </tr>
    </table>
</div>

<div class="section range">
    <h2>Selected history period</h2>
    <p class="range-row"><span class="range-label">From</span> {{ $date($report['range']['from']) }}</p>
    <p class="range-row"><span class="range-label">To</span> {{ $date($report['range']['to']) }}</p>
    <p class="range-row small muted">{{ number_format(count($report['points'])) }} raw readings in this period. Both boundaries are included. Times are Pacific (PST/PDT).</p>
</div>

<div class="section latest-section">
    <h2>Latest device reading</h2>
    <p class="reading-time">{{ $latest ? 'Recorded '.$date($latest['recorded_at']) : 'No telemetry has been recorded for this device.' }}</p>
    <table class="details latest">
        @foreach (array_chunk($metrics, 4) as $row)
            <tr>
                @foreach ($row as [$label, $key, $unit])
                    @php($value = $latest[$key] ?? ($key === 'cts_state' ? ($latest['cts'] ?? null) : null))
                    <td><span class="label">{{ $label }}</span><span class="value">{{ $display($value) }}{{ $value !== null ? $unit : '' }}</span></td>
                @endforeach
            </tr>
        @endforeach
    </table>
    <p class="small muted" style="margin-top: 6pt;">The latest reading is shown independently of the selected history period.</p>
</div>

<div class="notes">
    <p>Graphs use the same latest {{ number_format($report['reading_limit']) }} available readings as History, filtered to the selected period. Older readings outside that available set are not included.</p>
    <p>Missing measurements are omitted from graphs and shown as N/A above. PS average is the recorded PS_average value; it is not recalculated from PS1 and PS2.</p>
</div>

@foreach ($chartPages as $page)
    <div class="graph-page">
        <div class="page-heading">
            <p class="eyebrow">HISTORY / ALL MEASUREMENTS</p>
            <h2 style="margin: 5pt 0 0;">{{ $display($device['name'] ?? $device['serial_no'] ?? null) }}</h2>
            <p class="subtitle">Serial {{ $display($device['serial_no'] ?? null) }} &nbsp; | &nbsp; {{ $date($report['range']['from']) }} - {{ $date($report['range']['to']) }}</p>
        </div>
        @foreach ($page as $chart)
            <div class="chart">
                <div class="chart-head">
                    <h3>{{ $chart['title'] }}{{ $chart['unit'] ? ' ('.$chart['unit'].')' : '' }}</h3>
                    <p class="chart-count">{{ number_format($chart['measurement_count']) }} valid {{ $chart['measurement_count'] === 1 ? 'measurement' : 'measurements' }} from {{ number_format($chart['reading_count']) }} raw readings</p>
                </div>
                <img class="chart-image" src="data:image/svg+xml;base64,{{ base64_encode($chart['svg']) }}" alt="{{ $chart['title'] }} history graph">
                <p class="chart-note">Dots: individual readings. Dashed lines: linear trend across valid readings; at least two distinct timestamps are required.</p>
            </div>
        @endforeach
        @if ($loop->last)
            <div class="notes">
                <h2 style="color: #172a43;">Reading this report</h2>
                <p>All five History measurement views are included, regardless of the measurement selected when the report was created. Panel sensors show PS1 and PS2 separately.</p>
                <p>Each trend is an ordinary least-squares fit over that measurement's valid readings in the selected period. Repeated timestamps count as separate samples. Trends stop at the first and last valid readings and are not projected beyond the recorded data.</p>
                <p>Times use America/Los_Angeles, including the applicable PST or PDT offset. Device details and the latest reading reflect the server snapshot at report creation.</p>
            </div>
        @endif
    </div>
@endforeach
</body>
</html>
