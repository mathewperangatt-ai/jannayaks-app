<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\ReservedSlug;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalProfileUrlSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggestions_are_name_derived_and_mark_unavailable_collisions(): void
    {
        $owner = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair');
        $urls = app(ProfileUrlService::class);
        $urls->selectPersonalSlug($owner['profile'], $owner['user'], 'arun.kumar', 'accomplished');

        $suggestions = $urls->suggestPersonalSlugs('Arun Kumar Nair', (int) $owner['profile']->id);

        $bySlug = [];
        foreach ($suggestions as $row) {
            $bySlug[$row['slug']] = $row['available'];
        }

        $this->assertArrayHasKey('arun.kumar', $bySlug);
        $this->assertTrue($bySlug['arun.kumar']);

        $other = $this->prepareUnpublishedLiving('distinguished', 'Arun Kumar Nair');
        $otherSuggestions = $urls->suggestPersonalSlugs('Arun Kumar Nair', (int) $other['profile']->id);

        $taken = collect($otherSuggestions)->firstWhere('slug', 'arun.kumar');
        $this->assertNotNull($taken);
        $this->assertFalse($taken['available']);

        $available = collect($otherSuggestions)->firstWhere('available', true);
        $this->assertNotNull($available);
        $this->assertMatchesRegularExpression('/^arun/', $available['slug']);
        $this->assertStringNotContainsString((string) $other['profile']->id, $available['slug']);
    }

    public function test_availability_endpoint_rejects_vanity_and_accepts_name_derived(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair');

        $this->actingAs($prepared['user'])
            ->getJson(route('applications.profile-url.availability', $prepared['application']).'?slug=superstar')
            ->assertOk()
            ->assertJsonPath('available', false);

        $this->actingAs($prepared['user'])
            ->getJson(route('applications.profile-url.availability', $prepared['application']).'?slug=arun.kumar')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('slug', 'arun.kumar');
    }

    public function test_professional_title_is_allowed_when_consistent_and_not_auto_required(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar', 'Physician');
        $urls = app(ProfileUrlService::class);

        $urls->selectPersonalSlug($prepared['profile'], $prepared['user'], 'dr.arun.kumar', 'accomplished');
        $this->assertSame('dr.arun.kumar', $prepared['profile']->fresh()->slug);

        $suggestions = $urls->suggestPersonalSlugs('Arun Kumar', (int) $prepared['profile']->id);
        $this->assertFalse(collect($suggestions)->contains(fn (array $row): bool => str_starts_with($row['slug'], 'dr.')));
    }

    public function test_reserved_admin_slug_cannot_be_claimed(): void
    {
        ReservedSlug::query()->create([
            'slug' => 'indian-national-congress',
            'category' => ReservedSlug::CATEGORY_POLITICAL,
            'reason' => 'Political party',
        ]);

        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Indian National Congress');

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.update', $prepared['application']), [
            'slug' => 'indian-national-congress',
        ])->assertSessionHasErrors('slug');
    }

    public function test_previously_used_slug_cannot_be_recycled_after_profile_row_is_gone(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair');
        app(ProfileUrlService::class)->selectPersonalSlug($prepared['profile'], $prepared['user'], 'arun.kumar', 'accomplished');

        $this->assertDatabaseHas('slug_reservations', ['slug' => 'arun.kumar']);

        $prepared['application']->forceFill(['profile_id' => null])->save();
        $prepared['profile']->delete();

        $this->assertDatabaseHas('slug_reservations', ['slug' => 'arun.kumar']);

        $other = $this->prepareUnpublishedLiving('distinguished', 'Arun Kumar Nair');
        $this->expectException(\InvalidArgumentException::class);
        app(ProfileUrlService::class)->selectPersonalSlug($other['profile'], $other['user'], 'arun.kumar', 'distinguished');
    }

    public function test_selected_unpublished_url_does_not_expose_the_profile(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair');
        app(ProfileUrlService::class)->selectPersonalSlug($prepared['profile'], $prepared['user'], 'arun.kumar', 'accomplished');

        $this->get('/arun.kumar')->assertNotFound();
        $this->get('/p/arun.kumar')->assertNotFound();
        $this->get('/arun.kumar')->assertDontSee('Arun Kumar Nair', false);
    }

    public function test_approved_published_profile_resolves_at_root_slug_and_legacy_path_redirects(): void
    {
        [$member, $application, $profile] = $this->publishLiving('accomplished', 'Arun Kumar Nair');
        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');

        $this->get('/arun.kumar')->assertOk()->assertSee('Arun Kumar Nair', false);
        $this->get('/p/arun.kumar')->assertRedirect('/arun.kumar');
        $this->assertSame($member->id, (int) $profile->fresh()->user_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_system_routes_continue_to_work_alongside_catch_all_profile_urls(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/apply')->assertOk();
        $this->get('/gallery')->assertOk();
        $this->get('/search')->assertOk();
        $this->get('/faq-charges')->assertOk();
        $this->get('/admin')->assertRedirect();
        $this->get('/contact')->assertNotFound();
    }

    public function test_profile_and_profession_edits_do_not_change_the_slug(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair', 'Teacher');
        app(ProfileUrlService::class)->selectPersonalSlug($prepared['profile'], $prepared['user'], 'arun.kumar', 'accomplished');

        $prepared['profile']->forceFill([
            'profession' => 'District Collector',
            'display_name' => 'A. K. Nair',
            'bio_headline' => 'Updated headline',
        ])->save();

        $this->assertSame('arun.kumar', $prepared['profile']->fresh()->slug);
        $this->assertDatabaseHas('slug_reservations', [
            'slug' => 'arun.kumar',
            'profile_id' => $prepared['profile']->id,
        ]);
    }

    public function test_member_cannot_change_personal_url_after_it_is_assigned(): void
    {
        $prepared = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar Nair');
        app(ProfileUrlService::class)->selectPersonalSlug($prepared['profile'], $prepared['user'], 'arun.kumar', 'accomplished');

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.update', $prepared['application']), [
            'slug' => 'arun.kumar.nair',
        ])->assertSessionHasErrors('slug');

        $this->assertSame('arun.kumar', $prepared['profile']->fresh()->slug);
    }

    public function test_numeric_collision_suffix_is_not_the_profile_id(): void
    {
        $first = $this->prepareUnpublishedLiving('accomplished', 'Arun Kumar');
        app(ProfileUrlService::class)->selectPersonalSlug($first['profile'], $first['user'], 'arun.kumar', 'accomplished');

        $second = $this->prepareUnpublishedLiving('distinguished', 'Arun Kumar');
        $suggestions = app(ProfileUrlService::class)->suggestPersonalSlugs('Arun Kumar', (int) $second['profile']->id);

        foreach ($suggestions as $row) {
            $this->assertStringNotContainsString((string) $second['profile']->id, $row['slug']);
        }
    }

    public function test_home_footer_uses_official_contact_details(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('hello@jannayaks.in', false)
            ->assertSee('94 95 94 93 99', false)
            ->assertSee('3/532, Trivandrum 695573', false)
            ->assertDontSee('hello@jannayaks.com', false);
    }

    /**
     * @return array{user: User, application: Application, profile: Profile}
     */
    private function prepareUnpublishedLiving(string $tier, string $fullName = 'Arun Kumar Nair', string $profession = ''): array
    {
        $member = User::factory()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => $profession,
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
        ]);

        return [
            'user' => $member,
            'application' => $application->fresh(),
            'profile' => $profile->fresh(),
        ];
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function publishLiving(string $tier, string $fullName = 'Arun Kumar Nair'): array
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
        $profile = Profile::query()->findOrFail($published->profile_id);

        return [$member, $published->fresh(), $profile->fresh()];
    }
}
