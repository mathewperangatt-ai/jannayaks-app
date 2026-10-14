<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_send_required_security_headers(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), usb=(), magnetometer=(), accelerometer=(), gyroscope=(), interest-cohort=()'
        );
    }

    public function test_headers_are_applied_to_homepage_and_404s_alike(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->get('/this-route-does-not-exist-9x7')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_csp_ships_report_only_by_default(): void
    {
        config(['jannayaks.security.headers.csp_enforce' => false]);

        $response = $this->get(route('login'))->assertOk();

        $this->assertTrue($response->headers->has('Content-Security-Policy-Report-Only'));
        $this->assertFalse($response->headers->has('Content-Security-Policy'));

        $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('https://fonts.googleapis.com', $csp);
        $this->assertStringContainsString('https://fonts.gstatic.com', $csp);
        $this->assertStringContainsString('https://translate.google.com', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }

    public function test_member_csp_allows_only_the_google_translate_hosts_it_needs(): void
    {
        config(['jannayaks.security.headers.csp_enforce' => true]);
        $csp = (string) $this->get(route('home'))->assertOk()->headers->get('Content-Security-Policy');
        $directives = collect(explode(';', $csp))
            ->map(fn (string $d) => preg_split('/\s+/', trim($d)))
            ->mapWithKeys(fn (array $parts) => [array_shift($parts) => $parts]);

        $this->assertContains('https://translate-pa.googleapis.com', $directives['script-src']);
        $this->assertContains('https://translate-pa.googleapis.com', $directives['connect-src']);
        $this->assertContains('https://www.gstatic.com', $directives['style-src']);
        $this->assertContains('https://www.gstatic.com', $directives['img-src']);
        $this->assertSame(["'self'"], $directives['default-src']);
        $this->assertSame(['https://translate.googleapis.com'], $directives['frame-src']);
        $this->assertSame(["'none'"], $directives['frame-ancestors']);
        $this->assertNotContains("'unsafe-eval'", $directives['script-src']);
        foreach ($directives as $sources) {
            $this->assertNotContains('*', $sources);
            $this->assertNotContains('https:', $sources);
        }

        $admin = (string) config('jannayaks.security.headers.csp_admin');
        $this->assertStringNotContainsString('translate-pa.googleapis.com', $admin);
        $this->assertStringContainsString("'unsafe-eval'", $admin);
    }

    public function test_csp_can_be_enforced_via_config(): void
    {
        config(['jannayaks.security.headers.csp_enforce' => true]);

        $response = $this->get(route('login'))->assertOk();

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertFalse($response->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_php_version_banner_is_not_sent(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $this->assertFalse($response->headers->has('X-Powered-By'));
    }
}
