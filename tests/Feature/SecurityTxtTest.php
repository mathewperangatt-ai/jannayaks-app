<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTxtTest extends TestCase
{
    public function test_security_txt_is_served_at_well_known_path(): void
    {
        $path = public_path('.well-known/security.txt');
        $this->assertFileExists($path);

        $expected = "Contact: mailto:founder@jannayaks.in\nExpires: 2027-11-01T00:00:00Z\nPreferred-Languages: en\n";
        $this->assertSame($expected, file_get_contents($path));

        // Production serves public/ files before index.php (FrankenPHP/Caddy/.htaccess).
        // The Feature HTTP client only hits the Laravel router, so register a
        // temporary test route that mirrors that static-file behaviour.
        Route::get('/.well-known/security.txt', function () use ($path) {
            return response()->file($path, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        });

        $response = $this->get('/.well-known/security.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertStreamedContent($expected);
        $this->assertStringNotContainsString('mathew.perangatt', $response->streamedContent());
    }
}
