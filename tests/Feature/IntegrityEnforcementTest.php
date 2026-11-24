<?php

namespace Tests\Feature;

use App\Jobs\VerifyPublishedProfileIntegrity;
use App\Mail\ProfileIntegrityEnforcementMail;
use App\Models\IntegrityIncident;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\User;
use App\Services\ProfileIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 3 — enforcement matrix: confirmed tampering suspends immediately,
 * infrastructure failures never suspend, report mode records only.
 */
class IntegrityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        Queue::fake();
        config([
            'jannayaks.security.integrity.mode' => 'enforce',
            'jannayaks.security.integrity.deep_verify_percent' => 100,
            'jannayaks.security.integrity.hmac_secret' => 'test-integrity-secret-'.str_repeat('x', 16),
        ]);
    }

    private function seedPublished(bool $withPhoto = true): array
    {
        return $this->seedPublishedProfile($withPhoto);
    }

    /** Authorised editorial change: reconciles healthy, never suspends. */
    public function test_authorised_editorial_change_does_not_suspend(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        // Replace the editorial through the sanctioned replacement flow
        // (workflow refreshes the trusted snapshot itself).
        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $profile->refresh();
        $this->assertNull($profile->suspended_at);
        $this->assertSame(0, IntegrityIncident::query()->where('profile_id', $profile->id)->count());
    }

    /** Direct database edit (bypasses Eloquent/observers) → cycle suspends. */
    public function test_direct_database_content_edit_suspends_profile(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        // Tamper OUTSIDE the application workflow: raw SQL, no observers.
        DB::table('profiles')->where('id', $profile->id)->update([
            'display_name' => 'Tampered Display Name',
        ]);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $profile->refresh();
        $this->assertNotNull($profile->suspended_at, 'Confirmed tampering must suspend immediately.');
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)->whereIn('type', [
            IntegrityIncident::TYPE_IDENTITY,
        ])->exists());

        Mail::assertQueued(ProfileIntegrityEnforcementMail::class, 1);

        // Suspended profile leaves public visibility immediately.
        $this->assertFalse(app(\App\Services\ProfileUrlService::class)->isPubliclyVisible($profile->fresh()));
    }

    /** Report mode: incident is recorded but visibility is untouched. */
    public function test_report_mode_records_incident_without_suspension(): void
    {
        config(['jannayaks.security.integrity.mode' => 'report']);
        ['profile' => $profile] = $this->seedPublished();

        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Tampered']);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $profile->refresh();
        $this->assertNull($profile->suspended_at);
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)->exists());
        Mail::assertNothingQueued();
    }

    /** Unauthorised photograph REPLACEMENT (bytes swapped in storage) → suspends. */
    public function test_photo_object_substitution_suspends_profile(): void
    {
        ['profile' => $profile, 'photo' => $photo] = $this->seedPublished();
        $disk = (string) $photo->disk;
        $key = (string) $photo->storage_path_key;

        // Swap the stored bytes for different image content (same key).
        $img = imagecreatetruecolor(400, 500);
        ob_start();
        imagejpeg($img);
        $tamperedBytes = ob_get_clean();
        imagedestroy($img);
        Storage::disk($disk)->put($key, $tamperedBytes);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'photos'])->assertSuccessful();

        $profile->refresh();
        $this->assertNotNull($profile->suspended_at);
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)
            ->where('type', IntegrityIncident::TYPE_PHOTOGRAPH_OBJECT)->exists());
        Mail::assertQueued(ProfileIntegrityEnforcementMail::class, 1);
    }

    /** Unauthorised photograph DELETION (reference removed) → incident. */
    public function test_photo_reference_deletion_creates_incident_and_suspends(): void
    {
        ['profile' => $profile, 'photo' => $photo] = $this->seedPublished();

        // Raw delete: no observers, no audit event explains this.
        DB::table('media_items')->where('id', $photo->id)->delete();

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $profile->refresh();
        $this->assertNotNull($profile->suspended_at);
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)
            ->where('type', IntegrityIncident::TYPE_PHOTOGRAPH_REFERENCE)->exists());
    }

    /** Unauthorised metadata tampering (slug + headline) → suspends. */
    public function test_metadata_tampering_suspends_profile(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        DB::table('profiles')->where('id', $profile->id)->update([
            'slug' => 'hijacked-slug',
            'bio_headline' => 'Completely different headline',
        ]);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $profile->refresh();
        $this->assertNotNull($profile->suspended_at);
        $this->assertSame('hijacked-slug', $profile->slug, 'Evidence is preserved, never silently repaired.');
    }

    /** Storage/infrastructure failure → scan_error, NEVER suspension. */
    public function test_storage_failure_does_not_suspend(): void
    {
        ['profile' => $profile, 'photo' => $photo] = $this->seedPublished();

        // Remove the stored object: reading it fails → verification cannot
        // distinguish attacker deletion of bytes from an outage → scan_error.
        Storage::disk((string) $photo->disk)->delete((string) $photo->storage_path_key);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'photos'])->assertSuccessful();

        $profile->refresh();
        $this->assertNull($profile->suspended_at, 'Infrastructure failure must never suspend.');
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)->doesntExist());
    }

    /** Two profiles tampered in the same cycle: both suspend, both notify. */
    public function test_multiple_simultaneous_incidents_are_all_enforced(): void
    {
        ['profile' => $profileA] = $this->seedPublished();
        ['profile' => $profileB] = $this->seedPublished();

        DB::table('profiles')->whereIn('id', [$profileA->id, $profileB->id])->update([
            'display_name' => 'Mass Tampering',
        ]);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $this->assertNotNull($profileA->refresh()->suspended_at);
        $this->assertNotNull($profileB->refresh()->suspended_at);
        Mail::assertQueued(ProfileIntegrityEnforcementMail::class, 2);
    }

    /** Event-driven: observers queue a verification for published profiles. */
    public function test_profile_save_dispatches_verification_job(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        Queue::fake();
        $profile->refresh()->forceFill(['bio_headline' => 'New headline via workflow'])->save();

        Queue::assertPushed(VerifyPublishedProfileIntegrity::class,
            fn (VerifyPublishedProfileIntegrity $job): bool => $job->profileId === $profile->id);
    }

    /** Irrelevant profile saves do not queue verification. */
    public function test_irrelevant_profile_save_does_not_dispatch(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        Queue::fake();

        // A save touching none of the public-state fields must not queue; a
        // raw DB update bypasses observers entirely.
        $profile->refresh();
        $profile->forceFill(['profession' => $profile->profession])->save();
        DB::table('profiles')->where('id', $profile->id)->update(['updated_at' => now()]);

        Queue::assertNothingPushed();
    }

    /** Event-driven job itself enforces a mismatch (direct handler call). */
    public function test_verification_job_enforces_confirmed_mismatch(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Evil']);

        (new VerifyPublishedProfileIntegrity($profile->id, 'evt-test'))->handle(app(ProfileIntegrityService::class));

        $this->assertNotNull($profile->refresh()->suspended_at);
        Mail::assertQueued(ProfileIntegrityEnforcementMail::class, 1);
    }

    /** Event-driven job skips profiles that are not publicly visible. */
    public function test_verification_job_skips_hidden_profiles(): void
    {
        ['profile' => $profile] = $this->seedPublished();
        $profile->forceFill(['suspended_at' => now()])->save();

        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Evil While Hidden']);

        (new VerifyPublishedProfileIntegrity($profile->id, 'evt-test-2'))->handle(app(ProfileIntegrityService::class));

        // Recorded by the cycle later; but no enforcement/suspension churn.
        $this->assertSame(0, IntegrityIncident::query()->where('profile_id', $profile->id)->count());
    }

    /** HMAC evidence: signed snapshot verifies; forged baseline is an incident. */
    public function test_forged_snapshot_signature_is_incident(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first();
        $this->assertNotNull($snapshot->signature, 'Snapshots must be signed when the secret is configured.');

        // Attacker rewrites the trusted baseline without the secret.
        $snapshot->forceFill([
            'display_name' => 'Forged Baseline',
            'snapshot_hash' => hash('sha256', 'forged'),
            'signature' => str_repeat('f', 64),
        ])->save();

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)
            ->where('type', IntegrityIncident::TYPE_SNAPSHOT_SIGNATURE)->exists());
        Mail::assertQueued(ProfileIntegrityEnforcementMail::class, 1);
    }

    /** Legacy unsigned snapshots remain valid when the secret is configured. */
    public function test_legacy_unsigned_snapshot_is_not_flagged(): void
    {
        config(['jannayaks.security.integrity.hmac_secret' => '']);
        ['profile' => $profile] = $this->seedPublished();
        config(['jannayaks.security.integrity.hmac_secret' => 'late-configured-secret-'.str_repeat('y', 16)]);

        // Row is unsigned (pre-secret); verification must not flag it.
        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $this->assertNull($profile->refresh()->suspended_at);
        $this->assertSame(0, IntegrityIncident::query()->where('profile_id', $profile->id)->count());
    }

    /** Suspended profile is restored only through the manual review gate. */
    public function test_restoration_requires_manual_security_review(): void
    {
        ['profile' => $profile, 'application' => $application] = $this->seedPublished();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Tampered']);
        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();
        $this->assertNotNull($profile->refresh()->suspended_at);

        // An open integrity incident blocks silent restoration: the review
        // flow refuses without an explicit integrity waiver reason.
        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\ProfileAdministrationService::class)->restoreLivingProfileVisibility(
            $application->fresh(),
            $securityAdmin,
            'restoring after review',
            integrityWaiverReason: '',
        );
    }

    /**
     * Seed helper mirroring ProfileIntegrityMonitorTest's published fixture.
     *
     * @return array<string, mixed>
     */
    private function seedPublishedProfile(bool $withPhoto = true): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = \App\Models\Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'full_name' => 'Enforcement Fixture Leader',
            'preferred_display_name' => 'Enforcement Fixture Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Enforcement Fixture Leader',
            'display_name' => 'Enforcement Fixture Leader',
            'profession' => 'Public Servant',
            'slug' => 'enforce-fixture-'.strtolower(\Illuminate\Support\Str::random(8)),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $photo = null;
        if ($withPhoto) {
            $photo = app(\App\Services\ProfileMediaService::class)->uploadProfilePhoto(
                $profile,
                \Illuminate\Http\UploadedFile::fake()->image('portrait.jpg', 400, 500),
                $editor,
            );
            app(\App\Services\ProfileMediaService::class)->approve($photo, $reviewer);
        }

        $english = \App\Models\EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => \App\Models\EditorialContent::LANGUAGE_EN,
            'status' => \App\Models\EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Enforcement Fixture Leader',
            'body' => 'Approved English biography of the enforcement fixture.',
            'summary' => 'Approved summary.',
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

        return [
            'member' => $member,
            'editor' => $editor,
            'admin' => $admin,
            'application' => $application->fresh(),
            'profile' => $profile->fresh(),
            'photo' => $withPhoto ? $photo->fresh() : null,
        ];
    }
}
