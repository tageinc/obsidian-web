<?php

namespace Tests\Feature;

use App\Models\ConfigVersions;
use App\Models\FirmwareVersions;
use Database\Seeders\FirmwareAndConfigurationSeeder;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmwareAndConfigurationSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $migration = require database_path('migrations/2026_09_20_000000_create_device_application_tables.php');
        $migration->up();
        Storage::fake(config('filesystems.default'));
    }

    public function test_seeded_versions_and_downloads_work_and_reruns_preserve_records(): void
    {
        $this->seed(FirmwareAndConfigurationSeeder::class);

        foreach (['firmware' => FirmwareVersions::class, 'config' => ConfigVersions::class] as $kind => $model) {
            $record = $model::firstOrFail();
            Storage::assertExists($record->file_path);
            $this->getJson("/api/{$kind}-version/SP1")->assertOk()->assertJson(['version' => '1']);
            $this->get("/api/{$kind}-file/prefix/SP1")->assertOk();
            $record->update(['description' => 'Keep this uploaded release']);
            Storage::put($record->file_path, 'Existing file contents');
        }

        $this->seed(FirmwareAndConfigurationSeeder::class);

        foreach ([FirmwareVersions::class, ConfigVersions::class] as $model) {
            $this->assertSame(1, $model::count());
            $record = $model::firstOrFail();
            $this->assertSame('Keep this uploaded release', $record->description);
            $this->assertSame('Existing file contents', Storage::get($record->file_path));
        }
    }

    public function test_production_does_not_receive_development_artifacts(): void
    {
        $this->app->instance('env', 'production');
        $this->artisan('db:seed', [
            '--class' => FirmwareAndConfigurationSeeder::class,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, FirmwareVersions::count());
        $this->assertSame(0, ConfigVersions::count());
        $this->assertSame([], Storage::allFiles());
    }
}
