<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** Vector charts for the same raw readings and least-squares fits shown in History. */
class DeviceHistoryReportCharts
{
    private const VIEWS = [
        ['title' => 'Temperature', 'unit' => '°F', 'series' => [['temp', 'Temperature', '#2862c4']]],
        ['title' => 'Panel sensors', 'unit' => '', 'series' => [['ps1', 'PS1', '#2862c4'], ['ps2', 'PS2', '#138369']]],
        ['title' => 'PDS', 'unit' => '', 'zero_reference' => true, 'series' => [['pds', 'PDS', '#8954b9']]],
        ['title' => 'PS average', 'unit' => '', 'series' => [['ps_avg', 'PS average', '#138369']]],
        ['title' => 'Motor speed', 'unit' => '', 'zero_reference' => true, 'series' => [['motor_speed', 'Motor speed', '#ba681b']]],
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
                'zero_reference' => $view['zero_reference'] ?? false,
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
        [$minimum, $maximum, $ticks, $magnitude, $spacing] = $this->valueAxis($values, $chart['zero_reference']);
        $labels = array_map(fn ($value) => $this->number($value * $magnitude, $spacing * $magnitude), $ticks);
        $left = max($left, max(array_map(fn ($label) => $this->labelWidth($label, 11), $labels)) + 13);
        $plotWidth = $width - $left - $right;
        $x = static fn ($value) => $left + (($value - $from) / ($to - $from)) * $plotWidth;
        $y = static fn ($value) => $top + $plotHeight - (($value / $magnitude - $minimum) / ($maximum - $minimum)) * $plotHeight;
        $zeroY = $chart['zero_reference'] ? $y(0) : null;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="700" height="270" viewBox="0 0 700 270">';
        $svg .= '<rect width="700" height="270" fill="#ffffff"/>';
        foreach ($ticks as $index => $value) {
            $axisY = $top + $plotHeight - (($value - $minimum) / ($maximum - $minimum)) * $plotHeight;
            // Keep the dedicated zero label legible when a regular tick is nearby.
            if ($zeroY !== null && abs($axisY - $zeroY) < 14) {
                continue;
            }
            $svg .= $this->line($left, $axisY, $width - $right, $axisY, '#e2e8f0');
            $svg .= $this->text($left - 9, $axisY + 4, $labels[$index], 'end', 11, '#52627a');
        }
        $startDate = CarbonImmutable::createFromTimestampMs($from)->setTimezone(SolarTrackerGraphData::TIMEZONE);
        $endDate = CarbonImmutable::createFromTimestampMs($to)->setTimezone(SolarTrackerGraphData::TIMEZONE);
        $showDate = $startDate->format('Y-m-d T') !== $endDate->format('Y-m-d T');
        $timeTicks = $this->timeTicks($from, $to);
        $previousLabelEnd = -INF;
        foreach ($timeTicks as $timestamp) {
            $axisX = $x($timestamp);
            $date = CarbonImmutable::createFromTimestampMs($timestamp)->setTimezone(SolarTrackerGraphData::TIMEZONE);
            $dateLabel = $date->format($showDate ? 'M j, Y' : 'g:i A');
            $timeLabel = $showDate ? $date->format('g:i A T') : '';
            $halfLabel = max($this->labelWidth($dateLabel, 11), $this->labelWidth($timeLabel, 10)) / 2;
            $labelX = max(4 + $halfLabel, min($width - 4 - $halfLabel, $axisX));
            // Like History's automatic label skipping, retain clock alignment
            // while leaving enough room for dates at the report's printed size.
            if ($labelX - $halfLabel < $previousLabelEnd + 4) {
                continue;
            }
            $previousLabelEnd = $labelX + $halfLabel;
            $svg .= $this->line($axisX, $top, $axisX, $height - $bottom, '#eef2f7');
            $svg .= $this->text($labelX, $height - $bottom + 20, $dateLabel, 'middle', 11, '#52627a');
            if ($showDate) {
                $svg .= $this->text($labelX, $height - $bottom + 35, $timeLabel, 'middle', 10, '#52627a');
            }
        }
        $svg .= $this->line($left, $height - $bottom, $width - $right, $height - $bottom, '#b7c5d8');
        if ($zeroY !== null) {
            $svg .= $this->line($left, $zeroY, $width - $right, $zeroY, '#52627a', ' class="zero-reference" stroke-width="2"');
            $svg .= $this->text($left - 9, $zeroY + 4, '0', 'end', 11, '#52627a', ' font-weight="bold"');
        }
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

    private function valueAxis(array $values, bool $beginAtZero): array
    {
        // Keep normal ranges identical to History's Chart.js linear scale. Scale
        // extreme magnitudes before subtraction to avoid overflow or underflow.
        $largest = $values ? max(array_map('abs', $values)) : 0;
        $magnitude = $largest > 1e100 || ($largest > 0 && $largest < 1e-100)
            ? (10 ** floor(log10($largest)) ?: $largest) : 1;
        $minimum = $values ? min($values) / $magnitude : 0;
        $maximum = $values ? max($values) / $magnitude : 1;
        $finiteBound = PHP_FLOAT_MAX / $magnitude;
        if ($beginAtZero) {
            $minimum = min(0, $minimum);
            $maximum = max(0, $maximum);
        }
        if ($minimum === $maximum) {
            $offset = $maximum == 0 ? 1 : abs($maximum * 0.05);
            $maximum = min($finiteBound, $maximum + $offset);
            if (!$beginAtZero) {
                $minimum = max(-$finiteBound, $minimum - $offset);
            }
        }
        $maxSpaces = $beginAtZero ? 6 : 10;
        $spacing = ($maximum - $minimum) / $maxSpaces;
        // Chart.js keeps data limits instead of rounding sub-1e-14 spacing.
        if ($spacing * $magnitude < 1e-14) {
            $ticks = [$minimum, $maximum];
            $spacing = $maximum - $minimum;
        } else {
            $spacing = $this->niceNumber($spacing);
            $spaces = ceil($maximum / $spacing) - floor($minimum / $spacing);
            if ($spaces > $maxSpaces) {
                $spacing = $this->niceNumber($spaces * $spacing / $maxSpaces);
            }
            $first = floor($minimum / $spacing);
            $last = ceil($maximum / $spacing);
            $decimals = max(0, -(int) floor(log10($spacing)));
            $minimum = max(-$finiteBound, round($first * $spacing, $decimals));
            $maximum = min($finiteBound, round($last * $spacing, $decimals));
            $ticks = [$minimum];
            for ($index = 1; $index < $last - $first && $index <= $maxSpaces; $index++) {
                $value = round(($first + $index) * $spacing, $decimals);
                if ($value > $minimum && $value < $maximum) {
                    $ticks[] = $value;
                }
            }
            $ticks[] = $maximum;
        }
        if ($beginAtZero && !in_array(0, $ticks)) {
            $ticks[] = 0;
            sort($ticks, SORT_NUMERIC);
        }

        return [$minimum, $maximum, $ticks, $magnitude, $spacing];
    }

    private function niceNumber(float $range): float
    {
        $rounded = round($range);
        $range = abs($range - $rounded) < $range / 1000 ? $rounded : $range;
        $power = 10 ** floor(log10($range));
        $fraction = $range / $power;

        return ($fraction <= 1 ? 1 : ($fraction <= 2 ? 2 : ($fraction <= 5 ? 5 : 10))) * $power;
    }

    private function timeTicks(float $from, float $to): array
    {
        // Same epoch-aligned clock intervals as History's timeSeries.timeTicks.
        $target = ($to - $from) / 6;
        $step = ceil($target / 86400000) * 86400000;
        foreach ([1, 5, 15, 30, 60, 120, 240, 360, 720, 1440, 2880, 10080] as $minutes) {
            if ($minutes * 60000 >= $target) {
                $step = $minutes * 60000;
                break;
            }
        }
        $ticks = [];
        for ($timestamp = ceil($from / $step) * $step; $timestamp <= $to; $timestamp += $step) {
            $ticks[] = $timestamp;
        }

        return $ticks ?: [$from];
    }

    private function number(float $value, float $spacing): string
    {
        if ($value == 0) {
            return '0';
        }
        $stepExponent = $spacing > 0 && is_finite($spacing)
            ? (int) floor(log10($spacing)) : (int) floor(log10(abs($value))) - 2;
        if (abs($value) >= 1000000 || abs($value) < 0.001) {
            $digits = max(2, min(16, (int) floor(log10(abs($value))) - $stepExponent));

            return sprintf('%.*e', $digits, $value);
        }
        $decimals = max(0, min(20, -$stepExponent));
        $label = number_format($value, $decimals, '.', '');

        return $decimals ? rtrim(rtrim($label, '0'), '.') : $label;
    }

    private function coordinate(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function labelWidth(string $label, int $fontSize): float
    {
        // Conservative space for these numeric and English DejaVu Sans labels.
        return strlen($label) * $fontSize * 0.7;
    }

    private function line(float $x1, float $y1, float $x2, float $y2, string $color, string $attributes = ''): string
    {
        return '<line x1="'.$this->coordinate($x1).'" y1="'.$this->coordinate($y1).'" x2="'.$this->coordinate($x2).'" y2="'.$this->coordinate($y2).'" stroke="'.$color.'"'.$attributes.'/>';
    }

    private function text(float $x, float $y, string $value, string $anchor = 'start', int $size = 12, string $color = '#26354b', string $attributes = ''): string
    {
        return '<text x="'.$this->coordinate($x).'" y="'.$this->coordinate($y).'" text-anchor="'.$anchor.'" font-family="DejaVu Sans" font-size="'.$size.'" fill="'.$color.'"'.$attributes.'>'.htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8').'</text>';
    }
}
