<?php

namespace Tests\Feature;

use App\Services\ProfileUrlService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'terms' => ['legal.terms', '/terms', 'Terms and Conditions'],
            'refund' => ['legal.refund', '/refund-policy', 'Refund and Cancellation Policy'],
            'grievance' => ['legal.grievance', '/grievance', 'Grievance, Complaints and Takedown Policy'],
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
            ->assertSee('Effective date: 10 November 2026', false)
            ->assertSee('mailto:founder@jannayaks.in', false)
            ->assertSee('<link rel="canonical" href="'.route($routeName).'">', false)
            ->assertSee('jf-footer', false);

        $this->assertMatchesRegularExpression(
            '#href="'.preg_quote(route($routeName), '#').'"\s+aria-current="page"#',
            (string) $response->getContent()
        );
    }

    public function test_legal_pages_carry_no_tariff_amounts_or_draft_markers(): void
    {
        foreach (self::legalPages() as [, $path]) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('₹', false)
                ->assertDontSee('GST', false)
                ->assertDontSee('registered trademark', false)
                ->assertDontSee('legal-pending', false)
                ->assertDontSee('PENDING CONFIRMATION', false)
                ->assertDontSee('to be named before launch', false)
                ->assertDontSee('Working draft', false);
        }
    }

    public function test_terms_state_the_paused_cross_publication_position_and_trademark_status(): void
    {
        $this->get('/terms')
            ->assertOk()
            ->assertSee('Jannayaks does not currently offer applicants an option to have a profile published on IconsHerald.', false)
            ->assertSee('A trademark application has been filed and is pending. Registration has not been granted.', false)
            ->assertSee('You must be at least 18 years old', false)
            ->assertDontSee('In Memoriam', false)
            ->assertDontSee('Voter ID', false);
    }

    public function test_privacy_policy_names_the_fiduciary_processors_and_paused_cross_publication(): void
    {
        $this->get('/privacy')
            ->assertOk()
            ->assertSee('Digital Personal Data Protection Act, 2023', false)
            ->assertSee('Data Fiduciary', false)
            ->assertSee('Railway', false)
            ->assertSee('Cloudflare', false)
            ->assertSee('Razorpay', false)
            ->assertSee('Google Translate', false)
            ->assertSee('Jannayaks does not currently offer applicants an option to have profiles cross-published on IconsHerald.', false)
            ->assertSee('kept for at least six months after publication', false)
            ->assertSee('Grievance Officer, Aurex Network (Proprietor)', false)
            ->assertDontSee('Voter ID', false);
    }

    public function test_refund_page_states_the_three_refund_stages(): void
    {
        $this->get('/refund-policy')
            ->assertOk()
            ->assertSee('Before the profile is submitted for approval', false)
            ->assertSee('Full refund', false)
            ->assertSee('From submission for approval until 7 days after submission', false)
            ->assertSee('60% refund', false)
            ->assertSee('After you approve the profile, or after the 7-day period ends', false)
            ->assertSee('No refund', false);
    }

    public function test_grievance_page_names_the_designated_grievance_officer(): void
    {
        $this->get('/grievance')
            ->assertOk()
            ->assertSee('<strong>Designated Grievance Officer:</strong> Grievance Officer, Aurex Network (Proprietor).', false)
            ->assertSee('Grievance / Profile Complaint', false);
    }

    public function test_disclaimer_carries_the_bilingual_editorial_disclosure(): void
    {
        $this->get('/disclaimer')
            ->assertOk()
            ->assertSee('Information on Jannayaks', false)
            ->assertSee('It is not a certificate, recommendation or endorsement.', false)
            ->assertSee('we do not independently verify every statement in them', false)
            ->assertSee('Grievance Redressal page', false)
            ->assertSee('Jannayaks-ലെ വിവരങ്ങളെക്കുറിച്ച്', false)
            ->assertSee('അത് ഒരു സാക്ഷ്യപത്രമോ ശുപാർശയോ ഔദ്യോഗിക അംഗീകാരമോ അല്ല.', false);
    }

    public function test_homepage_carries_the_compact_editorial_disclosure_with_disclaimer_link(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Editorial and Information Notice', false)
            ->assertSee('If Jannayaks declines an application before publication, the full amount paid, including applicable taxes, will be refunded.', false)
            ->assertSee('Editorial, വിവര അറിയിപ്പ്', false)
            ->assertSee('<a href="'.route('legal.disclaimer').'"', false);
    }

    public function test_published_profiles_disclose_supplied_information_near_contact(): void
    {
        Storage::fake('public');
        $this->seed(DemoProfilesSeeder::class);

        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('Prepared from information supplied by the profile owner. Not independently verified.', false)
            ->assertSee('വിവരങ്ങൾ സ്വതന്ത്രമായി സ്ഥിരീകരിച്ചിട്ടില്ല', false);
    }

    public function test_footer_links_point_to_every_legal_page(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach ([
            'legal.privacy' => 'Privacy Policy',
            'legal.terms' => 'Terms &amp; Conditions',
            'legal.grievance' => 'Grievance Redressal',
            'legal.refund' => 'Refund &amp; Cancellation',
            'legal.disclaimer' => 'Disclaimer',
        ] as $routeName => $label) {
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
