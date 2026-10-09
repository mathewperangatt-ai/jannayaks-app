<?php

namespace Tests\Feature;

use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function legalPages(): array
    {
        return [
            'privacy' => ['legal.privacy', '/privacy', 'Privacy Policy'],
            'terms' => ['legal.terms', '/terms', 'Terms &amp; Conditions'],
            'refund' => ['legal.refund', '/refund-policy', 'Refund &amp; Cancellation'],
            'grievance' => ['legal.grievance', '/grievance', 'Grievance Redressal'],
            'disclaimer' => ['legal.disclaimer', '/disclaimer', 'Disclaimer'],
        ];
    }

    #[DataProvider('legalPages')]
    public function test_legal_page_renders_publicly_with_operator_details(string $routeName, string $path, string $heading): void
    {
        $this->assertSame(url($path), route($routeName));

        $response = $this->get($path)
            ->assertOk()
            ->assertSee('<h1>'.$heading.'</h1>', false)
            ->assertSee('Aurex Network', false)
            ->assertSee('mailto:hello@jannayaks.in', false)
            ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false)
            ->assertSee('jf-footer', false);

        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route($routeName), '#').'"\s+aria-current="page"#',
            (string) $response->getContent()
        );
    }

    public function test_legal_pages_show_the_corrected_legal_address(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('3/352, Trivandrum, Kerala 695573', false)
            ->assertDontSee('3/532', false);

        $this->get('/grievance')
            ->assertOk()
            ->assertSee('3/352, Trivandrum, Kerala 695573', false);
    }

    public function test_footer_links_point_to_every_legal_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (self::legalPages() as [$routeName, , $label]) {
            $response->assertSee('<a href="'.route($routeName).'">'.$label.'</a>', false);
            $response->assertDontSee('<a href="#">'.$label.'</a>', false);
        }
    }

    public function test_legal_page_paths_cannot_be_claimed_as_profile_urls(): void
    {
        $urls = app(ProfileUrlService::class);

        foreach (['privacy', 'terms', 'refund-policy', 'grievance', 'disclaimer'] as $slug) {
            $this->assertTrue($urls->isReserved($slug), "Slug [{$slug}] must be reserved.");
        }
    }
}
