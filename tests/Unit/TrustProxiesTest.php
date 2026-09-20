<?php

namespace Tests\Unit;

use App\Http\Middleware\TrustProxies;
use Illuminate\Config\Repository;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TrustProxiesTest extends TestCase
{
    public function test_forwarded_https_is_only_accepted_from_configured_proxy(): void
    {
        $middleware = new TrustProxies(new Repository([
            'proxies' => ['trusted' => ['172.20.0.1']],
        ]));

        foreach (['172.20.0.1' => true, '203.0.113.10' => false] as $address => $secure) {
            $request = Request::create('http://obsidian.tezca.net/login', 'GET', [], [], [], [
                'REMOTE_ADDR' => $address,
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ]);
            $middleware->handle($request, function ($request) use ($secure) {
                $this->assertSame($secure, $request->isSecure());
            });
        }
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_ALL);
    }
}
