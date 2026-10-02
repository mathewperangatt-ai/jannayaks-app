<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * SEO-5: branded public error pages.
 *
 * - 404 renders the branded view with a genuine HTTP 404 (no soft-200).
 * - 500 renders the branded view without exposing exception details.
 * - Neither exposes internal information; both stay noindex via the layout.
 */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_public_url_returns_branded_404(): void
    {
        $response = $this->get('/this-page-does-not-exist-anywhere');

        $response->assertStatus(404); // genuine 404, not a soft-200
        $content = $response->getContent();

        $this->assertStringContainsString('This page could not be found.', $content);
        $this->assertStringContainsString('Return to the homepage', $content);
        $this->assertStringContainsString('jf-footer', $content); // shared footer present
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $content);
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('storage/logs', $content);
        $this->assertStringNotContainsString('application/ld+json', $content); // no content schema on errors
    }

    public function test_invalid_profile_slug_returns_branded_404(): void
    {
        $this->get('/no.such.profile')
            ->assertStatus(404)
            ->assertSee('This page could not be found.', false);
    }

    public function test_server_error_is_branded_and_hides_exception_details(): void
    {
        // A controlled route that fails, so the branded 500 view can be
        // verified without depending on production internals.
        Route::get('/_error-pages-test/boom', function (): never {
            abort(500, 'boom-secret-exception-detail-42');
        });

        $response = $this->get('/_error-pages-test/boom');

        $response->assertStatus(500); // genuine 500, not a soft-200
        $content = $response->getContent();

        $this->assertStringContainsString('Something went wrong on our side.', $content);
        $this->assertStringContainsString('temporary problem', $content);
        $this->assertStringContainsString('Return to the homepage', $content);
        $this->assertStringContainsString('jf-footer', $content);

        // No exception message, stack frame, or environment leakage.
        $this->assertStringNotContainsString('boom-secret-exception-detail-42', $content);
        $this->assertStringNotContainsString('RuntimeException', $content);
        $this->assertStringNotContainsString('vendor/', $content);
        $this->assertStringNotContainsString('storage/framework', $content);
        $this->assertStringNotContainsString('stack trace', $content);
        $this->assertStringNotContainsString('application/ld+json', $content);
    }
}
