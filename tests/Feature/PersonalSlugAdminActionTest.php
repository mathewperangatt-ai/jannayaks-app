<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\ReservedSlug;
use App\Models\SlugRedirect;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The one-off published-URL correction uses ProfileUrlService::selectPersonalSlug().
 * There is no general-purpose admin form for assigning profile URLs.
 */
class PersonalSlugAdminActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_audited_service_correction_reserves_the_old_slug_and_redirects(): void
    {
        [$admin, $member, $application, $profile] = $this->publishedProfile();

        $updated = app(ProfileUrlService::class)->selectPersonalSlug(
            $profile,
            $admin,
            'ratish.ravindra',
            (string) $application->package_tier,
        );

        $this->assertSame('ratish.ravindra', $updated->slug);
        $this->assertSame('4IZ2UF', $updated->previous_slug);
        $this->assertTrue(
            SlugRedirect::query()
                ->where('old_slug', '4IZ2UF')
                ->where('new_slug', 'ratish.ravindra')
                ->where('redirectable_id', $profile->id)
                ->exists()
        );
        $this->assertTrue(
            StaffActionLog::query()
                ->where('action', 'profile_url.slug_changed')
                ->where('actor_user_id', $admin->id)
                ->where('subject_id', $profile->id)
                ->where('before->slug', '4IZ2UF')
                ->where('after->slug', 'ratish.ravindra')
                ->exists()
        );

        $this->get('/4IZ2UF')->assertRedirect('/ratish.ravindra');
        $this->get('/ratish.ravindra')->assertOk()->assertSee('Ratish Ravindra', false);

        $html = $this->actingAs($admin)->get('/admin/applications/'.$application->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('Set personal profile URL', $html);
        $this->assertStringNotContainsString('assignPersonalProfileUrl', $html);
        $this->assertSame($member->id, (int) $updated->user_id);
    }

    public function test_member_cannot_replace_a_published_url_and_misleading_slugs_are_refused(): void
    {
        [$admin, $member, , $profile] = $this->publishedProfile();
        ReservedSlug::query()->create([
            'slug' => 'ratish',
            'category' => ReservedSlug::CATEGORY_OTHER,
            'reason' => 'Held for a manual check',
        ]);
        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'published',
            'full_name' => 'Ratish Ravindra',
            'display_name' => 'Ratish Ravindra',
            'profession' => '',
            'slug' => 'ratish.ravindra10',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        foreach ([$member, $admin] as $actor) {
            foreach (['ratish', 'ratish.ravindra10', 'superstar'] as $slug) {
                try {
                    app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $actor, $slug, 'distinguished');
                    $this->fail('Slug ['.$slug.'] must be refused.');
                } catch (InvalidArgumentException) {
                    $this->assertSame('4IZ2UF', $profile->fresh()->slug);
                }
            }
        }

        $this->assertSame(0, SlugRedirect::query()->count());
    }

    /**
     * @return array{0: User, 1: User, 2: Application, 3: Profile}
     */
    private function publishedProfile(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Ratish Ravindra',
            'display_name' => 'Ratish Ravindra',
            'profession' => '',
            'slug' => '4IZ2UF',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Ratish Ravindra',
            'preferred_display_name' => 'Ratish Ravindra',
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_PUBLISHED,
        ]);

        return [$admin, $member, $application->fresh(), $profile->fresh()];
    }
}
