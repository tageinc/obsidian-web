<?php

namespace Tests\Unit;

use App\Services\DeviceHistoryReportCharts;
use App\Services\DeviceHistoryReportPdf;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DeviceHistoryReportPdfTest extends TestCase
{
    public function test_renderer_produces_a_pdf_for_missing_readings_and_cleans_its_temporary_files(): void
    {
        $cache = storage_path('framework/cache/device-history-pdf');
        $before = glob($cache.'/*', GLOB_ONLYDIR) ?: [];
        $bytes = app(DeviceHistoryReportPdf::class)->render($this->report());

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringContainsString('%%EOF', $bytes);
        $this->assertSame($before, glob($cache.'/*', GLOB_ONLYDIR) ?: []);
    }

    public function test_report_escapes_device_content_and_distinguishes_latest_from_selected_period(): void
    {
        $report = $this->report();
        $report['device']['name'] = '<img src="https://example.invalid/secret">';
        $report['device']['serial_no'] = '<script>unsafe()</script>';
        $report['control_mode'] = 1;
        $report['latest'] = [
            'recorded_at' => CarbonImmutable::parse('2026-11-01T10:30:00Z'),
            'temp' => 0, 'ps_avg' => 999, 'pds' => null,
        ];
        $charts = (new DeviceHistoryReportCharts)->build([], $report['range']['from']->valueOf(), $report['range']['to']->valueOf());
        $html = view('pdf.device-history', ['report' => $report, 'chartPages' => array_chunk($charts, 2)])->render();

        $this->assertStringNotContainsString('<img src="https://example.invalid', $html);
        $this->assertStringNotContainsString('<script>unsafe()', $html);
        $this->assertStringContainsString('&lt;script&gt;unsafe()', $html);
        $this->assertStringContainsString('Recorded Nov 1, 2026, 2:30:00 AM PST', $html);
        $this->assertStringContainsString('Nov 1, 2026, 1:30:00 AM PDT', $html);
        $this->assertStringContainsString('Nov 1, 2026, 1:30:00 AM PST', $html);
        $this->assertStringContainsString('>0 °C<', $html);
        $this->assertStringContainsString('>999<', $html);
        $this->assertStringContainsString('PDS</span><span class="value">N/A</span>', $html);
        $this->assertStringContainsString('CURRENT CONTROL MODE</span><span class="value">Remote control</span>', $html);
        $this->assertStringContainsString('PDT (-07:00)', $html);
        $this->assertStringContainsString('PST (-08:00)', $html);
    }

    private function report(): array
    {
        return [
            'device' => ['name' => 'Report test', 'serial_no' => 'SIMULATED-REPORT', 'sku' => 'SKU-1', 'order_no' => 'ORDER-1'],
            'timezone' => 'America/Los_Angeles',
            'generated_at' => CarbonImmutable::parse('2026-11-01T11:00:00Z'),
            'range' => [
                'from' => CarbonImmutable::parse('2026-11-01T08:30:00Z'),
                'to' => CarbonImmutable::parse('2026-11-01T09:30:00Z'),
            ],
            'points' => [],
            'reading_limit' => 9000,
            'latest' => null,
        ];
    }
}
