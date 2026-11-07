<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Personal URL eligibility follows the living (Standard Profile) workflow,
 * not the historical package-tier identifier: emerging, accomplished and
 * distinguished applications are all eligible; In Memoriam and unknown
 * workflow identifiers are never eligible; an existing permanent personal
 * slug stays locked.
 */
class PersonalUrlEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function prepareApplication(string $tier, string $fullName = 'Ratish Ravindra'): array
    {
        $user = User::factory()->create();

        $profile = Profile::query()->create([
            'user_id' => $user->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $user->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
        ]);

        return [
            'user' => $user,
            'application' => $application->fresh(),
            'profile' => $profile->fresh(),
        ];
    }

    public function test_eligibility_gate_matrix(): void
    {
        $gate = fn (string $tier) => app(ProfileUrlService::class)->packageTierAllowsPersonalSlug($tier);

        // Ordinary Standard Profile workflow tiers — all eligible.
        $this->assertTrue($gate('distinguished')); // current Standard Profile internal tier
        $this->assertTrue($gate('accomplished'));  // historical ordinary application
        $this->assertTrue($gate('emerging'));      // historical ordinary application
        $this->assertTrue($gate(' DISTINGUISHED ')); // normalised

        // Structurally separate workflow + invalid identifiers — never eligible.
        $this->assertFalse($gate('in_memoriam'));
        $this->assertFalse($gate('standard'));
        $this->assertFalse($gate('banana'));
        $this->assertFalse($gate(''));
    }

    public function test_current_standard_profile_application_receives_suggestions(): void
    {
        $prepared = $this->prepareApplication('distinguished');

        $response = $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('Choose your personal URL')
            ->assertSee('ratish.ravindra', false)
            ->assertSee('jannayaks.in/', false);
        // IconsHerald cross-hosting is no longer offered on this page.
        $response->assertDontSee('IconsHerald</b>', false);
        $response->assertDontSee('iconsherald.com/your.name', false);
    }

    public function test_each_available_suggestion_shows_the_jannayaks_preview_only(): void
    {
        $prepared = $this->prepareApplication('distinguished', 'Meera Nair');

        $html = $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('https://jannayaks.in/meera.nair', $html);
        // Cross-hosting previews are no longer offered to applicants.
        $this->assertStringNotContainsString('https://iconsherald.com/meera.nair', $html);
        // The remaining preview carries the same slug and is tagged for the active-domain sync.
        $this->assertSame(1, substr_count($html, 'data-slug="meera.nair" data-domain='));
    }

    /**
     * Regression guard for the Ratish Ravindra application scenario: a
     * historical legacy tier on the application must not block the preferred
     * name/URL selection, change the questionnaire question set, or alter
     * required-field validation. Every historical tier walks the identical
     * Standard Profile flow.
     *
     * @dataProvider legacyTierProvider
     */
    #[DataProvider('legacyTierProvider')]
    public function test_legacy_tier_applications_walk_the_standard_url_and_interview_flow(string $tier): void
    {
        $prepared = $this->prepareApplication($tier, 'Ratish Ravindra');

        // 1. URL selection page offers the personal (name-based) flow — never
        //    the six-character system-address dead end.
        $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('Choose your personal URL', false)
            ->assertSee('https://jannayaks.in/ratish.ravindra', false)
            ->assertDontSee('six-character system address', false)
            ->assertDontSee('System-assigned URL', false);

        // 2. Reservation succeeds and stores the preferred name-derived slug.
        $this->actingAs($prepared['user'])
            ->post(route('applications.profile-url.update', $prepared['application']), [
                'slug' => 'ratish.ravindra',
            ])
            ->assertRedirect(route('applications.profile-url', $prepared['application']))
            ->assertSessionHas('status');
        $this->assertSame('ratish.ravindra', $prepared['profile']->fresh()->slug);

        // 3. The questionnaire question set is identical for every tier.
        $ids = \App\Support\OnlineInterviewCatalog::idsForTier($tier);
        $this->assertSame(
            \App\Support\OnlineInterviewCatalog::idsForTier('distinguished'),
            $ids,
            "Tier [{$tier}] must not change the Online Interview questions.",
        );

        // 4. Required-field validation is identical: an empty submission is
        //    missing the same required questions on every tier.
        $this->assertSame(
            \App\Support\OnlineInterviewCatalog::missingRequired('distinguished', []),
            \App\Support\OnlineInterviewCatalog::missingRequired($tier, []),
            "Tier [{$tier}] must not change required-field validation.",
        );
    }

    public static function legacyTierProvider(): array
    {
        return [
            'emerging' => ['emerging'],
            'accomplished' => ['accomplished'],
            'distinguished' => ['distinguished'],
        ];
    }

    private function bladePreviewHtml(string $tier, string $fullName): string
    {
        $prepared = $this->prepareApplication($tier, $fullName);

        return $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->getContent();
    }

    public function test_historical_emerging_application_receives_suggestions_and_can_reserve(): void
    {
        $prepared = $this->prepareApplication('emerging', 'Meera Nair');

        $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('Choose your personal URL')
            ->assertSee('meera.nair', false);

        $this->actingAs($prepared['user'])
            ->post(route('applications.profile-url.update', $prepared['application']), [
                'slug' => 'meera.nair',
            ])
            ->assertRedirect(route('applications.profile-url', $prepared['application']))
            ->assertSessionHas('status');

        $prepared['profile']->refresh();
        $prepared['application']->refresh();
        $this->assertSame('meera.nair', $prepared['profile']->slug);
    }

    public function test_historical_accomplished_application_is_eligible(): void
    {
        $prepared = $this->prepareApplication('accomplished', 'Vishnu Prasad');

        $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('Choose your personal URL')
            ->assertSee('vishnu.prasad', false);
    }

    public function test_existing_permanent_slug_remains_locked_regardless_of_tier(): void
    {
        $prepared = $this->prepareApplication('emerging', 'Locked Legacy Person');
        $urls = app(ProfileUrlService::class);

        // The legacy application already reserved a permanent personal slug.
        $urls->selectPersonalSlug($prepared['profile'], $prepared['user'], 'locked.legacy', 'emerging');
        $prepared['profile']->refresh();
        $this->assertSame('locked.legacy', $prepared['profile']->slug);
        $this->assertTrue($urls->hasPermanentPersonalSlug($prepared['profile']));

        $response = $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk();

        $this->assertStringContainsString(
            'Your personal URL is permanent and cannot be changed from this page.',
            $response->getContent(),
        );
        // No suggestion buttons are rendered for a locked profile.
        $this->assertStringNotContainsString('js-slug-choice', $response->getContent());

        // A different slug is refused by the permanence rule.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('permanent once chosen');

        $urls->selectPersonalSlug($prepared['profile'], $prepared['user'], 'locked.legacy2', 'emerging');
    }
}
