<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_production_session_cookies_are_encrypted_and_secure_by_default(): void
    {
        $this->assertTrue((bool) config('session.encrypt'));
        $this->assertTrue((bool) config('session.secure'));
        $this->assertTrue((bool) config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertSame('json', config('session.serialization'));
    }

    public function test_authenticated_write_endpoints_have_throttling_middleware(): void
    {
        $routes = app('router')->getRoutes();

        $expected = [
            'applications.store' => 'throttle:10,1',
            'applications.upload.material' => 'throttle:10,1',
            'online-interview.save' => 'throttle:60,1',
            'online-interview.submit' => 'throttle:10,1',
            'applications.payment.initiate' => 'throttle:10,1',
        ];

        foreach ($expected as $name => $middleware) {
            $route = $routes->getByName($name);

            $this->assertNotNull($route, $name.' route must exist.');
            $this->assertContains($middleware, $route->middleware(), $name.' must be throttled.');
        }
    }
}
