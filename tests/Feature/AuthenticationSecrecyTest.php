<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class AuthenticationSecrecyTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_login_retains_its_token_contract_without_serializing_or_logging_credentials(): void
    {
        $handler = new TestHandler();
        Log::swap(new Logger(new \Monolog\Logger('test', [$handler])));
        $password = 'sentinel-secret-password';
        $user = User::create([
            'name' => 'API account', 'email' => 'api-account@example.test',
            'password' => Hash::make($password), 'email_verified_at' => now(),
        ]);
        $user->remember_token = 'sentinel-remember-token';
        $user->save();
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => $password])
            ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('user.email', $user->email)
            ->assertJsonStructure(['token']);
        $this->assertNotEmpty($response->json('token'));
        foreach (['password', 'remember_token'] as $key) {
            $this->assertArrayNotHasKey($key, $response->json('user'));
            $this->assertArrayNotHasKey($key, $user->toArray());
        }
        $records = json_encode($handler->getRecords());
        foreach ([$password, $user->password, $user->remember_token, $response->json('token')] as $secret) {
            $this->assertStringNotContainsString($secret, $records);
        }
    }

    public function test_failed_api_login_and_verification_mail_do_not_log_credentials_or_signed_links(): void
    {
        $handler = new TestHandler();
        Log::swap(new Logger(new \Monolog\Logger('test', [$handler])));
        $password = 'sentinel-failed-password';
        $this->postJson('/api/login', ['email' => 'missing@example.test', 'password' => $password])->assertUnauthorized();
        $user = new User(['email' => 'recipient@example.test']);
        $user->id = 123;
        $message = (new CustomVerifyEmail())->toMail($user);
        $records = json_encode($handler->getRecords());
        $this->assertStringNotContainsString($password, $records);
        $this->assertStringNotContainsString($message->actionUrl, $records);
        $this->assertStringNotContainsString('signature=', $records);
    }

    public function test_csrf_comparison_rejects_mismatch_without_logging_either_token(): void
    {
        $handler = new TestHandler();
        Log::swap(new Logger(new \Monolog\Logger('test', [$handler])));
        $middleware = new class($this->app, $this->app['encrypter']) extends VerifyCsrfToken {
            public function compare(Request $request): bool
            {
                return $this->tokensMatch($request);
            }
        };
        $request = Request::create('/profile', 'PUT', ['_token' => 'sentinel-request-token']);
        $request->setLaravelSession($this->app['session.store']);
        $request->session()->put('_token', 'sentinel-session-token');
        $this->assertFalse($middleware->compare($request));
        $request->merge(['_token' => 'sentinel-session-token']);
        $this->assertTrue($middleware->compare($request));
        $records = json_encode($handler->getRecords());
        $this->assertStringNotContainsString('sentinel-request-token', $records);
        $this->assertStringNotContainsString('sentinel-session-token', $records);
    }
}
