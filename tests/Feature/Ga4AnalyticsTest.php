<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Ga4AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_scripts_are_absent_when_measurement_id_is_empty(): void
    {
        config(['jannayaks.ga4.measurement_id' => '']);

        foreach ([route('home'), route('faq-charges'), route('gallery.index'), route('legal.privacy')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
            $this->assertStringNotContainsString('gtag(', $html);
            $this->assertStringNotContainsString('G-TEST', $html);
        }
    }

    public function test_invalid_measurement_id_is_treated_as_disabled(): void
    {
        // Mirror the config gate: only G-XXXXXXXX values are accepted.
        putenv('JANNAYAKS_GA4_MEASUREMENT_ID=not-a-measurement-id');
        $resolved = (static function (): string {
            $raw = strtoupper(trim((string) env('JANNAYAKS_GA4_MEASUREMENT_ID', '')));

            return preg_match('/^G-[A-Z0-9]+$/', $raw) === 1 ? $raw : '';
        })();
        config(['jannayaks.ga4.measurement_id' => $resolved]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertSame('', $resolved);

        putenv('JANNAYAKS_GA4_MEASUREMENT_ID');
    }

    public function test_enabled_measurement_id_loads_gtag_on_public_marketing_pages_only(): void
    {
        config(['jannayaks.ga4.measurement_id' => 'G-TESTMEASURE1']);

        foreach ([route('home'), route('faq-charges'), route('gallery.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-TESTMEASURE1', $html);
            $this->assertStringContainsString("gtag('config', \"G-TESTMEASURE1\"", $html);
            $this->assertStringContainsString('"allow_google_signals":false', $html);
            $this->assertStringContainsString('"allow_ad_personalization_signals":false', $html);
            $this->assertStringContainsString('"anonymize_ip":true', $html);
            $this->assertStringNotContainsString('livewire:navigated', $html);
            $this->assertStringNotContainsString('popstate', $html);
            $this->assertSame(1, substr_count($html, 'googletagmanager.com/gtag/js'));
        }

        $loginHtml = $this->get(route('login'))->assertOk()->getContent();
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $loginHtml);

        $member = User::factory()->create(['email_verified_at' => now()]);
        $appHtml = $this->actingAs($member)->get(route('apply'))->assertOk()->getContent();
        $this->assertStringNotContainsString('googletagmanager.com/gtag/js', $appHtml);
    }

    public function test_public_profile_uses_generic_page_title_and_omits_display_name_from_gtag_config(): void
    {
        config(['jannayaks.ga4.measurement_id' => 'G-TESTMEASURE1']);

        $displayName = 'UniqueDisplayNameForGa4';
        [$profile] = $this->makePublishedProfileForGa4($displayName);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=G-TESTMEASURE1', $html);
        $this->assertStringContainsString('"page_title":"Public profile"', $html);
        $this->assertStringContainsString($displayName, $html, 'Visible page content may still show the name.');

        // Isolate the gtag config JSON object and ensure it stays non-identifying.
        $marker = 'gtag(\'config\', "G-TESTMEASURE1", ';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, 'Expected a gtag config call for the measurement ID.');
        $jsonStart = $start + strlen($marker);
        $jsonEnd = strpos($html, ');', $jsonStart);
        $this->assertNotFalse($jsonEnd, 'Expected gtag config call to terminate.');
        $configJson = substr($html, $jsonStart, $jsonEnd - $jsonStart);
        $this->assertStringContainsString('"page_title":"Public profile"', $configJson);
        $this->assertStringNotContainsString($displayName, $configJson);
        $this->assertStringNotContainsString((string) $profile->reference_code, $configJson);
        $this->assertSame(1, substr_count($html, $marker));
    }

    public function test_privacy_policy_discloses_conditional_analytics_without_claiming_it_is_always_on(): void
    {
        config(['jannayaks.ga4.measurement_id' => '']);

        $html = $this->get(route('legal.privacy'))->assertOk()->getContent();
        $this->assertStringContainsString('When Google Analytics 4 is switched on', $html);
        $this->assertStringContainsString('it is off unless our operators configure it', $html);
        $this->assertStringContainsString('We do not use advertising cookies', $html);
        $this->assertStringNotContainsString('We do not use advertising or analytics cookies', $html);
    }

    /**
     * @return array{0: Profile}
     */
    private function makePublishedProfileForGa4(string $displayName): array
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $displayName,
            'display_name' => $displayName,
            'profession' => 'Public servant',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $displayName,
            'preferred_display_name' => $displayName,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $displayName,
            'body' => 'Approved English biography for GA4 tests.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => true,
        ]);

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return [Profile::query()->findOrFail($published->profile_id)];
    }
}
