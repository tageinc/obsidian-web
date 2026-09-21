<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use App\Services\RedisWorkloads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeviceSoftwareController extends Controller
{
    public function getLatestConfigVersionNumber($prefix = null)
    {
        return $this->version(ConfigVersions::class, 'config', $prefix);
    }

    public function getLatestFirmwareVersionNumber($prefix = null)
    {
        return $this->version(FirmwareVersions::class, 'firmware', $prefix);
    }

    private function version($model, $kind, $prefix)
    {
        $version = app(RedisWorkloads::class)->version($kind, $prefix, function () use ($model, $prefix) {
            $record = $model::where('prefix', $prefix)->orderBy('version', 'desc')->first();

            return $record ? (string) $record->version : null;
        });
        if ($version === null) {
            $this->missing($kind, 'prefix', $prefix);
            // Keep the legacy string field, but never report a missing deployment as success.
            return response()->json(['version' => '0', 'error' => 'no_matching_version'], 404);
        }

        return response()->json(['version' => $version]);
    }

    public function serveFirmwareByVersion($version = null)
    {
        return $this->download(FirmwareVersions::class, 'firmware', 'version', $version);
    }

    public function serveConfigByVersion($version = null)
    {
        return $this->download(ConfigVersions::class, 'config', 'version', $version);
    }

    public function serveFirmwareByPrefix($prefix = null)
    {
        return $this->download(FirmwareVersions::class, 'firmware', 'prefix', $prefix);
    }

    public function serveConfigByPrefix($prefix = null)
    {
        return $this->download(ConfigVersions::class, 'config', 'prefix', $prefix);
    }

    private function download($model, $kind, $selector, $value)
    {
        $record = null;
        if ($selector === 'version') {
            $record = $value === null ? $model::latest()->first() : $model::where('version', $value)->first();
        } elseif ($value !== null) {
            $record = $model::where('prefix', $value)->orderBy('version', 'desc')->first();
        }

        if (!$record || !$record->file_path || !Storage::exists($record->file_path)) {
            $this->missing($kind, $selector, $value, $record ? 'missing_file' : 'missing_record');
            abort(404, $kind === 'firmware' ? 'Firmware file not found.' : 'Configuration file not found.');
        }

        return Storage::download($record->file_path);
    }

    private function missing($kind, $selector, $value, $reason = 'no_matching_version')
    {
        Log::warning('device.software_unavailable', [
            'kind' => $kind, 'selector' => $selector, 'reason' => $reason,
            'selector_hash' => $value === null ? null : hash('sha256', (string) $value),
        ]);
    }
}
