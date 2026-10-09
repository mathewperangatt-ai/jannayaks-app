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

    public function test_legal_pages_show_the_confirmed_contact_details(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('3/532 A, Trivandrum 695573', false)
            ->assertSee('Name of the person who answers: Mathew', false)
            ->assertSee('tel:+919495949399', false);

        $this->get('/grievance')
            ->assertOk()
            ->assertSee('3/532 A, Trivandrum 695573', false)
            ->assertSee('[to be named before launch]', false);
    }

    public function test_legal_pages_omit_retired_and_unconfirmed_providers(): void
    {
        foreach (self::legalPages() as [, $path]) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('Razorpay', false)
                ->assertDontSee('MSG91', false)
                ->assertDontSee('one-time password', false)
                ->assertDontSee('overseas', false);
        }
    }

    public function test_refund_page_states_the_configured_before_publication_percent(): void
    {
        config(['jannayaks.refund.before_publication_percent' => 45]);

        $this->get('/refund-policy')
            ->assertOk()
            ->assertSee('refund of 45% of the fee you paid', false)
            ->assertDontSee('full refund</strong> of what you paid', false);
    }

    public function test_terms_mark_gst_as_pending_and_state_memorial_hosting_period(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('GST — PENDING CONFIRMATION', false)
            ->assertDontSee('does not charge GST', false)
            ->assertSee('hosted for three years from the date it is published', false);
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
