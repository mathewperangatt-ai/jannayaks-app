<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyHttpsTest extends TestCase
{
    public function test_forwarded_https_scheme_and_client_ip_are_honored_behind_a_proxy(): void
    {
        $this->get('/up', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-For' => '203.0.113.10',
        ])->assertOk();

        $request = $this->app['request'];

        $this->assertTrue($request->secure());
        $this->assertSame('203.0.113.10', $request->ip());
    }
}
