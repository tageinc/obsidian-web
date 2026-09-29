<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirmwareVersions;
use App\Models\ConfigVersions;
use App\Services\RedisWorkloads;
use App\Services\ConfigurationRelease;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeviceSoftwareController extends Controller
{
    public function getLatestConfigVersionNumber($prefix = null)
    {
        if (request()->query('protocol') === '2') {
            $record = ConfigVersions::where('prefix', $prefix)->orderByReleaseVersion()->orderByDesc('id')->first();
            if (!$record) {
                return response()->json(['error' => 'no_matching_version'], 404);
            }
            try {
                $snapshot = app(ConfigurationRelease::class)->snapshot($record);
                unset($snapshot['config']);
                $snapshot['download_path'] = 'api/config-file/release/'.$record->id.'?'.http_build_query([
                    'prefix' => $snapshot['prefix'], 'release_id' => $snapshot['release_id'],
                ]);
                return response()->json($snapshot)->header('Cache-Control', 'no-store');
            } catch (HttpException $error) {
                return response()->json(['error' => $error->getMessage()], $error->getStatusCode());
            }
        }
        return $this->version(ConfigVersions::class, 'config', $prefix);
    }

    public function serveConfigRelease(Request $request, $release)
    {
        $data = $request->validate([
            'prefix' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/D'],
            'release_id' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
        ]);
        $record = ConfigVersions::find($release);
        if (!$record) {
            return response()->json(['error' => 'missing_record'], 404);
        }
        try {
            $snapshot = app(ConfigurationRelease::class)->snapshot($record);
            if ($data['prefix'] !== $snapshot['prefix'] || !hash_equals($snapshot['release_id'], $data['release_id'])) {
                return response()->json(['error' => 'identity_mismatch'], 409);
            }
            return response()->json($snapshot)->header('Cache-Control', 'no-store');
        } catch (HttpException $error) {
            return response()->json(['error' => $error->getMessage()], $error->getStatusCode());
        }
    }

    public function getLatestFirmwareVersionNumber($prefix = null)
    {
        return $this->version(FirmwareVersions::class, 'firmware', $prefix);
    }

    private function version($model, $kind, $prefix)
    {
        $version = app(RedisWorkloads::class)->version($kind, $prefix, function () use ($model, $prefix) {
            $query = $model::where('prefix', $prefix);
            $record = $query->orderByReleaseVersion()->first();

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
            $query = $model::where('prefix', $value);
            $record = $query->orderByReleaseVersion()->first();
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
