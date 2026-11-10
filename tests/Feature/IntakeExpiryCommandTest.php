<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ConsentRecord;
use App\Models\EditorialContent;
use App\Models\InterviewAnswer;
use App\Models\MediaItem;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\SlugReservation;
use App\Models\SourceMaterial;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ConsentRecordingService;
use App\Services\IntakeExpiryService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Day-11 erasure of incomplete Online Interview applications:
 * scope, protections, file handling, retained evidence and retries.
 */
class IntakeExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        config(['jannayaks.ai.image_enhancement.enabled' => false]);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_nine_november_start_is_valid_through_day_ten_and_erased_on_day_eleven(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $materialPath = $this->material($seed)['path'];
        $photoPath = $this->photo($seed)['path'];

        // Day 10 (18 November): valid, nothing reported, nothing changed.
        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-18'])
            ->expectsOutputToContain('No incomplete applications are due')
            ->assertExitCode(0);
        $this->assertDatabaseHas('applications', ['id' => $seed['application']->id]);
        $this->assertTrue(Storage::disk('private_uploads')->exists($materialPath));

        // Day 11 dry run (19 November): reported, still nothing changed.
        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19'])
            ->expectsOutputToContain('1 incomplete application(s) due for erasure')
            ->expectsOutputToContain('#'.$seed['application']->id)
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);
        $this->assertDatabaseHas('applications', ['id' => $seed['application']->id]);
        $this->assertSame(1, InterviewAnswer::query()->where('application_id', $seed['application']->id)->count());

        // Day 11 execution: erased with working records and physical files.
        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])
            ->expectsOutputToContain('#'.$seed['application']->id.' erased.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('applications', ['id' => $seed['application']->id]);
        $this->assertSame(0, InterviewAnswer::query()->where('application_id', $seed['application']->id)->count());
        $this->assertSame(0, SourceMaterial::query()->count());
        $this->assertDatabaseMissing('profiles', ['id' => $seed['profile']->id]);
        $this->assertSame(0, EditorialContent::query()->where('profile_id', $seed['profile']->id)->count());
        $this->assertSame(0, MediaItem::query()->where('mediable_id', $seed['profile']->id)->count());
        $this->assertFalse(Storage::disk('private_uploads')->exists($materialPath));
        $this->assertFalse(Storage::disk('public')->exists($photoPath));
    }

    public function test_paid_and_waived_incomplete_applications_expire_with_financials_retained(): void
    {
        $paid = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $this->assertSame(Application::PAYMENT_STATUS_PAID, $paid['application']->payment_status);

        $waived = $this->seedPaidIncomplete(startedUtc: '2026-11-09 05:30:00', waive: true);
        $this->assertSame(Application::PAYMENT_STATUS_WAIVED, $waived['application']->payment_status);
        $this->assertSame(2, Payment::query()->count());

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])->assertExitCode(0);

        foreach ([$paid, $waived] as $seed) {
            $this->assertDatabaseMissing('applications', ['id' => $seed['application']->id]);
        }

        // Every financial row survives, detached from the erased application,
        // with its references intact.
        $this->assertSame(2, Payment::query()->count());
        Payment::query()->get()->each(function (Payment $payment): void {
            $this->assertNull($payment->application_id);
            $this->assertNotNull($payment->transaction_reference);
            $this->assertNotNull($payment->amount);
        });
    }

    public function test_submitted_published_in_memoriam_demo_and_admin_test_records_are_protected(): void
    {
        $submitted = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $submitted['application']->forceFill(['online_interview_completed_at' => now()])->save();

        $published = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $published['application']->forceFill(['status' => Application::STATUS_PUBLISHED])->save();
        $published['profile']->forceFill(['status' => 'published', 'published_at' => now(), 'slug' => 'published.leader'])->save();

        $memorialTier = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $memorialTier['application']->forceFill(['package_tier' => 'in_memoriam'])->save();

        $demo = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $demo['application']->forceFill(['source_method' => 'admin_test_demo'])->save();

        $adminTest = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $adminTest['application']->forceFill(['source_method' => 'admin_test_demo'])->save();

        // An in_memoriam-sourced reservation classifies the application.
        $reserved = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        SlugReservation::query()->create([
            'slug' => 'memorial.classified',
            'profile_id' => $reserved['profile']->id,
            'application_id' => $reserved['application']->id,
            'source' => SlugReservation::SOURCE_IN_MEMORIAM,
        ]);

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])
            ->expectsOutputToContain('No incomplete applications are due')
            ->assertExitCode(0);

        foreach ([$submitted, $published, $memorialTier, $demo, $adminTest, $reserved] as $seed) {
            $this->assertDatabaseHas('applications', ['id' => $seed['application']->id]);
        }
    }

    public function test_expired_customer_account_remains_active_and_can_start_a_fresh_application(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        app(ConsentRecordingService::class)->recordOnce(
            $seed['member'],
            'questionnaire-submission',
            '127.0.0.1',
            'test',
            $seed['application'],
            $seed['profile'],
        );

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => $seed['member']->id, 'account_status' => 'active']);
        $this->assertSame(1, ConsentRecord::query()->count(), 'Consent evidence is retained.');
        $consent = ConsentRecord::query()->sole();
        $this->assertNull($consent->application_id);
        $this->assertNotNull($consent->user_id);

        $audit = StaffActionLog::query()->where('action', 'application.intake_expired')->sole();
        $this->assertSame($seed['application']->id, (int) $audit->subject_id);
        $this->assertSame($seed['member']->id, (int) $audit->before['user_id']);
        $this->assertStringContainsString('ten calendar days', (string) $audit->after['reason']);

        // The retained account starts a fresh application.
        $this->actingAs($seed['member'])
            ->post(route('apply.intent'), [
                'source_method' => 'online_interview',
                'full_name' => 'Fresh Start',
                'broad_location' => 'Kozhikode',
                'contact_email' => 'fresh.start@example.com',
                'solicitation_declaration' => '1',
            ])
            ->assertRedirect();
        $this->assertSame(1, Application::query()->where('user_id', $seed['member']->id)->count());
        $this->assertSame('Fresh Start', Application::query()->where('user_id', $seed['member']->id)->value('full_name'));
    }

    public function test_slug_reservation_is_released_so_the_preferred_url_becomes_available(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        SlugReservation::query()->create([
            'slug' => 'preferred.name',
            'profile_id' => $seed['profile']->id,
            'application_id' => $seed['application']->id,
            'source' => 'assigned',
        ]);

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])->assertExitCode(0);

        $this->assertSame(0, SlugReservation::query()->count());
    }

    public function test_dry_run_never_modifies_anything(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $materialPath = $this->material($seed)['path'];
        $photoPath = $this->photo($seed)['path'];

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19'])
            ->expectsOutputToContain('DRY RUN')
            ->assertExitCode(0);

        $this->assertDatabaseHas('applications', ['id' => $seed['application']->id]);
        $this->assertDatabaseHas('profiles', ['id' => $seed['profile']->id]);
        $this->assertSame(1, MediaItem::query()->where('mediable_id', $seed['profile']->id)->count());
        $this->assertTrue(Storage::disk('public')->exists($photoPath));
        $this->assertTrue(Storage::disk('private_uploads')->exists($materialPath));
    }

    public function test_retry_is_idempotent_and_concurrent_submission_is_protected(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])->assertExitCode(0);
        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])
            ->expectsOutputToContain('No incomplete applications are due')
            ->assertExitCode(0);

        // A customer who submits while erasure is due is protected: the
        // final submitted-state check refuses deletion.
        $concurrent = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $concurrent['application']->forceFill(['online_interview_completed_at' => now()])->save();

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])
            ->expectsOutputToContain('No incomplete applications are due')
            ->assertExitCode(0);
        $this->assertDatabaseHas('applications', ['id' => $concurrent['application']->id]);
    }

    public function test_file_deletion_failure_is_recorded_and_recoverable(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $materialPath = $this->material($seed)['path'];

        // Replace the file with a directory: deletion fails on the local driver.
        Storage::disk('private_uploads')->delete($materialPath);
        Storage::disk('private_uploads')->makeDirectory($materialPath);

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])
            ->expectsOutputToContain('could not be deleted and are recorded for reconciliation')
            ->assertExitCode(1);

        // The database erasure completed; the undeletable file is recorded, not hidden.
        $this->assertDatabaseMissing('applications', ['id' => $seed['application']->id]);
        $pending = StaffActionLog::query()->where('action', 'application.intake_expiry_files_pending')->sole();
        $this->assertSame($seed['application']->id, (int) $pending->after['application_id']);
        $this->assertSame($materialPath, $pending->after['paths'][0]['path']);

        // Still failing on retry until the obstruction is removed.
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(1);
        $this->assertSame(0, StaffActionLog::query()->where('action', 'application.intake_expiry_files_cleared')->count());

        Storage::disk('private_uploads')->deleteDirectory($materialPath);
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(0);

        $this->assertSame(1, StaffActionLog::query()->where('action', 'application.intake_expiry_files_cleared')->count());

        // Later retries neither re-process the cleared application nor add audit entries.
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(0);
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(0);
        $this->assertSame(1, StaffActionLog::query()->where('action', 'application.intake_expiry_files_cleared')->count());
    }

    public function test_crash_after_database_deletion_before_file_deletion_is_recovered_from_the_expiry_record(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $materialPath = $this->material($seed)['path'];
        $photoPath = $this->photo($seed)['path'];
        $service = app(IntakeExpiryService::class);

        // Database deletion commits, then the process stops: no file deletion,
        // no pending-files entry, no cleared entry.
        $result = $service->expire($seed['application'], \Carbon\CarbonImmutable::parse('2026-11-19', 'Asia/Kolkata'));
        $this->assertTrue($result['deleted']);
        $this->assertDatabaseMissing('applications', ['id' => $seed['application']->id]);
        $this->assertSame(0, StaffActionLog::query()->where('action', IntakeExpiryService::PENDING_ACTION)->count());
        $this->assertTrue(Storage::disk('private_uploads')->exists($materialPath));
        $this->assertTrue(Storage::disk('public')->exists($photoPath));

        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])
            ->expectsOutputToContain('Cleared 1 pending-file record(s).')
            ->assertExitCode(0);

        $this->assertFalse(Storage::disk('private_uploads')->exists($materialPath));
        $this->assertFalse(Storage::disk('public')->exists($photoPath));
        $this->assertTrue($service->filesCleared($seed['application']->id));

        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])
            ->expectsOutputToContain('Cleared 0 pending-file record(s).')
            ->assertExitCode(0);
        $this->assertSame(1, StaffActionLog::query()->where('action', IntakeExpiryService::CLEARED_ACTION)->count());
    }

    public function test_crash_after_file_deletion_before_recording_is_marked_cleared_without_redeleting(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $materialPath = $this->material($seed)['path'];
        $service = app(IntakeExpiryService::class);

        // Database deletion and file deletion both completed, then the process
        // stopped before recording the outcome.
        $result = $service->expire($seed['application'], \Carbon\CarbonImmutable::parse('2026-11-19', 'Asia/Kolkata'));
        $this->assertSame([], $service->deleteManifestFiles($result['manifest']));
        $this->assertFalse(Storage::disk('private_uploads')->exists($materialPath));
        $this->assertFalse($service->filesCleared($seed['application']->id));

        // A file recreated at the old path after reconciliation must not be touched.
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(0);
        $this->assertTrue($service->filesCleared($seed['application']->id));

        Storage::disk('private_uploads')->put($materialPath, 'Unrelated later file at a reused path.');
        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])->assertExitCode(0);

        $this->assertTrue(Storage::disk('private_uploads')->exists($materialPath), 'Cleared applications are never re-processed.');
        $this->assertSame(1, StaffActionLog::query()->where('action', IntakeExpiryService::CLEARED_ACTION)->count());
    }

    public function test_normal_successful_erasure_is_marked_cleared_once_and_never_retried(): void
    {
        $seed = $this->seedPaidIncomplete(startedUtc: '2026-11-09 04:30:00');
        $this->material($seed);

        $this->artisan('jannayaks:process-intake-expiry', ['--date' => '2026-11-19', '--execute' => true])->assertExitCode(0);
        $this->assertSame(1, StaffActionLog::query()->where('action', IntakeExpiryService::CLEARED_ACTION)->count());

        $this->artisan('jannayaks:process-intake-expiry', ['--retry-files' => true])
            ->expectsOutputToContain('Cleared 0 pending-file record(s).')
            ->assertExitCode(0);
        $this->assertSame(1, StaffActionLog::query()->where('action', IntakeExpiryService::CLEARED_ACTION)->count());
        $this->assertSame(1, StaffActionLog::query()->where('action', IntakeExpiryService::EXPIRED_ACTION)->count());
    }

    public function test_daily_schedule_executes_the_erasure_in_the_business_timezone(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'jannayaks:process-intake-expiry'));

        $this->assertSame(1, $events->count(), 'The intake expiry schedule must be declared exactly once.');
        $event = $events->first();
        $this->assertStringContainsString('--execute', (string) $event->command, 'The scheduled run must perform the erasure, not a dry run.');
        $this->assertSame('15 2 * * *', $event->expression);
        $this->assertSame('Asia/Kolkata', (string) $event->timezone);
        $this->assertTrue((bool) $event->withoutOverlapping);
    }

    /**
     * @param  string|null  $waive  Pass any non-null value to apply a staff waiver.
     * @return array<string, mixed>
     */
    private function seedPaidIncomplete(string $startedUtc, bool $waive = false): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Expiry Subject '.uniqid(),
            'display_name' => 'Expiry Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        // Paid fixtures carry a settled payment; waived fixtures start unpaid
        // because a waiver cannot be applied over a collected payment.
        $factory = Application::factory()->for($member);
        $application = ($waive ? $factory : $factory->paid())->create([
            'profile_id' => $profile->id,
            'full_name' => 'Expiry Subject',
            'broad_location' => 'Kozhikode',
            'source_method' => 'online_interview',
            'package_tier' => 'distinguished',
        ]);
        if ($waive) {
            app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $admin, 'Launch QA waiver.');
        }
        $application->refresh()->forceFill([
            'online_interview_started_at' => $startedUtc,
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
        ])->save();

        InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'An answer that must be erased with the application.',
            'answered_at' => now(),
        ]);
        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'status' => 'draft',
            'version_number' => 1,
            'title' => 'Working draft',
            'body' => 'Editorial working record for the incomplete application.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => true,
        ]);

        return [
            'member' => $member,
            'admin' => $admin,
            'profile' => $profile->fresh(),
            'application' => $application->fresh(),
        ];
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array{path: string}
     */
    private function material(array $seed): array
    {
        $path = 'source-materials/applications/'.$seed['application']->id.'/notes.pdf';
        Storage::disk('private_uploads')->put($path, 'Source document bytes.');
        SourceMaterial::query()->create([
            'application_id' => $seed['application']->id,
            'user_id' => $seed['member']->id,
            'material_type' => 'personal_notes',
            'storage_disk' => 'private_uploads',
            'storage_path' => $path,
            'original_filename' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'file_bytes' => 22,
            'uploaded_at' => now(),
        ]);

        return ['path' => $path];
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array{path: string}
     */
    private function photo(array $seed): array
    {
        $media = app(ProfileMediaService::class)->uploadProfilePhoto(
            $seed['profile'],
            UploadedFile::fake()->image('portrait.jpg', 400, 500),
            $seed['member'],
        );

        return ['path' => $media->storage_path_key];
    }
}
