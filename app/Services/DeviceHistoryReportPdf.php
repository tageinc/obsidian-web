<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class DeviceHistoryReportPdf
{
    public function __construct(private DeviceHistoryReportCharts $charts)
    {
    }

    public function render(array $data): string
    {
        // Each render owns its temporary resources; font caches stay outside the public tree.
        $cache = storage_path('framework/cache/device-history-pdf');
        File::ensureDirectoryExists($cache, 0700, true);
        $temporary = $cache.'/'.bin2hex(random_bytes(12));
        File::makeDirectory($temporary, 0700, true);

        try {
            $options = new Options([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
                'isFontSubsettingEnabled' => true,
                'fontCache' => $cache,
                'fontDir' => $cache,
                'tempDir' => $temporary,
                'chroot' => [$temporary],
                'allowedProtocols' => ['data://' => ['rules' => []]],
            ]);
            $pdf = new Dompdf($options);
            $charts = $this->charts->build(
                $data['points'],
                $data['range']['from']->valueOf(),
                $data['range']['to']->valueOf()
            );
            $html = view('pdf.device-history', [
                'report' => $data,
                'chartPages' => array_chunk($charts, 2),
            ])->render();
            unset($charts);
            $pdf->loadHtml($html, 'UTF-8');
            unset($html);
            $pdf->setPaper('letter', 'portrait');
            $pdf->render();
            $canvas = $pdf->getCanvas();
            $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
            $canvas->page_text(42, 762, 'OBSIDIAN  /  DEVICE HISTORY', $font, 7, [0.35, 0.41, 0.49]);
            $canvas->page_text(489, 762, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.35, 0.41, 0.49]);

            return $pdf->output();
        } finally {
            File::deleteDirectory($temporary);
        }
    }
}
