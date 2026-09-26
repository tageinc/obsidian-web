<?php

namespace Tests\Unit;

use App\Services\DeviceHistoryReportCharts;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DeviceHistoryReportChartsTest extends TestCase
{
    public function test_all_measurements_preserve_zero_and_recorded_average_and_omit_nulls(): void
    {
        $charts = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp' => 0, 'ps1' => 10, 'ps2' => 20, 'ps_avg' => 999, 'pds' => -3, 'motor_speed' => 0],
            ['epoch_ms' => 2000, 'temp' => null, 'ps1' => null, 'ps2' => 30, 'ps_avg' => null, 'pds' => 0],
        ], 1000, 2000);

        $this->assertSame(['Temperature', 'Panel sensors', 'PDS', 'PS average', 'Motor speed'], array_column($charts, 'title'));
        $this->assertSame('°F', $charts[0]['unit']);
        $this->assertSame([32.0], array_column($charts[0]['series'][0]['points'], 'y'));
        $this->assertSame([10.0], array_column($charts[1]['series'][0]['points'], 'y'));
        $this->assertSame([20.0, 30.0], array_column($charts[1]['series'][1]['points'], 'y'));
        $this->assertSame([-3.0, 0.0], array_column($charts[2]['series'][0]['points'], 'y'));
        $this->assertSame([999.0], array_column($charts[3]['series'][0]['points'], 'y'));
        $this->assertSame([0.0], array_column($charts[4]['series'][0]['points'], 'y'));
        $this->assertSame(3, $charts[1]['measurement_count']);
        $this->assertSame(2, $charts[1]['reading_count']);
    }

    public function test_filter_boundaries_are_inclusive_and_trends_do_not_extend_past_valid_readings(): void
    {
        $points = [
            ['epoch_ms' => 100, 'temp' => 900],
            ['epoch_ms' => 200, 'temp' => null],
            ['epoch_ms' => 300, 'temp' => 10],
            ['epoch_ms' => 400, 'temp' => 20],
            ['epoch_ms' => 500, 'temp' => null],
            ['epoch_ms' => 600, 'temp' => 900],
        ];
        $chart = (new DeviceHistoryReportCharts)->build($points, 200, 500)[0];

        $this->assertSame(4, $chart['reading_count']);
        $this->assertSame([300.0, 400.0], array_column($chart['series'][0]['points'], 'x'));
        $this->assertSame([300.0, 400.0], array_column($chart['series'][0]['trend'], 'x'));
        $this->assertEqualsWithDelta(50, $chart['series'][0]['trend'][0]['y'], 0.00001);
        $this->assertEqualsWithDelta(68, $chart['series'][0]['trend'][1]['y'], 0.00001);
        $this->assertSame(200.0, $chart['from']);
        $this->assertSame(500.0, $chart['to']);
    }

    public function test_regression_weights_duplicate_timestamps_independently_at_large_epochs(): void
    {
        $first = (float) CarbonImmutable::parse('2026-09-25T00:00:00Z')->valueOf();
        $fit = (new DeviceHistoryReportCharts)->linearRegression([
            ['x' => $first, 'y' => 0],
            ['x' => $first, 'y' => 6],
            ['x' => $first + 3600000, 'y' => 6],
            ['x' => $first + 7200000, 'y' => 8],
        ]);

        $this->assertCount(2, $fit);
        // Ordinary least squares over four samples, not three timestamp averages.
        $this->assertEqualsWithDelta(34 / 11, $fit[0]['y'], 0.00000001);
        $this->assertEqualsWithDelta(90 / 11, $fit[1]['y'], 0.00000001);
    }

    public function test_single_instant_has_raw_dots_but_no_trend_and_empty_measurements_are_explicit(): void
    {
        $charts = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp' => 5],
            ['epoch_ms' => 1000, 'temp' => 6],
        ], 1000, 1000);

        $this->assertSame([], $charts[0]['series'][0]['trend']);
        $this->assertSame(2, $charts[0]['measurement_count']);
        $this->assertStringContainsString('stroke-linecap="round"', $charts[0]['svg']);
        $this->assertStringNotContainsString('No valid measurements', $charts[0]['svg']);
        $this->assertStringContainsString('No valid measurements', $charts[1]['svg']);
        $this->assertStringNotContainsString('NaN', $charts[0]['svg']);
        $this->assertStringNotContainsString('INF', $charts[0]['svg']);
    }

    public function test_fall_back_axis_disambiguates_both_pacific_offsets(): void
    {
        $from = CarbonImmutable::parse('2026-11-01T08:30:00Z')->valueOf();
        $to = CarbonImmutable::parse('2026-11-01T09:30:00Z')->valueOf();
        $chart = (new DeviceHistoryReportCharts)->build([], $from, $to)[0];

        $this->assertStringContainsString('1:30 AM PDT', $chart['svg']);
        $this->assertStringContainsString('1:30 AM PST', $chart['svg']);
        $this->assertStringContainsString('Nov 1, 2026', $chart['svg']);
    }

    public function test_dense_graph_keeps_every_raw_sample_without_aggregation(): void
    {
        $points = [];
        for ($index = 0; $index < 9000; $index++) {
            $points[] = ['epoch_ms' => 1000 + $index, 'temp' => $index % 4];
        }
        $chart = (new DeviceHistoryReportCharts)->build($points, 1000, 9999)[0];

        $this->assertCount(9000, $chart['series'][0]['points']);
        $this->assertSame(9000, substr_count($chart['svg'], 'l0.01 0 '));
    }

    public function test_extreme_finite_measurements_keep_axis_coordinates_finite(): void
    {
        $charts = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp_f' => -1e308, 'ps1' => -PHP_FLOAT_MAX, 'ps2' => PHP_FLOAT_MAX],
            ['epoch_ms' => 2000, 'temp_f' => 1e308, 'ps1' => PHP_FLOAT_MAX, 'ps2' => PHP_FLOAT_MAX],
        ], 1000, 2000);

        foreach ($charts as $chart) {
            $this->assertDoesNotMatchRegularExpression('/(?:nan|inf)/i', $chart['svg']);
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($chart['svg']));
        }
        $this->assertSame(2, $charts[0]['measurement_count']);
        foreach ([0, 1] as $index) {
            $labels = $this->valueLabels($charts[$index]['svg']);
            $this->assertLessThanOrEqual(min(array_column($charts[$index]['series'][0]['points'], 'y')), min($labels));
            $this->assertGreaterThanOrEqual(max(array_column($charts[$index]['series'][0]['points'], 'y')), max($labels));
            preg_match_all('/M[\d.-]+ ([\d.-]+)l0\.01 0/', $charts[$index]['svg'], $coordinates);
            foreach ($coordinates[1] as $coordinate) {
                $this->assertGreaterThanOrEqual(36, (float) $coordinate);
                $this->assertLessThanOrEqual(204, (float) $coordinate);
            }
        }
    }

    public function test_fahrenheit_payloads_are_not_converted_twice_and_invalid_values_stay_missing(): void
    {
        $chart = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp' => 26.1135, 'temp_f' => 79.0043],
            ['epoch_ms' => 2000, 'temp' => 0, 'temp_f' => null],
            ['epoch_ms' => 3000, 'temp' => 'invalid'],
        ], 1000, 3000)[0];

        $this->assertSame([79.0043], array_column($chart['series'][0]['points'], 'y'));
        $this->assertSame(1, $chart['measurement_count']);
    }

    /** @dataProvider zeroReferenceReadings */
    public function test_signed_sensor_charts_show_a_labeled_zero_reference_without_losing_readings(array $values): void
    {
        $points = array_map(static fn ($value, $index) => [
            'epoch_ms' => 1000 + $index * 1000,
            'pds' => $value,
            'motor_speed' => $value,
            'ps_avg' => $value,
        ], $values, array_keys($values));
        $charts = (new DeviceHistoryReportCharts)->build($points, 1000, 1000 * count($values));
        $expectedValues = array_values(array_map('floatval', array_filter($values, static fn ($value) => is_numeric($value) && is_finite((float) $value))));

        foreach ([2, 4] as $index) {
            $chart = $charts[$index];
            $this->assertSame($expectedValues, array_column($chart['series'][0]['points'], 'y'));
            $this->assertSame(count($expectedValues), $chart['measurement_count']);
            $this->assertDoesNotMatchRegularExpression('/(?:nan|inf)/i', $chart['svg']);
            $document = new \DOMDocument;
            $this->assertTrue($document->loadXML($chart['svg']));
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
            $lines = $xpath->query('//svg:line[@class="zero-reference"]');
            $this->assertCount(1, $lines);
            $line = $lines->item(0);
            $zeroY = (float) $line->getAttribute('y1');
            $this->assertGreaterThanOrEqual(36, $zeroY);
            $this->assertLessThanOrEqual(204, $zeroY);
            $this->assertSame($line->getAttribute('y1'), $line->getAttribute('y2'));
            $this->assertGreaterThanOrEqual(68, (float) $line->getAttribute('x1'));
            $this->assertSame('675.000', $line->getAttribute('x2'));
            $this->assertSame('2', $line->getAttribute('stroke-width'));
            $this->assertSame('#52627a', $line->getAttribute('stroke'));

            $zeroLabels = $xpath->query('//svg:text[@text-anchor="end" and text()="0"]');
            $this->assertCount(1, $zeroLabels);
            $labelX = $zeroLabels->item(0)->getAttribute('x');
            $this->assertEqualsWithDelta((float) $line->getAttribute('x1') - 9, (float) $labelX, 0.001);
            $this->assertEqualsWithDelta($zeroY + 4, (float) $zeroLabels->item(0)->getAttribute('y'), 0.001);
            foreach ($xpath->query('//svg:text[@x="'.$labelX.'" and text()!="0"]') as $label) {
                $this->assertGreaterThanOrEqual(13.999, abs((float) $label->getAttribute('y') - ($zeroY + 4)));
            }

            preg_match_all('/M[\d.-]+ ([\d.-]+)l0\.01 0/', $chart['svg'], $coordinates);
            $this->assertCount(count($expectedValues), $coordinates[1]);
            foreach ($expectedValues as $pointIndex => $value) {
                $pointY = (float) $coordinates[1][$pointIndex];
                if ($value > 0) {
                    $this->assertLessThan($zeroY, $pointY);
                } elseif ($value < 0) {
                    $this->assertGreaterThan($zeroY, $pointY);
                } else {
                    $this->assertEqualsWithDelta($zeroY, $pointY, 0.001);
                }
            }
            if (!$expectedValues) {
                $this->assertStringContainsString('No valid measurements', $chart['svg']);
            }
        }

        foreach ([0, 1, 3] as $index) {
            $this->assertStringNotContainsString('class="zero-reference"', $charts[$index]['svg']);
        }
    }

    public static function zeroReferenceReadings(): array
    {
        return [
            'crossing zero between ordinary ticks' => [[-10, 25]],
            'positive only' => [[6, 12]],
            'negative only' => [[-12, -6]],
            'all zero' => [[0, 0]],
            'missing values' => [[null, 'missing', INF, NAN]],
            'opposing finite extremes' => [[-PHP_FLOAT_MAX, PHP_FLOAT_MAX]],
            'positive finite extremes' => [[PHP_FLOAT_MAX / 2, PHP_FLOAT_MAX]],
            'negative finite extremes' => [[-PHP_FLOAT_MAX, -PHP_FLOAT_MAX / 2]],
            'tiny finite readings' => [[-1e-305, 2e-305]],
        ];
    }

    /** @dataProvider historyValueTicks */
    public function test_all_report_metrics_follow_history_numeric_tick_rules(array $values, array $ordinary, array $signed): void
    {
        $points = array_map(static fn ($value, $index) => [
            'epoch_ms' => 1000 + $index * 1000,
            'temp_f' => $value, 'ps1' => $value, 'ps_avg' => $value,
            'pds' => $value, 'motor_speed' => $value,
        ], $values, array_keys($values));
        $charts = (new DeviceHistoryReportCharts)->build($points, 1000, max(1000, count($values) * 1000));

        foreach ($charts as $index => $chart) {
            // Golden tick values from the installed Chart.js LinearScale, with
            // History's ordinary 11-tick and signed-sensor 7-tick limits.
            $this->assertEquals(in_array($index, [2, 4]) ? $signed : $ordinary, $this->valueLabels($chart['svg']), $chart['title']);
        }
    }

    public static function historyValueTicks(): array
    {
        return [
            'positive' => [[12, 24], [12, 14, 16, 18, 20, 22, 24], [0, 5, 10, 15, 20, 25]],
            'negative' => [[-24, -12], [-24, -22, -20, -18, -16, -14, -12], [-25, -20, -15, -10, -5, 0]],
            'crossing' => [[-3, 2], [-3, -2.5, -2, -1.5, -1, -0.5, 0, 0.5, 1, 1.5, 2], [-3, -2, -1, 0, 1, 2]],
            'constant' => [[42, 42], [39.5, 40, 40.5, 41, 41.5, 42, 42.5, 43, 43.5, 44, 44.5], [0, 10, 20, 30, 40, 50]],
            'zero' => [[0, 0], [-1, -0.8, -0.6, -0.4, -0.2, 0, 0.2, 0.4, 0.6, 0.8, 1], [0, 0.2, 0.4, 0.6, 0.8, 1]],
            'fractional' => [[79.0043, 79.121], [79, 79.02, 79.04, 79.06, 79.08, 79.1, 79.12, 79.14], [0, 20, 40, 60, 80]],
            'small fractional' => [[0.0012, 0.0024], [0.001, 0.0012, 0.0014, 0.0016, 0.0018, 0.002, 0.0022, 0.0024], [0, 0.0005, 0.001, 0.0015, 0.002, 0.0025]],
            'tiny' => [[1e-16, 2e-16], [1e-16, 2e-16], [0, 2e-16]],
            'empty' => [[], [0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 1], [0, 0.2, 0.4, 0.6, 0.8, 1]],
        ];
    }

    public function test_time_ticks_are_clock_aligned_within_the_selected_range(): void
    {
        $from = CarbonImmutable::parse('2026-01-01T15:45:00Z')->valueOf();
        $to = $from + 12 * 3600000;
        $charts = (new DeviceHistoryReportCharts)->build([], $from, $to);
        $expected = ['8:00 AM', '10:00 AM', '12:00 PM', '2:00 PM', '4:00 PM', '6:00 PM'];
        foreach ($charts as $chart) {
            $xpath = $this->svgPath($chart['svg']);
            $labels = $xpath->query('//svg:text[@y="224.000"]');
            $this->assertSame($expected, array_map(static fn ($label) => $label->textContent, iterator_to_array($labels)));
            $lines = $xpath->query('//svg:line[@stroke="#eef2f7"]');
            foreach ($lines as $index => $line) {
                $timestamp = $from + (((float) $line->getAttribute('x1') - 68) / 607) * ($to - $from);
                $this->assertEqualsWithDelta(ceil($from / 7200000) * 7200000 + $index * 7200000, $timestamp, 40);
                $this->assertGreaterThanOrEqual($from, $timestamp);
                $this->assertLessThanOrEqual($to, $timestamp);
            }
        }
    }

    public function test_short_and_single_instant_ranges_keep_history_time_tick_fallbacks(): void
    {
        $instant = CarbonImmutable::parse('2026-01-01T15:45:30Z')->valueOf();
        $short = (new DeviceHistoryReportCharts)->build([], $instant, $instant + 1000)[0];
        $this->assertCount(1, $this->svgPath($short['svg'])->query('//svg:line[@stroke="#eef2f7"]'));
        $single = (new DeviceHistoryReportCharts)->build([['epoch_ms' => $instant, 'temp_f' => 42]], $instant, $instant)[0];
        $labels = $this->svgPath($single['svg'])->query('//svg:text[@y="224.000"]');
        $this->assertSame(['7:30 AM', '7:45 AM', '8:00 AM', '8:15 AM'], array_map(static fn ($label) => $label->textContent, iterator_to_array($labels)));
    }

    public function test_nearby_tiny_measurements_keep_distinct_tick_labels(): void
    {
        $values = [1e-20, 1.00000001e-20];
        $chart = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp_f' => $values[0]],
            ['epoch_ms' => 2000, 'temp_f' => $values[1]],
        ], 1000, 2000)[0];

        $this->assertSame($values, $this->valueLabels($chart['svg']));
    }

    public function test_subnormal_measurements_do_not_produce_invalid_svg_coordinates(): void
    {
        $charts = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => 1000, 'temp_f' => 5e-324, 'pds' => -5e-324],
            ['epoch_ms' => 2000, 'temp_f' => 1e-323, 'pds' => 1e-323],
        ], 1000, 2000);

        foreach ([0, 2] as $index) {
            $this->assertDoesNotMatchRegularExpression('/(?:nan|inf)/i', $charts[$index]['svg']);
            $this->assertCount(2, $charts[$index]['series'][0]['points']);
            $this->svgPath($charts[$index]['svg']);
        }
    }

    public function test_long_numeric_labels_fit_the_report_without_crowding_date_labels(): void
    {
        $from = CarbonImmutable::parse('2026-11-12T08:00:00Z')->valueOf();
        $to = $from + 86400000;
        $charts = (new DeviceHistoryReportCharts)->build([
            ['epoch_ms' => $from, 'temp_f' => -1e-20, 'pds' => -PHP_FLOAT_MAX, 'ps_avg' => 12],
            ['epoch_ms' => $to, 'temp_f' => -1.00000001e-20, 'pds' => PHP_FLOAT_MAX, 'ps_avg' => 24],
        ], $from, $to);

        foreach ([0, 2, 3] as $index) {
            $xpath = $this->svgPath($charts[$index]['svg']);
            foreach ($xpath->query('//svg:text[@text-anchor="end"]') as $label) {
                $left = (float) $label->getAttribute('x') - strlen($label->textContent) * 11 * 0.7;
                $this->assertGreaterThanOrEqual(3.999, $left);
                if ($index === 3) {
                    $this->assertSame('59.000', $label->getAttribute('x'));
                }
            }
            foreach ([224, 239] as $row) {
                $labels = $xpath->query('//svg:text[@y="'.$row.'.000"]');
                $this->assertGreaterThanOrEqual(4, $labels->length);
                $previousEnd = 0;
                foreach ($labels as $label) {
                    $halfWidth = strlen($label->textContent) * (int) $label->getAttribute('font-size') * 0.7 / 2;
                    $center = (float) $label->getAttribute('x');
                    $this->assertSame('middle', $label->getAttribute('text-anchor'));
                    $this->assertGreaterThanOrEqual($previousEnd + 3.999, $center - $halfWidth);
                    $this->assertLessThanOrEqual(696.001, $center + $halfWidth);
                    $previousEnd = $center + $halfWidth;
                }
            }
        }
    }

    private function valueLabels(string $svg): array
    {
        $labels = $this->svgPath($svg)->query('//svg:text[@text-anchor="end"]');
        $values = array_map(static fn ($label) => (float) $label->textContent, iterator_to_array($labels));
        sort($values, SORT_NUMERIC);

        return $values;
    }

    private function svgPath(string $svg): \DOMXPath
    {
        $document = new \DOMDocument;
        $this->assertTrue($document->loadXML($svg));
        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

        return $xpath;
    }
}
