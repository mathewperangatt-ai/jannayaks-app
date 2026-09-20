<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\GeoState;
use App\Models\Profile;
use App\Models\ProfileGeography;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileUrlService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileStateAttributeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_profile_persists_mandatory_keralam_state(): void
    {
        $profile = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Arun Kumar Nair',
            'display_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->assertDatabaseHas('profile_geographies', [
            'profile_id' => $profile->id,
            'state_region_name' => 'Keralam',
            'country_code' => 'IN',
        ]);
        $this->assertSame('Keralam', $profile->fresh()->geography?->state_region_name);
        $this->assertSame('Keralam', GeoState::query()->value('name'));
    }

    public function test_blank_or_legacy_kerala_state_normalizes_to_keralam(): void
    {
        $profile = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Legacy State Holder',
            'display_name' => 'Legacy State Holder',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $geography = $profile->geography;
        $this->assertNotNull($geography);

        $geography->forceFill(['state_region_name' => 'Kerala'])->save();
        $this->assertSame('Keralam', $geography->fresh()->state_region_name);

        $geography->forceFill(['state_region_name' => ''])->save();
        $this->assertSame('Keralam', $geography->fresh()->state_region_name);
        $this->assertSame('Keralam', ProfileGeography::normalizedStateName(''));
        $this->assertSame('Keralam', ProfileGeography::normalizedStateName('Kerala'));
    }

    public function test_database_rejects_a_null_state_on_profile_geography(): void
    {
        $profile = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Null State Holder',
            'display_name' => 'Null State Holder',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->expectException(QueryException::class);

        DB::table('profile_geographies')
            ->where('profile_id', $profile->id)
            ->update(['state_region_name' => null]);
    }

    public function test_personal_profile_url_is_root_slug_and_never_includes_state(): void
    {
        [$member, $application, $profile] = $this->publishLiving('accomplished', 'Arun Kumar Nair');
        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $admin, 'arun.kumar', 'accomplished');

        $fresh = $profile->fresh();
        $path = app(ProfileUrlService::class)->canonicalPublicPath($fresh);

        $this->assertSame('/arun.kumar', $path);
        $this->assertSame('Keralam', $fresh->geography?->state_region_name);
        $this->assertSame($member->id, (int) $fresh->user_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);

        $this->get('/arun.kumar')->assertOk()->assertSee('Arun Kumar Nair', false);
        $this->get('/keralam/arun.kumar')->assertNotFound();
        $this->get('/p/keralam/arun.kumar')->assertNotFound();
        $this->assertStringNotContainsString('/keralam/arun.kumar', $this->get('/arun.kumar')->getContent());
    }

    public function test_public_state_surfaces_use_keralam_and_not_a_url_prefix(): void
    {
        $this->get('/')->assertOk()->assertSee('Keralam ▾', false);
        $this->get(route('gallery.index'))->assertOk()->assertSee('Keralam', false);
        $this->get(route('search.index'))->assertOk()->assertSee('Keralam', false);
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function publishLiving(string $tier, string $fullName): array
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
