<?php

namespace Tests\Feature;

use App\Models\IntegrityIncident;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 3 follow-up: the production watchdog reported 5 integrity mismatches
 * that were legitimately authorised out-of-band changes (owner-approved demo
 * cleanup via direct SQL; an old headline drift). The operator needs a
 * sanctioned way to REFRESH a trusted baseline after such a change — the
 * explicit --force flag — while the default remains refusal (baselines are
 * never silently overwritten).
 */
class IntegrityBaselineRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['jannayaks.security.integrity.mode' => 'report']);
    }

    private function seedPublishedProfile(): Profile
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $admin = User::factory()->admin()->create();

        $application = \App\Models\Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Baseline Refresh Fixture',
            'preferred_display_name' => 'Baseline Refresh Fixture',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Baseline Refresh Fixture',
            'display_name' => 'Baseline Refresh Fixture',
            'slug' => 'rebase-'.strtolower(\Illuminate\Support\Str::random(8)),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Baseline Refresh Fixture',
            'body' => 'Body.',
            'summary' => 'Summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => \App\Models\Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(\App\Services\ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return $profile->fresh();
    }

    public function test_establish_baseline_refuses_to_overwrite_by_default(): void
    {
        $profile = $this->seedPublishedProfile();
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $originalHash = $snapshot->snapshot_hash;

        // An authorised out-of-band change made the live state differ.
        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Renamed After Authorised Change']);

        $exit = Artisan::call('jannayaks:verify-profile-integrity', [
            '--profile' => (string) $profile->id,
            '--establish-baseline' => true,
        ]);

        $this->assertSame(0, $exit, 'Refusal is a successful no-op, not an error.');
        $snapshot->refresh();
        $this->assertSame($originalHash, $snapshot->snapshot_hash, 'Default path never overwrites.');
    }

    public function test_force_flag_refreshes_the_baseline_after_authorised_change(): void
    {
        $profile = $this->seedPublishedProfile();
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $originalHash = $snapshot->snapshot_hash;

        // The authorised out-of-band change (e.g. owner-approved cleanup).
        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Renamed After Authorised Change']);

        $exit = Artisan::call('jannayaks:verify-profile-integrity', [
            '--profile' => (string) $profile->id,
            '--establish-baseline' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);
        $snapshot->refresh();
        $this->assertNotSame($originalHash, $snapshot->snapshot_hash, 'The baseline now reflects the authorised change.');
        $this->assertSame('operator_establish_baseline', (string) $snapshot->source_event);
        $this->assertSame('Renamed After Authorised Change', (string) $snapshot->display_name);

        // The force action is audit-logged.
        $this->assertTrue(StaffActionLog::query()
            ->where('action', 'integrity.baseline_force_refreshed')
            ->where('subject_id', $profile->id)
            ->exists());

        // The next verification reconciles healthy against the refreshed baseline.
        $result = app(\App\Services\ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'post-refresh-run', false);
        $this->assertSame('healthy', $result['classification']);
        $this->assertNull($profile->refresh()->suspended_at);
    }

    public function test_force_refresh_does_not_touch_open_incidents_or_snapshots_of_other_profiles(): void
    {
        $profile = $this->seedPublishedProfile();
        $other = $this->seedPublishedProfile();
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $otherSnapshot = ProfileIntegritySnapshot::query()->where('profile_id', $other->id)->firstOrFail();

        // An open incident on this profile (as the production mismatch produced).
        IntegrityIncident::query()->create([
            'profile_id' => $profile->id,
            'run_id' => 'test-run',
            'type' => IntegrityIncident::TYPE_IDENTITY,
            'mode' => 'report',
            'detection_reason' => 'unexplained drift',
            'expected_state' => [],
            'observed_state' => [],
            'detection_fingerprint' => 'fp-'.\Illuminate\Support\Str::random(12),
            'detected_at' => now(),
        ]);

        $exit = Artisan::call('jannayaks:verify-profile-integrity', [
            '--profile' => (string) $profile->id,
            '--establish-baseline' => true,
            '--force' => true,
        ]);

        $this->assertSame(0, $exit);

        // Evidence is preserved: the open incident remains open for review.
        $this->assertSame(1, IntegrityIncident::query()
            ->where('profile_id', $profile->id)->where('status', 'open')->count());

        // The other profile's baseline is untouched.
        $this->assertSame(
            $otherSnapshot->snapshot_hash,
            ProfileIntegritySnapshot::query()->where('profile_id', $other->id)->first()->snapshot_hash,
        );
        $this->assertSame(1, ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->count());
    }
}
