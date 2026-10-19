<?php

namespace Tests\Feature;

use App\Console\Commands\ExportContactNumbersCommand;
use App\Mail\ProfileContactMail;
use App\Models\Application;
use App\Models\ContactExportLog;
use App\Models\InMemoriamProfile;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * TEMPORARY weekly contact-numbers export programme.
 *
 * Business rule: numbers are collected with the application, exported only
 * once the application's payment is authoritatively settled, delivered to the
 * owner as a private CSV, and removed from the live database only after the
 * mail transport accepts the message. Failures retain every value and release
 * the batch claim so a retry can pick the rows up.
 */
class ContactNumbersExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.contact.phone_export_recipient' => 'owner-exports@example.com']);
        Storage::fake('private_uploads');
        Mail::fake();
    }

    private function paidApplicationWithNumbers(array $overrides = []): Application
    {
        return Application::factory()->paid()->create(array_merge([
            'full_name' => 'Paid Applicant',
            'preferred_contact_mobile' => '9800000001',
            'preferred_whatsapp_mobile' => '9800000002',
        ], $overrides));
    }

    /* 1. Eligibility: only settled applications are exported and cleaned. */
    public function test_paid_application_with_numbers_is_exported_and_nulled(): void
    {
        $app = $this->paidApplicationWithNumbers();

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);

        // Two-phase: delivered values are RETAINED until owner confirmation.
        $app->refresh();
        $this->assertSame('9800000001', (string) $app->preferred_contact_mobile, 'Values must be retained until confirmation');
        $this->assertSame('9800000002', (string) $app->preferred_whatsapp_mobile);
        $this->assertNull($app->contact_exported_at);
        $this->assertNotNull($app->contact_export_batch_id, 'The batch claim marks the exported-but-unconfirmed rows');
        $this->assertSame(Application::STATUS_PAYMENT_COMPLETE_AWAITING_INTERVIEW, (string) $app->status);
        $this->assertSame('Paid Applicant', (string) $app->full_name, 'Name and other fields stay untouched');
        $this->assertNotNull($app->preferred_contact_email, 'Emails are never removed');

        $this->assertSame(1, ContactExportLog::count());
        $log = ContactExportLog::first();
        $this->assertSame(ContactExportLog::STATUS_AWAITING_CONFIRMATION, (string) $log->status);
        $this->assertSame(1, $log->row_count);
        $this->assertNotNull($log->file_path);

        Storage::disk('private_uploads')->assertExists($log->file_path);
        $csv = Storage::disk('private_uploads')->get($log->file_path);
        $this->assertStringContainsString('9800000001', $csv);
        $this->assertStringContainsString('9800000002', $csv);
        $this->assertStringContainsString((string) $app->id, $csv);

        // Owner confirms after verifying the CSV — only then are values removed.
        $this->artisan('jannayaks:confirm-contact-export', ['batch_id' => $log->batch_id])->assertExitCode(0);

        $app->refresh();
        $this->assertNull($app->preferred_contact_mobile, 'Confirmation removes the exported values');
        $this->assertNull($app->preferred_whatsapp_mobile);
        $this->assertSame('confirmed', (string) $log->fresh()->status);
        $this->assertSame('paid', (string) $app->payment_status);
    }

    /* 2. CSV: exact contents, escaping, no emails, formula-injection safe. */
    public function test_csv_contains_only_eligible_minimal_columns_and_escapes_formulas(): void
    {
        Application::factory()->paid()->create([
            'full_name' => '=HYPERLINK("http://evil","click")',
            'preferred_contact_mobile' => '+919700000001',
            'preferred_whatsapp_mobile' => '9700000002',
            'preferred_contact_email' => 'secret.inclusion@example.com',
        ]);
        Application::factory()->create([
            'full_name' => 'Pending Applicant',
            'preferred_contact_mobile' => '9700000003',
        ]);

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);

        $files = Storage::disk('private_uploads')->files('phone-exports');
        $this->assertCount(1, $files);
        $csv = Storage::disk('private_uploads')->get($files[0]);

        $rows = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        while ($row = fgetcsv($handle)) {
            $rows[] = $row;
        }
        fclose($handle);

        $this->assertSame('application_reference', $rows[0][0], 'Header row first');
        $this->assertCount(2, $rows, 'Header + exactly one eligible record');
        $dataRow = $rows[1];
        $this->assertSame('\'=HYPERLINK("http://evil","click")', $dataRow[1], 'Formula injection must be neutralised');
        $this->assertSame("'+919700000001", $dataRow[3]);
        $this->assertSame('9700000002', $dataRow[4]);
        $this->assertStringNotContainsString('secret.inclusion@example.com', $csv, 'Emails must never be exported');
        $this->assertStringNotContainsString('Pending Applicant', $csv, 'Ineligible records must not appear');
    }

    /* 3. Exclusions: unpaid / failed-payment / waived applications. */
    public function test_unpaid_failed_and_waived_applications_are_excluded(): void
    {
        $pending = Application::factory()->create([
            'full_name' => 'Pending Applicant',
            'preferred_contact_mobile' => '9800000010',
            'preferred_whatsapp_mobile' => '9800000011',
        ]);

        $failed = Application::factory()->create([
            'full_name' => 'Failed Applicant',
            'preferred_contact_mobile' => '9800000020',
        ]);
        Payment::query()->create([
            'application_id' => $failed->id,
            'transaction_reference' => 'TEST-FAILED-'.uniqid(),
            'gateway' => 'razorpay',
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '4720.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_FAILED,
        ]);

        $waived = Application::factory()->adminTestDemo(User::factory()->admin()->create()->id)->create([
            'full_name' => 'Waived Applicant',
            'preferred_contact_mobile' => '9800000030',
        ]);

        $this->artisan('jannayaks:export-contact-numbers')
            ->expectsOutputToContain('No eligible paid applications')
            ->assertExitCode(0);

        foreach ([$pending, $failed, $waived] as $app) {
            $app->refresh();
            $this->assertNotNull($app->preferred_contact_mobile, 'Excluded applications keep their numbers');
            $this->assertNull($app->contact_export_batch_id);
        }
        $this->assertSame(ContactExportLog::STATUS_EMPTY, (string) ContactExportLog::query()->latest('id')->first()->status);
        $this->assertSame([], Storage::disk('private_uploads')->files('phone-exports'));
    }

    /* 4. CSV build failure retains every phone value and releases the claim.
     *    Driven by a real storage failure (unconfigured disk), no command mocking. */
    public function test_csv_build_failure_retains_phone_values(): void
    {
        $app = $this->paidApplicationWithNumbers();

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('private_uploads')
            ->andReturnUsing(function () {
                throw new \RuntimeException('disk full');

                return \Illuminate\Support\Facades\Storage::disk('public');
            });

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(1);

        $app->refresh();
        $this->assertSame('9800000001', (string) $app->preferred_contact_mobile, 'A failed build must keep the numbers');
        $this->assertNull($app->contact_export_batch_id, 'A failed batch must release its claim');
        $this->assertDatabaseHas('contact_export_logs', ['status' => 'failed']);
    }

    /* 5. Delivery failure retains every phone value and releases the claim.
     *    Driven by a genuinely failing mail transport (facade throws). */
    public function test_delivery_failure_retains_phone_values(): void
    {
        $app = $this->paidApplicationWithNumbers();

        Mail::shouldReceive('to')->andReturnSelf()
            ->shouldReceive('send')->andThrow(new \RuntimeException('smtp connection refused'));

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(1);

        $app->refresh();
        $this->assertSame('9800000001', (string) $app->preferred_contact_mobile, 'A failed delivery must keep the numbers');
        $this->assertNull($app->contact_export_batch_id);
        $this->assertDatabaseHas('contact_export_logs', ['status' => 'failed']);
    }

    /* 6. Retry after a failed batch exports without losing records.
     *    Run 1 fails on a throwing mail transport; run 2 restores the fake
     *    mailer (no failure) and succeeds. */
    public function test_retry_after_failure_exports_successfully(): void
    {
        $app = $this->paidApplicationWithNumbers();

        // Run 1: the mail transport throws, so nothing is exported or removed.
        // Run 2: the transport succeeds and the batch is exported.
        Mail::shouldReceive('to')->twice()->andReturnSelf();
        Mail::shouldReceive('send')->twice()->andReturnUsing(function (): void {
            static $calls = 0;
            if (++$calls === 1) {
                throw new \RuntimeException('smtp connection refused');
            }
        });

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(1);
        $app->refresh();
        $this->assertSame('9800000001', (string) $app->preferred_contact_mobile, 'A failed batch must keep the numbers');

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);

        $app->refresh();
        $this->assertSame('9800000001', (string) $app->preferred_contact_mobile, 'Delivered values retained until confirmation');
        $this->assertNotNull($app->contact_export_batch_id);

        $this->artisan('jannayaks:confirm-contact-export')->assertExitCode(0);

        $app->refresh();
        $this->assertNull($app->preferred_contact_mobile);
        $this->assertSame('paid', (string) $app->payment_status);
        $this->assertSame(
            1,
            ContactExportLog::where('status', ContactExportLog::STATUS_CONFIRMED)->count(),
            'The confirmed batch is recorded'
        );
    }

    /* 7. A later paid application joins a subsequent weekly batch. */
    public function test_later_paid_application_is_exported_in_the_next_batch(): void
    {
        $first = Application::factory()->paid()->create([
            'full_name' => 'First Applicant',
            'preferred_contact_mobile' => '9800000041',
            'preferred_whatsapp_mobile' => '9800000042',
        ]);

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);

        $second = Application::factory()->paid()->create([
            'full_name' => 'Second Applicant',
            'preferred_contact_mobile' => '9800000051',
            'preferred_whatsapp_mobile' => '9800000052',
        ]);

        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);
        $this->artisan('jannayaks:export-contact-numbers')->assertExitCode(0);

        $this->assertSame(2, ContactExportLog::where('status', ContactExportLog::STATUS_AWAITING_CONFIRMATION)->count());
        $this->assertNotNull($first->fresh()->preferred_contact_mobile, 'Retained until confirmation');
        $this->assertNotNull($second->fresh()->preferred_whatsapp_mobile, 'Retained until confirmation');

        $this->artisan('jannayaks:confirm-contact-export', ['batch_id' => $first->contact_export_batch_id])->assertExitCode(0);
        $this->artisan('jannayaks:confirm-contact-export', ['batch_id' => $second->contact_export_batch_id])->assertExitCode(0);

        $this->assertNull($first->fresh()->preferred_whatsapp_mobile);
        $this->assertNull($second->fresh()->preferred_whatsapp_mobile);
        $this->assertSame(2, ContactExportLog::where('status', ContactExportLog::STATUS_CONFIRMED)->count());
        $this->assertSame(
            2,
            Application::query()->whereNotNull('contact_exported_at')->count(),
            'Both applications must carry the exported marker'
        );
        $this->assertNull($second->fresh()->preferred_contact_mobile);
        $this->assertNotNull($first->fresh()->preferred_contact_email, 'Emails are never removed');
    }

    /* 8. Public pages never disclose customer numbers. */
    public function test_public_profile_does_not_disclose_contact_numbers(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'published_at' => now(),
            'slug' => 'public-number-test',
            'full_name' => 'Public Number Test',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        Application::factory()->paid()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'preferred_contact_mobile' => '9800000999',
            'preferred_whatsapp_mobile' => '9800000998',
        ]);

        $this->get('/public-number-test')
            ->assertOk()
            ->assertDontSee('9800000999')
            ->assertDontSee('9800000998');
    }

    /* 9. Contact form accepts submissions without a mobile number. */
    public function test_contact_form_accepts_submission_without_mobile(): void
    {
        $this->seed(\Database\Seeders\DemoProfilesSeeder::class);
        Mail::fake();

        // Visit the profile first so back() returns to it, as in a browser.
        // The rendered mobile input must no longer carry the HTML required
        // attribute: email-only enquiries are accepted.
        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('id="visitor_mobile" name="visitor_mobile" maxlength="20" inputmode="tel"', false)
            ->assertDontSee('id="visitor_mobile" name="visitor_mobile" maxlength="20" required', false);

        $response = $this->post('/profiles/t.gopalakrishnan/contact', [
            'visitor_name' => 'Email Only Visitor',
            'message' => 'Would like to invite the profile owner to a community event.',
        ]);
        $response->assertRedirect('/t.gopalakrishnan#wellwishers');

        $this->assertDatabaseHas('profile_contact_messages', ['visitor_name' => 'Email Only Visitor']);
        $this->assertTrue(
            \Illuminate\Support\Facades\DB::table('profile_contact_messages')
                ->where('visitor_name', 'Email Only Visitor')
                ->whereNull('visitor_mobile')
                ->exists(),
            'Email-only submissions must store no mobile number'
        );
        Mail::assertSent(ProfileContactMail::class, 1);
    }

    /* 10. A provided number keeps its validation. */
    public function test_contact_form_still_validates_a_provided_mobile(): void
    {
        $this->seed(\Database\Seeders\DemoProfilesSeeder::class);

        $this->post('/profiles/t.gopalakrishnan/contact', [
            'visitor_name' => 'Bad Number',
            'visitor_mobile' => 'not-a-number',
            'message' => 'Still wants to reach out.',
        ])->assertSessionHasErrors('visitor_mobile');

        $this->assertDatabaseMissing('profile_contact_messages', ['visitor_name' => 'Bad Number']);
    }

    /* 11. Memorial commissioner mobile is no longer a required collection field. */
    public function test_memorial_profile_can_be_created_without_commissioner_mobile(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $memorial = \App\Models\InMemoriamProfile::query()->create([
            'user_id' => $user->id,
            'slug' => 'test-memorial-nophone',
            'status' => 'draft',
            'full_name' => 'Test Memorial',
            'deceased_full_name' => 'Test Deceased',
            'commissioner_contact_name' => '',
            'base_amount' => '24000.00',
            'display_amount' => '4500.00',
            'currency' => 'INR',
            'display_phone_consent' => false,
        ]);

        $this->assertNotNull($memorial->id);
        $this->assertNull($memorial->commissioner_contact_mobile);
    }
}
