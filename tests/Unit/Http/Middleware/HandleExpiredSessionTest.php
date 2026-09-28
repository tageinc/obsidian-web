<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\HandleExpiredSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * @covers \App\Http\Middleware\HandleExpiredSession
 */
class HandleExpiredSessionTest extends TestCase
{
    private HandleExpiredSession $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new HandleExpiredSession();
    }

    /**
     * Unauthenticated user → redirect to session-expired page.
     */
    public function test_unauthenticated_web_get_redirects_to_session_expired(): void
    {
        $request = Request::create('/dashboard', 'GET');

        $response = $this->middleware->handle($request, fn (Request $r) => response('ok'));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('session-expired', $response->headers->get('Location', ''));
    }

    /**
     * Unauthenticated API request → 401 JSON.
     */
    public function test_unauthenticated_api_returns_401_json(): void
    {
        $request = Request::create('/api/devices', 'GET');
        $request->headers->set('Accept', 'application/json');

        // Fake "expectsJson" by ensuring Accept is application/json.
        $response = $this->middleware->handle($request, fn (Request $r) => response('ok'));

        $this->assertEquals(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Session expired', $data['error']);
    }

    /**
     * Authenticated user → passthrough (no redirect).
     */
    public function test_authenticated_user_passthrough(): void
    {
        $request = Request::create('/dashboard', 'GET');

        // Use a fake authenticated user via the request.
        $request->setUserResolver(function () {
            return new class {
                public function getAuthIdentifier() { return 1; }
            };
        });

        // The middleware checks ! $request->user() — set it directly.
        $captured = null;
        $response = $this->middleware->handle($request, function (Request $r) use (&$captured) {
            $captured = $r;
            return response('ok');
        });

        // Authenticated user should NOT be redirected.
        $this->assertNotNull($captured);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Session-expired and refresh endpoints are never intercepted.
     */
    public function test_expired_routes_are_skipped(): void
    {
        foreach (['session.expired', 'session.expire.refresh'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Named route {$name} must exist");

            $request = Request::create($route->uri(), $route->methods()[0]);
            $request->setRouteResolver(fn () => $route);

            $captured = null;
            $response = $this->middleware->handle($request, function (Request $nextRequest) use (&$captured) {
                $captured = $nextRequest;
                return response('ok');
            });

            $this->assertSame($request, $captured, "{$name} must pass the request to the next middleware");
            $this->assertSame(200, $response->getStatusCode(), "{$name} must not redirect");
        }
    }
}
