<?php

namespace App\Services;

use App\Models\ConfigVersions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConfigurationRelease
{
    public function snapshot(ConfigVersions $record): array
    {
        if ($record->device_family !== TrackerConfigurationSchema::FAMILY || $record->schema_version !== 1) {
            throw new HttpException(409, 'unsupported_configuration_schema');
        }
        foreach ([$record->prefix, (string) $record->version] as $identifier) {
            if (!is_string($identifier) || !preg_match('/^[A-Za-z0-9._-]{1,64}$/D', $identifier)) {
                throw new HttpException(409, 'incompatible_release_identity');
            }
        }
        if (!$record->file_path || !Storage::exists($record->file_path)) {
            throw new HttpException(404, 'missing_file');
        }
        // Read once: fingerprint and response always describe these exact bytes.
        try {
            $bytes = Storage::get($record->file_path);
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException | \League\Flysystem\FileNotFoundException $error) {
            throw new HttpException(404, 'missing_file');
        } catch (\Throwable $error) {
            throw new HttpException(503, 'storage_unavailable');
        }
        if (!is_string($bytes)) {
            throw new HttpException(404, 'missing_file');
        }
        try {
            $config = app(TrackerConfigurationSchema::class)->validate($bytes);
        } catch (ValidationException $error) {
            throw new HttpException(409, 'invalid_stored_configuration');
        }
        $hash = hash('sha256', $bytes);
        $identity = [(string) $record->id, $record->prefix, (string) $record->version, 1, $hash];

        return [
            'release_id' => hash('sha256', json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'prefix' => $record->prefix, 'version' => (string) $record->version,
            'schema_version' => 1, 'sha256' => $hash, 'config' => $config,
        ];
    }
}
