<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Device;
use App\Models\ExternalApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalApiKeysTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;
    private const KEYS = '/developer-workspace/api-keys';

    protected function setUp(): void
    {
        parent::setUp();
        $this->developer = User::factory()->create(['email' => 'developer@example.test']);
        config(['app.developer_email' => $this->developer->email]);
    }

    public function test_only_the_verified_developer_browser_session_can_manage_keys(): void
    {
        $this->getJson(self::KEYS)->assertUnauthorized();
        $this->postJson(self::KEYS, ['name' => 'Denied'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(self::KEYS)->assertForbidden();
        $this->postJson(self::KEYS, ['name' => 'Denied'])->assertForbidden();
        $this->developer->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->developer)->postJson(self::KEYS, ['name' => 'Denied'])->assertForbidden();
        $this->assertDatabaseCount('external_api_keys', 0);
        $this->developer->forceFill(['email_verified_at' => now()])->save();
        $this->bearer(ApiToken::issue($this->developer))->postJson(self::KEYS, ['name' => 'Denied'])->assertUnauthorized();
    }

    public function test_creation_returns_the_secret_once_and_revocation_disables_it(): void
    {
        $response = $this->actingAs($this->developer)->postJson(self::KEYS, ['name' => 'Integration'])->assertCreated();
        $secret = $response->json('plain_text_key');
        $id = $response->json('data.id');
        $this->assertMatchesRegularExpression('/^obs_ext_[a-f0-9]{64}$/', $secret);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $key = ExternalApiKey::findOrFail($id);
        $this->assertSame(hash('sha256', $secret), $key->token_hash);
        $this->assertArrayNotHasKey('token_hash', $key->toArray());
        $list = $this->getJson(self::KEYS)->assertOk()->assertJsonPath('data.0.name', 'Integration');
        $list->assertDontSee($secret)->assertDontSee($key->token_hash);
        $this->bearer($secret)->getJson('/api/external/v1')->assertOk()->assertJsonPath('access', 'developer');
        $this->assertNotNull($key->fresh()->last_used_at);
        $this->withHeader('Authorization', '')->actingAs($this->developer, 'web')
            ->postJson(self::KEYS.'/'.$id.'/revoke')->assertOk()->assertJsonPath('data.status', 'Revoked');
        $this->bearer($secret)->getJson('/api/external/v1')->assertUnauthorized();
    }

    public function test_invalid_expired_former_developer_and_wrong_token_types_are_rejected(): void
    {
        [$key, $secret] = ExternalApiKey::issue($this->developer, 'Integration', null);
        $this->actingAs($this->developer)->getJson('/api/external/v1')->assertUnauthorized();
        $this->bearer(ApiToken::issue($this->developer))->getJson('/api/external/v1')->assertUnauthorized();
        $this->bearer($secret)->getJson('/api/all-devices-api')->assertUnauthorized();
        $this->bearer($secret)->getJson(self::KEYS)->assertUnauthorized();
        $this->bearer($secret.'x')->getJson('/api/external/v1')->assertUnauthorized();
        $key->forceFill(['expires_at' => now()->subSecond()])->save();
        $this->bearer($secret)->getJson('/api/external/v1')->assertUnauthorized();
        $key->forceFill(['expires_at' => null])->save();
        foreach (['another@example.test', '', null] as $email) {
            config(['app.developer_email' => $email]);
            $this->bearer($secret)->getJson('/api/external/v1')->assertUnauthorized();
        }
        config(['app.developer_email' => $this->developer->email]);
        $this->developer->forceFill(['email_verified_at' => null])->save();
        $this->bearer($secret)->getJson('/api/external/v1')->assertUnauthorized();
        $this->developer->delete();
        $this->assertDatabaseMissing('external_api_keys', ['id' => $key->id]);
    }

    public function test_validation_and_ownership_apply_to_key_management_and_legacy_creation(): void
    {
        $this->actingAs($this->developer)->postJson(self::KEYS, ['name' => '', 'expires_at' => '2000-01-01'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'expires_at']);
        [$foreign] = ExternalApiKey::issue(User::factory()->create(), 'Foreign', null);
        $this->postJson(self::KEYS.'/'.$foreign->id.'/revoke')->assertNotFound();
        $this->assertNull($foreign->fresh()->revoked_at);
        $response = $this->post(self::KEYS, ['name' => 'Legacy'])->assertCreated()->assertSee('Copy this key now');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->get(self::KEYS)->assertOk()->assertDontSee('Copy this key now');
    }

    public function test_external_devices_and_releases_reuse_existing_workflows_without_leaking_storage_paths(): void
    {
        Storage::fake('local');
        [$key, $secret] = ExternalApiKey::issue($this->developer, 'Integration', null);
        $device = Device::factory()->create(['name' => 'Before']);
        $this->bearer($secret)->getJson('/api/external/v1/devices')->assertOk()->assertJsonPath('data.0.id', $device->id);
        $this->bearer($secret)->getJson('/api/external/v1/devices/'.$device->id)->assertOk();
        $this->bearer($secret)->patchJson('/api/external/v1/devices/'.$device->id, ['name' => 'After'])->assertOk();
        $this->assertSame('After', $device->fresh()->name);
        $this->bearer($secret)->patchJson('/api/external/v1/devices/'.$device->id, ['state' => 'inactive'])->assertUnprocessable();
        $this->bearer($secret)->getJson('/api/external/v1/devices?per_page=1001')->assertUnprocessable();
        $this->bearer($secret)->getJson('/api/external/v1/devices/999999')->assertNotFound();
        $upload = $this->bearer($secret)->postJson('/api/external/v1/configuration', [
            'config' => UploadedFile::fake()->createWithContent('fixture.json', '{"fixture":true}'),
            'prefix' => 'TEST', 'description' => 'Synthetic integration test',
        ])->assertCreated();
        $this->bearer($secret)->getJson('/api/external/v1/configuration')->assertOk()->assertDontSee('file_path');
        $this->bearer($secret)->get('/api/external/v1/configuration/'.$upload->json('version').'/download')->assertOk();
        $this->assertNotNull($key->fresh()->last_used_at);
    }

    public function test_external_rate_limit_is_per_key(): void
    {
        [, $secret] = ExternalApiKey::issue($this->developer, 'Rate limited', null);
        for ($i = 0; $i < 120; $i++) {
            $this->bearer($secret)->getJson('/api/external/v1')->assertOk();
        }
        $this->bearer($secret)->getJson('/api/external/v1')->assertStatus(429);
        [, $second] = ExternalApiKey::issue($this->developer, 'Independent', null);
        $this->bearer($second)->getJson('/api/external/v1')->assertOk();
    }

    private function bearer(string $token): self
    {
        Auth::forgetGuards();
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
