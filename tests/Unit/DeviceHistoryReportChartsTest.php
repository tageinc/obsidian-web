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
        $this->assertStringContainsString('1.16e+308', $charts[0]['svg']);
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
            $this->assertGreaterThan(36, $zeroY);
            $this->assertLessThan(204, $zeroY);
            $this->assertSame($line->getAttribute('y1'), $line->getAttribute('y2'));
            $this->assertSame('68.000', $line->getAttribute('x1'));
            $this->assertSame('675.000', $line->getAttribute('x2'));
            $this->assertSame('2', $line->getAttribute('stroke-width'));
            $this->assertSame('#52627a', $line->getAttribute('stroke'));

            $zeroLabels = $xpath->query('//svg:text[@x="59.000" and text()="0"]');
            $this->assertCount(1, $zeroLabels);
            $this->assertEqualsWithDelta($zeroY + 4, (float) $zeroLabels->item(0)->getAttribute('y'), 0.001);
            foreach ($xpath->query('//svg:text[@x="59.000" and text()!="0"]') as $label) {
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
}
