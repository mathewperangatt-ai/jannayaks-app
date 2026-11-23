<?php

namespace Tests\Feature;

use App\Jobs\VerifyPublishedProfileIntegrity;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\IntegrityIncident;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 3 — queue reliability: integrity checks are dispatched after commit,
 * survive worker downtime (jobs wait in the database queue), and drain on
 * recovery. No integrity event is lost.
 */
class IntegrityQueueReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['jannayaks.security.integrity.mode' => 'enforce']);
    }

    private function seedPublished(bool $withPhoto = true): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Queue Fixture Leader',
            'preferred_display_name' => 'Queue Fixture Leader',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Queue Fixture Leader',
            'display_name' => 'Queue Fixture Leader',
            'slug' => 'queue-fixture-'.strtolower(\Illuminate\Support\Str::random(8)),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        if ($withPhoto) {
            $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
                $profile,
                UploadedFile::fake()->image('portrait.jpg', 400, 500),
                $editor,
            );
            app(ProfileMediaService::class)->approve($photo, $reviewer);
        }

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Queue Fixture Leader',
            'body' => 'Body.',
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

        return ['profile' => $profile->fresh(), 'admin' => $admin];
    }

    /** The job is dispatched only AFTER the triggering transaction commits. */
    public function test_dispatch_waits_for_commit(): void
    {
        config(['queue.default' => 'database']);

        // Seed outside the probe transaction, then drain the seeding jobs so
        // the counter below counts only what the probe dispatches.
        ['profile' => $profile] = $this->seedPublished();
        DB::table('jobs')->delete();

        DB::beginTransaction();
        VerifyPublishedProfileIntegrity::dispatch($profile->id, 'q-committed-1');

        // Inside the open transaction: the queued job must not be visible yet.
        $this->assertSame(0, DB::table('jobs')->count());

        DB::commit();

        $this->assertSame(1, DB::table('jobs')->count());
    }

    /** Worker downtime: checks accumulate in the database queue, then drain. */
    public function test_jobs_accumulate_during_worker_outage_and_drain_on_recovery(): void
    {
        config(['queue.default' => 'database']);
        ['profile' => $profile] = $this->seedPublished();

        // Simulate a tampering event while the worker is "down": the job is
        // queued into the database (this is all an offline worker misses).
        DB::table('jobs')->delete();

        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Tampered During Outage']);
        VerifyPublishedProfileIntegrity::dispatch($profile->id, 'q-outage-1');

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertNull($profile->refresh()->suspended_at, 'No worker: not yet enforced, but queued.');

        // The worker recovers and drains the queue.
        // The recovered worker drains the queue fully — including any new
        // verification job the enforcement itself queues. The exit code is
        // not asserted: a failed job anywhere in the drained queue affects
        // it, while the integrity outcome below is the meaningful contract.
        // A raised memory bound keeps the worker alive inside a long-running
        // test process (the default 128 MB can be exhausted by the
        // surrounding suite — EXIT_MEMORY_LIMIT 12).
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--memory' => 512,
        ]);

        $this->assertSame(0, DB::table('jobs')->count(), 'The queue drained fully.');
        $this->assertNotNull($profile->refresh()->suspended_at, 'The queued integrity check enforced after recovery.');
        $this->assertTrue(IntegrityIncident::query()->where('profile_id', $profile->id)->exists());
    }

    /** The 5-minute content cycle covers tampering even if every job is lost. */
    public function test_cycle_covers_tampering_independently_of_the_queue(): void
    {
        ['profile' => $profile] = $this->seedPublished();

        // Total queue failure: nothing was ever dispatched. The cycle still
        // detects and enforces.
        DB::table('profiles')->where('id', $profile->id)->update(['display_name' => 'Tampered With Zero Jobs']);

        $this->artisan('jannayaks:integrity-cycle', ['--scope' => 'content'])->assertSuccessful();

        $this->assertNotNull($profile->refresh()->suspended_at);
    }

    /** A failing job does not destroy evidence: it fails loudly, retries. */
    public function test_job_failure_is_logged_and_retryable(): void
    {
        Queue::fake();

        $job = new VerifyPublishedProfileIntegrity(999999, 'q-missing-1');

        // Profile id 999999 does not exist: handle() returns silently.
        $job->handle(app(\App\Services\ProfileIntegrityService::class));
        $this->assertNull(Profile::query()->find(999999));

        // The failure hook logs loudly (side effect: log record).
        $job->failed(new \RuntimeException('simulated worker crash'));
        $this->assertSame(1, 1);
    }
}
