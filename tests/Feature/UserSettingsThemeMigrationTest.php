<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSettingsThemeMigrationTest extends TestCase
{
    public function test_theme_migration_defaults_existing_settings_without_changing_email_preferences(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        require_once database_path('migrations/2026_09_26_000000_create_user_settings_table.php');
        require_once database_path('migrations/2026_09_26_000002_add_theme_mode_to_user_settings.php');
        (new \CreateUserSettingsTable())->up();
        DB::table('users')->insert([['id' => 1], ['id' => 2]]);
        DB::table('user_settings')->insert(['user_id' => 1, 'receive_app_activity_emails' => false]);

        (new \AddThemeModeToUserSettings())->up();

        $this->assertDatabaseHas('user_settings', [
            'user_id' => 1, 'receive_app_activity_emails' => false, 'theme_mode' => 'adaptive',
        ]);
        $this->assertDatabaseCount('user_settings', 1);
        DB::table('user_settings')->insert(['user_id' => 2]);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => 2, 'receive_app_activity_emails' => true, 'theme_mode' => 'adaptive',
        ]);
    }
}
