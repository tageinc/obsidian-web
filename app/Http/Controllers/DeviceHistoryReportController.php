<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Services\DeviceHistoryReportData;
use App\Services\DeviceHistoryReportPdf;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceHistoryReportController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $device = Device::findOrFail($id);
        $user = $request->user();
        abort_unless($user && (
            (int) $user->id === (int) $device->user_id || $user->isDeveloper()
        ), 403);

        $generatedAt = CarbonImmutable::now('UTC');
        if ($request->query->has('from') || $request->query->has('to')) {
            $isoTimestamp = function ($attribute, $value, $fail) {
                if ($this->parseTimestamp($value) === null) {
                    $fail('The '.$attribute.' must be an ISO 8601 timestamp with an explicit timezone and at most millisecond precision.');
                }
            };
            $input = $request->validate([
                'from' => ['bail', 'required', 'string', 'max:40', $isoTimestamp],
                'to' => ['bail', 'required', 'string', 'max:40', $isoTimestamp],
            ]);
            $from = $this->parseTimestamp($input['from']);
            $to = $this->parseTimestamp($input['to']);
            if ($from->greaterThan($to)) {
                throw ValidationException::withMessages(['to' => 'The to timestamp must be on or after from.']);
            }
        } else {
            $to = $generatedAt->startOfSecond();
            $from = $to->subHours(24);
        }

        $data = app(DeviceHistoryReportData::class)->forDevice($device, $from, $to, $generatedAt);
        $pdf = app(DeviceHistoryReportPdf::class)->render($data);
        $serial = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $device->serial_no), '-');
        $filename = 'device-history-'.substr($serial ?: (string) $device->id, 0, 80)
            .'-'.$generatedAt->format('Ymd-His').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function parseTimestamp(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d{1,3})?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D', $value)) {
            return null;
        }
        try {
            $date = new DateTimeImmutable($value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
                return null;
            }
            return CarbonImmutable::instance($date);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
