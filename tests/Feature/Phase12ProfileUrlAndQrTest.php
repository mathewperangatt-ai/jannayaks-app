<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileQrCodeService;
use App\Services\ProfileUrlService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Phase12ProfileUrlAndQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.url', 'https://www.jannayaks.in');
        URL::forceRootUrl('https://www.jannayaks.in');
        URL::forceScheme('https');
    }

    public function test_emerging_receives_exactly_six_alphanumeric_characters_on_publish(): void
    {
        [$member, $application, $profile] = $this->publishLiving('emerging');

        $slug = (string) $profile->fresh()->slug;
        $this->assertSame(6, strlen($slug));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $slug);
        $this->assertSame($member->id, (int) $profile->user_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_emerging_cannot_choose_custom_personal_url(): void
    {
        [$member, $application, $profile] = $this->publishLiving('emerging');
        $before = $profile->fresh()->slug;

        $this->actingAs($member)->post(route('applications.profile-url.update', $application), [
            'slug' => 'mathew-perangatt',
        ])->assertSessionHasErrors('slug');

        $this->assertSame($before, $profile->fresh()->slug);

        $this->expectException(\InvalidArgumentException::class);
        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile->fresh(),
            $member,
            'mathew-perangatt',
            'emerging',
        );
    }

    public function test_generated_emerging_urls_are_unique(): void
    {
        $slugs = [];
        for ($i = 0; $i < 5; $i++) {
            [, , $profile] = $this->publishLiving('emerging');
            $slugs[] = $profile->fresh()->slug;
        }

        $this->assertSame(count($slugs), count(array_unique($slugs)));
    }

    public function test_generated_emerging_urls_are_not_predictable_from_database_ids(): void
    {
        [, , $profile] = $this->publishLiving('emerging');
        $slug = (string) $profile->fresh()->slug;

        $this->assertStringNotContainsString((string) $profile->id, $slug);
        $this->assertDoesNotMatchRegularExpression('/^0+$/', $slug);
        $this->assertNotSame(str_pad((string) $profile->id, 6, '0', STR_PAD_LEFT), $slug);
    }

    public function test_accomplished_can_select_available_personal_url(): void
    {
        [$member, $application, $profile] = $this->prepareUnpublishedLiving('accomplished');

        $this->actingAs($member)->post(route('applications.profile-url.update', $application), [
            'slug' => 'arun.kumar',
        ])->assertRedirect(route('applications.profile-url', $application));

        $this->assertSame('arun.kumar', $profile->fresh()->slug);
    }

    public function test_distinguished_can_select_available_personal_url(): void
    {
        [$member, $application, $profile] = $this->prepareUnpublishedLiving('distinguished');

        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile->fresh(),
            $member,
            'arun.kumar.nair',
            'distinguished',
        );

        $this->assertSame('arun.kumar.nair', $profile->fresh()->slug);
        $this->actingAs($member)->get(route('applications.profile-url', $application))
            ->assertOk()
            ->assertSee('arun.kumar.nair', false);
    }

    public function test_duplicate_personal_urls_are_rejected(): void
    {
        [$memberA, , $profileA] = $this->prepareUnpublishedLiving('accomplished');
        app(ProfileUrlService::class)->selectPersonalSlug($profileA->fresh(), $memberA, 'arun.kumar', 'accomplished');

        [$memberB, , $profileB] = $this->prepareUnpublishedLiving('distinguished');

        $this->expectException(\InvalidArgumentException::class);
        app(ProfileUrlService::class)->selectPersonalSlug($profileB->fresh(), $memberB, 'arun.kumar', 'distinguished');
    }

    public function test_member_cannot_change_personal_url_after_publication(): void
    {
        [$member, $application, $profile] = $this->publishLiving('accomplished');
        $before = $profile->fresh()->slug;

        $this->actingAs($member)->post(route('applications.profile-url.update', $application), [
            'slug' => 'post-publish-change',
        ])->assertSessionHasErrors('slug');

        $this->assertSame($before, $profile->fresh()->slug);
    }

    public function test_database_uniqueness_protects_against_concurrent_collision(): void
    {
        [, , $profile] = $this->publishLiving('emerging');

        $this->expectException(QueryException::class);
        DB::table('profiles')->insert([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'full_name' => 'Collision',
            'display_name' => 'Collision',
            'profession' => '',
            'slug' => $profile->fresh()->slug,
            'display_phone_consent' => false,
            'display_email_consent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reserved_routes_cannot_be_selected(): void
    {
        [$member, $application] = $this->prepareUnpublishedLiving('accomplished');

        foreach (['admin', 'login', 'applications', 'search', 'in-memoriam'] as $reserved) {
            $this->actingAs($member)->post(route('applications.profile-url.update', $application), [
                'slug' => $reserved,
            ])->assertSessionHasErrors('slug');
        }
    }

    public function test_unsafe_and_path_traversal_slug_values_are_rejected(): void
    {
        $urls = app(ProfileUrlService::class);

        foreach (['../etc', 'foo/bar', 'foo\\bar', 'foo?x=1', 'foo#frag', "foo\nbar", ''] as $bad) {
            $result = $urls->validatePersonalSlugCandidate($bad);
            $this->assertFalse($result['ok'], 'Expected rejection for: '.$bad);
        }
    }

    public function test_member_cannot_modify_another_members_url(): void
    {
        [$owner, $application, $profile] = $this->publishLiving('accomplished');
        $before = $profile->fresh()->slug;
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->post(route('applications.profile-url.update', $application), [
            'slug' => 'stolen-slug',
            'profile_id' => $profile->id,
            'application_id' => $application->id,
        ])->assertForbidden();

        $this->assertSame($before, $profile->fresh()->slug);
        $this->assertSame($owner->id, (int) $profile->fresh()->user_id);
    }

    public function test_historical_url_redirects_to_same_profile_and_cannot_be_reassigned(): void
    {
        [$member, , $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        $old = (string) $profile->fresh()->slug;

        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');
        $this->assertSame('arun.kumar', $profile->fresh()->slug);

        $this->get('/p/'.$old)->assertRedirect('/arun.kumar');

        [, , $other] = $this->publishLiving('distinguished');

        $this->expectException(\InvalidArgumentException::class);
        app(ProfileUrlService::class)->selectPersonalSlug($other->fresh(), $admin, $old, 'distinguished');
    }

    public function test_changing_personal_url_makes_new_url_canonical_and_old_redirects(): void
    {
        [, , $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar.nair', 'accomplished');

        $this->assertSame('arun.kumar.nair', $profile->fresh()->slug);
        $this->get('/p/arun.kumar')->assertRedirect('/arun.kumar.nair');
        $this->get('/arun.kumar.nair')->assertOk()->assertSee('arun.kumar.nair', false);
    }

    public function test_historical_url_redirects_do_not_auto_expire(): void
    {
        [, , $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        $old = (string) $profile->fresh()->slug;

        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');

        $redirect = SlugRedirect::query()->where('old_slug', $old)->firstOrFail();
        $this->assertNull($redirect->expires_at);
        $this->assertSame((int) $profile->id, (int) $redirect->redirectable_id);

        $this->travel(400)->days();

        $this->get('/p/'.$old)->assertRedirect('/arun.kumar');
        $this->assertTrue(
            SlugRedirect::query()
                ->where('old_slug', $old)
                ->where('redirectable_id', $profile->id)
                ->whereNull('expires_at')
                ->exists()
        );
    }

    public function test_emerging_upgrade_preserves_six_char_until_personal_url_chosen(): void
    {
        [$member, $application, $profile] = $this->publishLiving('emerging');
        $admin = User::factory()->admin()->create();
        $six = (string) $profile->fresh()->slug;

        $application->forceFill(['package_tier' => 'accomplished'])->save();

        $this->assertSame($six, $profile->fresh()->slug);

        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');
        $this->assertSame('arun.kumar', $profile->fresh()->slug);
        $this->get('/p/'.$six)->assertRedirect('/arun.kumar');
    }

    public function test_qr_contains_current_canonical_public_url_and_updates_on_change(): void
    {
        [$member, $application, $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        $qr = app(ProfileQrCodeService::class);

        $firstPayload = $qr->encodedPayload($profile->fresh());
        $this->assertSame('https://www.jannayaks.in/'.$profile->fresh()->slug, $firstPayload);
        $this->assertStringNotContainsString('/applications/', $firstPayload);
        $this->assertStringNotContainsString('/preview', $firstPayload);
        $this->assertStringNotContainsString('/admin', $firstPayload);

        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');
        $second = $qr->encodedPayload($profile->fresh());
        $this->assertSame('https://www.jannayaks.in/arun.kumar', $second);

        $this->actingAs($member)
            ->get(route('applications.profile-qr', $application))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_unpublished_profiles_are_not_publicly_exposed(): void
    {
        $member = User::factory()->create();
        Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Hidden',
            'display_name' => 'Hidden',
            'profession' => '',
            'slug' => 'hidden-slug',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->get('/p/hidden-slug')->assertNotFound();

        $profile = Profile::query()->where('slug', 'hidden-slug')->firstOrFail();
        $profile->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => now(),
        ])->save();

        $this->get('/p/hidden-slug')->assertNotFound();
    }

    public function test_editors_and_support_cannot_reassign_canonical_urls_via_member_endpoint(): void
    {
        [$owner, $application, $profile] = $this->publishLiving('accomplished');
        $before = $profile->fresh()->slug;

        $editor = User::factory()->editor()->create();
        $support = User::factory()->support()->create();

        $this->actingAs($editor)->post(route('applications.profile-url.update', $application), [
            'slug' => 'editor-hijack',
        ])->assertForbidden();

        $this->actingAs($support)->post(route('applications.profile-url.update', $application), [
            'slug' => 'support-hijack',
        ])->assertForbidden();

        $this->assertSame($before, $profile->fresh()->slug);
        $this->assertSame($owner->id, (int) $profile->user_id);
    }

    public function test_admin_can_inspect_profile_url_fields_on_application_view_without_reassignment_action(): void
    {
        [$member, $application, $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');

        $this->actingAs($admin);

        $html = $this->get('/admin/applications/'.$application->id)->assertOk()->getContent();
        $this->assertStringContainsString('arun.kumar', $html);
        $this->assertStringContainsString('Profile URL (read-only)', $html);
        $this->assertStringNotContainsString('Reassign URL', $html);
        $this->assertStringNotContainsString('applications.profile-url.update', $html);
    }

    public function test_case_variants_are_treated_as_the_same_personal_url(): void
    {
        [, , $profile] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'Arun-Kumar', 'accomplished');
        $this->assertSame('arun-kumar', $profile->fresh()->slug);

        [, , $other] = $this->publishLiving('distinguished');
        $result = app(ProfileUrlService::class)->validatePersonalSlugCandidate(
            'ARUN-KUMAR',
            (int) $other->id,
        );
        $this->assertFalse($result['ok']);
    }

    public function test_historical_redirect_never_points_at_a_different_profile(): void
    {
        [, , $profileA] = $this->publishLiving('accomplished');
        $admin = User::factory()->admin()->create();
        $oldA = (string) $profileA->fresh()->slug;
        app(ProfileUrlService::class)->selectPersonalSlug($profileA->fresh(), $admin, 'arun.kumar', 'accomplished');

        [, , $profileB] = $this->publishLiving('accomplished');
        $this->assertNotSame('arun.kumar', $profileB->fresh()->slug);

        $redirect = SlugRedirect::query()->where('old_slug', $oldA)->firstOrFail();
        $this->assertSame((int) $profileA->id, (int) $redirect->redirectable_id);

        $this->get('/p/'.$oldA)->assertRedirect('/arun.kumar');
        $this->get('/'.$profileB->fresh()->slug)->assertOk()->assertDontSee('arun.kumar', false);
    }

    /**
     * Unpublished living profile so members may still choose a personal URL before approval/publication.
     *
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function prepareUnpublishedLiving(string $tier): array
    {
        $member = User::factory()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Arun Kumar Nair',
            'display_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => 'Leader',
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
        ]);

        return [$member, $application->fresh(), $profile->fresh()];
    }

    /**
     * Lightweight published living profile with canonical slug (via real publish path).
     *
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function publishLiving(string $tier): array
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Arun Kumar Nair',
            'display_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => 'Leader',
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        // Exceptional admin publication path — exercises real slug assignment on publish
        // without the heavy P10/P11 AI + preview fixture stack.
        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
        $profile = Profile::query()->findOrFail($published->profile_id);

        return [$member, $published->fresh(), $profile->fresh()];
    }
}
