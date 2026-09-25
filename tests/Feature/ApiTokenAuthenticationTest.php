<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiTokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_keeps_the_client_contract_and_logout_revokes_only_the_presented_token(): void
    {
        $user = User::factory()->create(['password' => Hash::make('test-password')]);
        $device = Device::factory()->create(['user_id' => $user->id]);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'test-password'])
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('user.id', $user->id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $token = $response->json('token');
        [$id, $secret] = explode('|', $token);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $id, 'token' => hash('sha256', $secret)]);
        $this->assertArrayNotHasKey('password', $response->json('user'));
        $second = ApiToken::issue($user);
        $this->bearer($token)->getJson('/api/devices/'.$device->id)->assertOk();
        $this->bearer($token)->postJson('/api/logout')->assertNoContent();
        $this->assertNotNull(ApiToken::findOrFail($id)->revoked_at);
        $this->bearer($token)->getJson('/api/devices/'.$device->id)->assertUnauthorized();
        $this->bearer($second)->getJson('/api/devices/'.$device->id)->assertOk();
        $this->assertNotNull(ApiToken::resolve($second)->last_used_at);
        $this->bearer('invalid')->postJson('/api/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertUnauthorized()->assertJsonPath('status', 'error');
    }

    public function test_previously_issued_tokens_survive_and_still_enforce_device_ownership(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        $foreign = Device::factory()->create();
        $secret = 'synthetic-previously-issued-token';
        $id = DB::table('personal_access_tokens')->insertGetId([
            'tokenable_type' => User::class, 'tokenable_id' => $user->id,
            'name' => 'API Token', 'token' => hash('sha256', $secret),
            'abilities' => '["*"]', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([$id.'|'.$secret, $secret] as $value) {
            $this->bearer($value)->getJson('/api/devices/'.$device->id)->assertOk();
            $this->bearer($value)->getJson('/api/devices/'.$foreign->id)->assertForbidden();
        }
        $this->bearer($id.'|wrong')->getJson('/api/devices/'.$device->id)->assertUnauthorized();
        ApiToken::findOrFail($id)->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->bearer($id.'|'.$secret)->getJson('/api/devices/'.$device->id)->assertUnauthorized();
        ApiToken::findOrFail($id)->forceFill(['expires_at' => null, 'tokenable_type' => 'OtherModel'])->save();
        $this->bearer($id.'|'.$secret)->getJson('/api/devices/'.$device->id)->assertUnauthorized();
    }

    public function test_same_origin_browser_api_requests_keep_sessions_and_require_csrf_for_writes(): void
    {
        $this->app->bind(\App\Http\Middleware\VerifyCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends \App\Http\Middleware\VerifyCsrfToken {
                protected function runningUnitTests() { return false; }
            };
        });
        $user = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);
        $session = [Auth::guard('web')->getName() => $user->id, '_token' => 'synthetic-csrf'];
        Auth::forgetGuards();
        $this->withSession($session)->withHeader('Origin', 'http://localhost')
            ->getJson('/api/devices/'.$device->id)->assertOk();
        Auth::forgetGuards();
        $this->postJson('/api/device/'.$device->id.'/retire')->assertStatus(419);
        $this->assertSame('active', $device->fresh()->state);
        Auth::forgetGuards();
        $this->postJson('/api/device/'.$device->id.'/retire', ['_token' => 'synthetic-csrf'])->assertOk();
        $this->assertSame('inactive', $device->fresh()->state);
        $this->bearer('invalid')->getJson('/api/devices/'.$device->id)->assertUnauthorized();
    }

    private function bearer(string $token): self
    {
        Auth::forgetGuards();
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
