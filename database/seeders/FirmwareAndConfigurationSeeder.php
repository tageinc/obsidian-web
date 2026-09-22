<?php

namespace Database\Seeders;

use App\Models\ConfigVersions;
use App\Models\FirmwareVersions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Downloadable development fixtures; these are not device deployment artifacts. */
class FirmwareAndConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('FirmwareAndConfigurationSeeder is restricted to local and testing environments.');

            return;
        }

        $fixtures = [
            [FirmwareVersions::class, 'firmware', 'bin', "DEVELOPMENT FIXTURE ONLY - NOT FLASHABLE FIRMWARE\n"],
            [ConfigVersions::class, 'config', 'json', json_encode([
                'version' => '1',
                'prefix' => 'SP1',
                'development_fixture' => true,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n"],
        ];

        foreach ($fixtures as [$model, $kind, $extension, $contents]) {
            $identity = ['prefix' => 'SP1', 'version' => '1'];

            // Preserve uploaded releases, including their metadata and file contents.
            if ($model::where($identity)->exists()) {
                continue;
            }

            $path = "development/{$kind}/SP1_{$kind}_1.{$extension}";
            if (! Storage::exists($path) && ! Storage::put($path, $contents)) {
                throw new RuntimeException("Unable to write development fixture: {$path}");
            }

            $model::create($identity + [
                'file_path' => $path,
                'description' => 'Development fixture only; not for deployment to a device.',
                'timestamp' => now(),
            ]);
        }
    }
}
