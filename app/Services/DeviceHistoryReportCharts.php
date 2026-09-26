<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Vector charts for the same raw readings and least-squares fits shown in History. */
class DeviceHistoryReportCharts
{
    private const VIEWS = [
        ['title' => 'Temperature', 'unit' => '°F', 'series' => [['temp', 'Temperature', '#2862c4']]],
        ['title' => 'Panel sensors', 'unit' => '', 'series' => [['ps1', 'PS1', '#2862c4'], ['ps2', 'PS2', '#138369']]],
        ['title' => 'PDS', 'unit' => '', 'series' => [['pds', 'PDS', '#8954b9']]],
        ['title' => 'PS average', 'unit' => '', 'series' => [['ps_avg', 'PS average', '#138369']]],
        ['title' => 'Motor speed', 'unit' => '', 'series' => [['motor_speed', 'Motor speed', '#ba681b']]],
    ];

    public function build(array $points, float $from, float $to): array
    {
        $points = array_values(array_filter($points, static function (array $point) use ($from, $to) {
            return is_numeric($point['epoch_ms'] ?? null) && is_finite((float) $point['epoch_ms'])
                && $point['epoch_ms'] >= $from && $point['epoch_ms'] <= $to;
        }));

        return array_map(function (array $view) use ($points, $from, $to) {
            $series = array_map(function (array $definition) use ($points) {
                [$field, $label, $color] = $definition;
                $readings = [];
                foreach ($points as $point) {
                    $value = $point[$field] ?? null;
                    if ($field === 'temp') {
                        $value = array_key_exists('temp_f', $point)
                            ? $point['temp_f'] : DeviceTelemetryValues::celsiusToFahrenheit($value);
                    }
                    if (is_numeric($value) && is_finite((float) $value)) {
                        $readings[] = ['x' => (float) $point['epoch_ms'], 'y' => (float) $value];
                    }
                }

                return [
                    'field' => $field, 'label' => $label, 'color' => $color,
                    'points' => $readings, 'trend' => $this->linearRegression($readings),
                ];
            }, $view['series']);
            $count = array_sum(array_map(static fn (array $entry) => count($entry['points']), $series));
            $chart = [
                'title' => $view['title'], 'unit' => $view['unit'], 'series' => $series,
                'reading_count' => count($points), 'measurement_count' => $count,
                'from' => $from, 'to' => $to,
            ];
            $chart['svg'] = $this->svg($chart);

            return $chart;
        }, self::VIEWS);
    }

    public function linearRegression(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }
        $first = min(array_column($points, 'x'));
        $last = max(array_column($points, 'x'));
        if ($first === $last) {
            return [];
        }
        // Center elapsed hours rather than epoch values to preserve numerical precision.
        // Duplicate timestamps remain independent samples, as in the interactive graph.
        $meanX = 0.0;
        $meanY = 0.0;
        $count = count($points);
        foreach ($points as $point) {
            $meanX += (($point['x'] - $first) / 3600000) / $count;
            $meanY += $point['y'] / $count;
        }
        $covariance = 0.0;
        $variance = 0.0;
        foreach ($points as $point) {
            $centeredX = ($point['x'] - $first) / 3600000 - $meanX;
            $covariance += $centeredX * ($point['y'] - $meanY);
            $variance += $centeredX * $centeredX;
        }
        if (!($variance > 0)) {
            return [];
        }
        $slope = $covariance / $variance;
        $result = array_map(static fn ($x) => [
            'x' => $x, 'y' => $meanY + $slope * (($x - $first) / 3600000 - $meanX),
        ], [$first, $last]);

        return is_finite($result[0]['y']) && is_finite($result[1]['y']) ? $result : [];
    }

    private function svg(array $chart): string
    {
        $width = 700;
        $height = 270;
        $left = 68;
        $right = 25;
        $top = 36;
        $bottom = 66;
        $plotWidth = $width - $left - $right;
        $plotHeight = $height - $top - $bottom;
        $from = $chart['from'];
        $to = $chart['to'];
        if ($from === $to) {
            $from -= 1800000;
            $to += 1800000;
        }
        $values = [];
        foreach ($chart['series'] as $series) {
            foreach (array_merge($series['points'], $series['trend']) as $point) {
                $values[] = $point['y'];
            }
        }
        // Scale before subtracting to keep finite extremes (for example -1e308
        // and +1e308) from overflowing the axis span or SVG coordinates.
        $magnitude = $values ? max(1, max(array_map('abs', $values))) : 1;
        $minimum = $values ? min($values) / $magnitude : 0;
        $maximum = $values ? max($values) / $magnitude : 1;
        $padding = $minimum === $maximum ? max(abs($minimum) * 0.1, 1 / $magnitude) : ($maximum - $minimum) * 0.08;
        $finiteBound = PHP_FLOAT_MAX / $magnitude;
        $minimum = max(-$finiteBound, $minimum - $padding);
        $maximum = min($finiteBound, $maximum + $padding);
        $x = static fn ($value) => $left + (($value - $from) / ($to - $from)) * $plotWidth;
        $y = static fn ($value) => $top + $plotHeight - (($value / $magnitude - $minimum) / ($maximum - $minimum)) * $plotHeight;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="700" height="270" viewBox="0 0 700 270">';
        $svg .= '<rect width="700" height="270" fill="#ffffff"/>';
        for ($index = 0; $index <= 4; $index++) {
            $value = $minimum + ($maximum - $minimum) * $index / 4;
            $axisY = $top + $plotHeight - (($value - $minimum) / ($maximum - $minimum)) * $plotHeight;
            $svg .= $this->line($left, $axisY, $width - $right, $axisY, '#e2e8f0');
            $labelValue = $value * $magnitude;
            $label = is_finite($labelValue) ? $this->number($labelValue) : ($value < 0 ? '-' : '').sprintf('%.2e', PHP_FLOAT_MAX);
            $svg .= $this->text($left - 9, $axisY + 4, $label, 'end', 11, '#52627a');
        }
        $startDate = CarbonImmutable::createFromTimestampMs($from)->setTimezone(SolarTrackerGraphData::TIMEZONE);
        $endDate = CarbonImmutable::createFromTimestampMs($to)->setTimezone(SolarTrackerGraphData::TIMEZONE);
        $showDate = $startDate->format('Y-m-d T') !== $endDate->format('Y-m-d T');
        $tickCount = $showDate ? 4 : 5;
        for ($index = 0; $index <= $tickCount; $index++) {
            $timestamp = $from + ($to - $from) * $index / $tickCount;
            $axisX = $x($timestamp);
            $date = CarbonImmutable::createFromTimestampMs($timestamp)->setTimezone(SolarTrackerGraphData::TIMEZONE);
            $anchor = $index === 0 ? 'start' : ($index === $tickCount ? 'end' : 'middle');
            $svg .= $this->line($axisX, $top, $axisX, $height - $bottom, '#eef2f7');
            $svg .= $this->text($axisX, $height - $bottom + 20, $date->format($showDate ? 'M j, Y' : 'g:i A'), $anchor, 11, '#52627a');
            if ($showDate) {
                $svg .= $this->text($axisX, $height - $bottom + 35, $date->format('g:i A T'), $anchor, 10, '#52627a');
            }
        }
        $svg .= $this->line($left, $height - $bottom, $width - $right, $height - $bottom, '#b7c5d8');
        $svg .= $this->text($width / 2, $height - 9, 'Recorded time (Pacific Time)', 'middle', 11, '#52627a');
        $legendX = $left;
        foreach ($chart['series'] as $series) {
            $svg .= '<circle cx="'.$legendX.'" cy="14" r="3" fill="'.$series['color'].'"/>';
            $svg .= $this->text($legendX + 9, 18, $series['label'].' ('.count($series['points']).')', 'start', 11, '#26354b');
            $legendX += 185;
            if ($series['trend']) {
                [$start, $end] = $series['trend'];
                $svg .= $this->line($x($start['x']), $y($start['y']), $x($end['x']), $y($end['y']), $series['color'], ' stroke-width="1.8" stroke-dasharray="6 4"');
            }
            // A single path keeps dense reports efficient without aggregating or dropping readings.
            $path = '';
            foreach ($series['points'] as $point) {
                $path .= 'M'.$this->coordinate($x($point['x'])).' '.$this->coordinate($y($point['y'])).'l0.01 0 ';
            }
            if ($path !== '') {
                $svg .= '<path d="'.$path.'" fill="none" stroke="'.$series['color'].'" stroke-width="3" stroke-linecap="round"/>';
            }
        }
        if (!$values) {
            $svg .= '<rect x="150" y="103" width="430" height="32" fill="#ffffff"/>';
            $svg .= $this->text($width / 2, 124, 'No valid measurements in this time range.', 'middle', 13, '#52627a');
        }

        return $svg.'</svg>';
    }

    private function number(float $value): string
    {
        if ($value != 0 && (abs($value) >= 1000000 || abs($value) < 0.001)) {
            return sprintf('%.2e', $value);
        }

        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function coordinate(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color, string $attributes = ''): string
    {
        return '<line x1="'.$this->coordinate($x1).'" y1="'.$this->coordinate($y1).'" x2="'.$this->coordinate($x2).'" y2="'.$this->coordinate($y2).'" stroke="'.$color.'"'.$attributes.'/>';
    }

    private function text(float $x, float $y, string $value, string $anchor = 'start', int $size = 12, string $color = '#26354b'): string
    {
        return '<text x="'.$this->coordinate($x).'" y="'.$this->coordinate($y).'" text-anchor="'.$anchor.'" font-family="DejaVu Sans" font-size="'.$size.'" fill="'.$color.'">'.htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8').'</text>';
    }
}
