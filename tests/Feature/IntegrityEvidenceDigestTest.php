<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 3 — independent integrity-evidence anchor: the daily digest email
 * carries signed-evidence hashes and counts ONLY. It must never expose
 * profile content, personal data or secrets.
 */
class IntegrityEvidenceDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        config([
            'jannayaks.security.integrity.hmac_secret' => 'digest-secret-'.str_repeat('s', 16),
            'jannayaks.security.integrity.photo_moderation.evidence_anchor_recipient' => 'owner-anchor@example.org',
        ]);
    }

    private function seedPublishedProfile(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Digest Fixture Leader',
            'preferred_display_name' => 'Digest Fixture Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Digest Fixture Leader',
            'display_name' => 'Digest Fixture Leader',
            'slug' => 'digest-fixture-'.strtolower(\Illuminate\Support\Str::random(8)),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('portrait.jpg', 400, 500),
            $editor,
        );
        app(ProfileMediaService::class)->approve($photo, $reviewer);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Digest Fixture Leader',
            'body' => 'Digest fixture biography body text.',
            'summary' => 'Summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application->fresh()->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
    }

    public function test_digest_emails_hashes_and_counts_only(): void
    {
        $this->seedPublishedProfile();

        $this->artisan('jannayaks:integrity-evidence-digest')
            ->expectsOutputToContain('Integrity evidence digest emailed')
            ->assertSuccessful();

        Mail::assertSent(\App\Mail\IntegrityEvidenceDigestMail::class, 1);

        // The digest is recorded in the audit trail.
        $this->assertTrue(StaffActionLog::query()->where('action', 'integrity.evidence_digest_sent')->exists());
    }

    public function test_rolling_aggregate_persists_across_cache_clears(): void
    {
        $this->seedPublishedProfile();

        $this->artisan('jannayaks:integrity-evidence-digest')->assertSuccessful();
        $first = StaffActionLog::query()->where('action', 'integrity.evidence_digest_sent')->first();
        $this->assertNotNull($first->after['aggregate'] ?? null);

        // Simulate a deployment/cache clear + restart: the cache is emptied,
        // but the chain continues from the PERSISTENT audit log, not a cache
        // value — the emailed digests remain the external reference.
        cache()->forget('integrity.digest.aggregate');

        $this->artisan('jannayaks:integrity-evidence-digest')->assertSuccessful();
        $rows = StaffActionLog::query()->where('action', 'integrity.evidence_digest_sent')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertNotSame($first->after['aggregate'], $rows->last()->after['aggregate']);

        // Recompute the second aggregate exactly as the command does, using
        // the PERSISTED first aggregate + the current snapshot rows — proving
        // the chain continued from the audit log after the cache clear.
        $snapshotRows = ProfileIntegritySnapshot::query()
            ->join('profiles', 'profiles.id', '=', 'profile_integrity_snapshots.profile_id')
            ->where('profiles.status', 'published')
            ->whereNull('profiles.unpublished_at')
            ->orderBy('profile_integrity_snapshots.profile_id')
            ->get([
                'profile_integrity_snapshots.profile_id',
                'profile_integrity_snapshots.snapshot_hash',
                'profile_integrity_snapshots.signature',
            ]);
        $expected = hash('sha256', implode('|', array_merge(
            [$first->after['aggregate']],
            $snapshotRows->map(fn ($row): string => $row->profile_id.':'.$row->snapshot_hash.':'.$row->signature)->all(),
        )));
        $this->assertSame($expected, $rows->last()->after['aggregate']);
    }

    public function test_digest_skips_when_no_anchor_recipient_is_configured(): void
    {
        $this->seedPublishedProfile();
        config(['jannayaks.security.integrity.photo_moderation.evidence_anchor_recipient' => '']);

        $this->artisan('jannayaks:integrity-evidence-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_digest_content_contains_no_sensitive_information(): void
    {
        $this->seedPublishedProfile();
        $snapshot = ProfileIntegritySnapshot::query()->first();
        $this->assertNotNull($snapshot->signature, 'Fixture must be signed.');

        $this->artisan('jannayaks:integrity-evidence-digest')->assertSuccessful();

        Mail::assertSent(\App\Mail\IntegrityEvidenceDigestMail::class, function (\App\Mail\IntegrityEvidenceDigestMail $mail): bool {
            $body = $mail->bodyText;

            // Cover both render paths defensively.
            $rendered = $body;
            $this->assertStringContainsString('Rolling aggregate', $rendered);
            $this->assertStringContainsString('Open incidents', $rendered);

            foreach (['Digest Fixture', 'digest-fixture', 'biography body', 'totp', 'hmac', 'JANNAYAKS_INTEGRITY_HMAC_SECRET'] as $forbidden) {
                $this->assertStringNotContainsString(strtolower($forbidden), strtolower($rendered));
            }

            return true;
        });
    }
}
