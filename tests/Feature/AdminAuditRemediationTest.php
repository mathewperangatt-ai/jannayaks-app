<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ApplicationWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdminAuditRemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_waiver_is_admin_only_audited_and_does_not_invent_a_collected_payment(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->create([
            'status' => Application::STATUS_PAYMENT_PENDING,
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'broad_location' => 'Kozhikode',
        ]);

        $service = app(ApplicationPaymentStateService::class);

        try {
            $service->recordStaffWaiver($application, $editor, 'Editor must not waive a fee.');
            $this->fail('An editor must not apply a waiver.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only an authorised Admin may apply a payment waiver.', $e->getMessage());
        }

        $waived = $service->recordStaffWaiver($application, $admin, 'Offline arrangement recorded by finance.');
        $this->assertSame(Application::PAYMENT_STATUS_WAIVED, $waived->payment_status);
        $this->assertSame($admin->id, $waived->waived_by_user_id);
        $this->assertTrue($waived->isPaymentSettled());

        $waiver = Payment::query()->where('application_id', $application->id)->firstOrFail();
        $this->assertSame(Payment::STATUS_WAIVED, $waiver->status);
        $this->assertSame(Payment::GATEWAY_WAIVER, $waiver->gateway);
        $this->assertSame($admin->id, $waiver->waived_by_user_id);
        $this->assertNotNull($waiver->waived_at);
        $this->assertSame('Offline arrangement recorded by finance.', $waiver->waiver_reason);
        $this->assertFalse($waiver->isPaidOrBetter());

        $audit = StaffActionLog::query()->where('action', 'application.payment_waived')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('Offline arrangement recorded by finance.', $audit->after['waiver_reason']);

        try {
            $service->recordStaffWaiver($waived->fresh(), $admin, 'A second waiver.');
            $this->fail('A second waiver must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A payment waiver is already recorded for this application.', $e->getMessage());
        }

        $paid = Application::factory()->paid()->create(['broad_location' => 'Kannur']);
        try {
            $service->recordStaffWaiver($paid, $admin, 'Cannot waive money already collected.');
            $this->fail('A collected payment must not be waived.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A collected payment already exists. A waiver would make the payment state inconsistent.', $e->getMessage());
        }
        $this->assertSame(0, Payment::query()->where('application_id', $paid->id)->where('status', Payment::STATUS_WAIVED)->count());
    }

    public function test_local_refund_records_success_and_failure_without_calling_a_gateway(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->paid()->create(['broad_location' => 'Kozhikode']);
        $payment = Payment::query()->where('application_id', $application->id)->firstOrFail();
        $refunds = app(RefundService::class);

        $summary = $refunds->refundSummary($payment);
        $this->assertTrue($summary['eligible']);
        $this->assertFalse($summary['gateway_refund_executed']);
        $this->assertSame('calculated', $payment->refundDisposition());

        try {
            $refunds->recordAdministrativeRefund($payment, $editor, 'Editor must not refund.');
            $this->fail('An editor must not record a refund.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only an authorised Admin may record a refund.', $e->getMessage());
        }
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        $recorded = $refunds->recordAdministrativeRefund($payment->fresh(), $admin, 'Customer withdrew before preview.');
        $this->assertSame(Payment::STATUS_REFUNDED, $recorded->status);
        $this->assertSame('recorded_locally', $recorded->refundDisposition());
        $this->assertNull($recorded->refund_gateway_id);
        $this->assertSame(Payment::EVENT_REFUND_PROCESSED, $recorded->event_type);
        $this->assertNotNull($recorded->credit_note_number);
        $this->assertSame(Application::PAYMENT_STATUS_REFUNDED, $application->fresh()->payment_status);
        $this->assertTrue(
            StaffActionLog::query()->where('action', 'payment.refund_recorded')->where('subject_id', $recorded->id)->exists()
        );

        try {
            $refunds->recordAdministrativeRefund($recorded->fresh(), $admin, 'Duplicate refund.');
            $this->fail('A second refund must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A refund is already recorded for this payment.', $e->getMessage());
        }
        $this->assertSame(1, Payment::query()->where('application_id', $application->id)->where('status', Payment::STATUS_REFUNDED)->count());

        $this->assertSame('recorded_locally', $recorded->fresh()->refundDisposition());
        $this->assertSame('Recorded locally — Razorpay was not called', $recorded->fresh()->refund_record_label);

        $closed = Application::factory()->paid()->create([
            'broad_location' => 'Kannur',
            'first_customer_preview_at' => now(),
        ]);
        $closedPayment = Payment::query()->where('application_id', $closed->id)->firstOrFail();
        $beforeFailure = $closedPayment->only([
            'status',
            'event_type',
            'refund_amount',
            'refunded_at',
            'refund_gateway_id',
            'refund_note',
        ]);
        try {
            $refunds->recordAdministrativeRefund($closedPayment, $admin, 'Preview already released.');
            $this->fail('An ineligible refund must not change the payment.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('not eligible', $e->getMessage());
        }
        $unchanged = $closedPayment->fresh();
        $this->assertSame($beforeFailure, $unchanged->only(array_keys($beforeFailure)));
        $this->assertSame('failed', $unchanged->refundDisposition());
        $this->assertSame('Last attempt failed — payment unchanged', $unchanged->refund_record_label);
        $this->assertFalse($refunds->refundSummary($unchanged)['gateway_refund_executed']);
        $failed = StaffActionLog::query()->where('action', 'payment.refund_failed')->where('subject_id', $closedPayment->id)->firstOrFail();
        $this->assertSame('failed', $failed->after['disposition']);
        $this->assertFalse($failed->after['gateway_refund_executed']);
    }

    public function test_editorial_records_cannot_be_created_approved_and_malayalam_approval_is_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $profile = $this->profileFor($editor);
        $versioning = app(EditorialContentVersioningService::class);

        try {
            $versioning->attributesForNewDraft([
                'profile_id' => $profile->id,
                'status' => EditorialContent::STATUS_APPROVED,
            ]);
            $this->fail('A new editorial record must not be created as approved.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('New editorial records must be created as drafts.', $e->getMessage());
        }

        $draft = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം',
            'summary' => 'ചുരുക്കം',
            'body' => 'പ്രസിദ്ധീകരിക്കാത്ത മലയാളം പാഠം.',
            'created_by_id' => $editor->id,
        ]);

        try {
            $versioning->applyEditorUpdate($draft, ['status' => EditorialContent::STATUS_APPROVED], $editor);
            $this->fail('An editor must not approve Malayalam.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only an authorised Admin may approve a Malayalam editorial version.', $e->getMessage());
        }
        $this->assertSame(EditorialContent::STATUS_DRAFT, $draft->fresh()->status);

        $approved = $versioning->applyEditorUpdate($draft->fresh(), ['status' => EditorialContent::STATUS_APPROVED], $admin);
        $this->assertSame($draft->id, $approved->id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $approved->status);
        $this->assertSame($admin->id, $approved->reviewed_by_id);
    }

    public function test_publication_without_customer_approval_requires_a_reason_and_stays_admin_only(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        [$application, $english] = $this->approvedEnglishApplication($admin);

        $workflow = app(ApplicationWorkflowService::class);
        try {
            $workflow->publish($application, $editor, false, 'An editor reason.');
            $this->fail('An editor must not publish.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only an authorised Admin may publish.', $e->getMessage());
        }

        try {
            $workflow->publish($application->fresh(), $admin, false);
            $this->fail('Exceptional publication requires a reason.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Publishing without customer approval requires a written reason.', $e->getMessage());
        }
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->fresh()->status);

        $published = $workflow->publish($application->fresh(), $admin, false, 'Offline approval was received in writing.');
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame($english->id, $published->published_english_editorial_content_id);
        $this->assertNull($published->published_malayalam_editorial_content_id);

        $audit = StaffActionLog::query()->where('action', 'application.published')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('Offline approval was received in writing.', $audit->after['exceptional_publication_reason']);
        $this->assertFalse($audit->after['require_customer_approval']);
    }

    public function test_malayalam_publication_is_independent_and_hides_unpublished_drafts(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        [$application, $english, $profile] = $this->publishEnglish($admin);
        $draftBody = 'പ്രസിദ്ധീകരിക്കാത്ത മലയാളം ഡ്രാഫ്റ്റ്.';
        $publishedBody = 'പ്രസിദ്ധീകരിച്ച മലയാളം പതിപ്പ്.';

        $draft = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'source_editorial_content_id' => $english->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം',
            'summary' => 'ചുരുക്കം',
            'body' => $draftBody,
            'created_by_id' => $editor->id,
        ]);

        $profile = $profile->fresh();
        $this->assertNotEmpty($profile->slug);
        $this->get('/'.$profile->slug)
            ->assertOk()
            ->assertSee('English published biography.', false)
            ->assertDontSee($draftBody, false);

        $workflow = app(ApplicationWorkflowService::class);
        foreach ([$editor, $member] as $actor) {
            try {
                $workflow->publishMalayalam($application->fresh(), $actor, $draft);
                $this->fail('Only an administrator may publish Malayalam.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Only an authorised Admin may approve and publish Malayalam.', $e->getMessage());
            }
        }
        $this->assertNull($application->fresh()->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);

        $draft->forceFill(['body' => $publishedBody])->save();
        $published = $workflow->publishMalayalam($application->fresh(), $admin, $draft->fresh());
        $this->assertSame($draft->id, $published->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $published->published_english_editorial_content_id);
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $draft->fresh()->status);

        $profile = $profile->fresh();
        $bound = $profile->application;
        $this->assertNotNull($bound);
        $this->assertSame($draft->id, (int) $bound->published_malayalam_editorial_content_id);
        $presented = app(\App\Services\PublicProfilePresentationService::class)->present($profile, 'ml');
        $this->assertNotNull($presented['malayalam']);
        $this->assertSame($publishedBody, $presented['activeEditorial']?->body);

        $this->get('/'.$profile->slug.'?lang=ml')
            ->assertOk()
            ->assertSee($publishedBody, false)
            ->assertSee('lang=ml', false);

        $successor = app(EditorialContentVersioningService::class)->applyEditorUpdate(
            $draft->fresh(),
            ['body' => 'പുതിയ മലയാളം ഡ്രാഫ്റ്റ് പൊതുവായി കാണരുത്.'],
            $editor,
        );
        $this->assertNotSame($draft->id, $successor->id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $successor->status);
        $this->assertSame($draft->id, $application->fresh()->published_malayalam_editorial_content_id);
        $this->assertSame($publishedBody, $draft->fresh()->body);
        $this->get('/'.$profile->slug.'?lang=ml')
            ->assertOk()
            ->assertSee($publishedBody, false)
            ->assertDontSee('പുതിയ മലയാളം ഡ്രാഫ്റ്റ് പൊതുവായി കാണരുത്.', false);

        $this->assertTrue(
            StaffActionLog::query()->where('action', 'application.malayalam_published')->where('actor_user_id', $admin->id)->exists()
        );
    }

    public function test_audit_history_and_customer_consent_are_read_only_for_administrators(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $support = User::factory()->support()->create();
        $profile = $this->profileFor($admin);
        $application = Application::factory()->create([
            'user_id' => $profile->user_id,
            'profile_id' => $profile->id,
            'broad_location' => 'Kozhikode',
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Consent title',
            'summary' => 'Consent summary',
            'body' => 'Consent body that must not be shown on the consent list.',
            'created_by_id' => $admin->id,
        ]);

        StaffActionLog::query()->create([
            'actor_user_id' => $admin->id,
            'action' => 'application.payment_waived',
            'subject_type' => Application::class,
            'subject_id' => $application->id,
            'after' => [
                'waiver_reason' => 'Fee waived for this test.',
                'email' => 'hidden.person@example.com',
            ],
        ]);
        EditorialCustomerApproval::query()->create([
            'application_id' => $application->id,
            'profile_id' => $profile->id,
            'approved_by_user_id' => $application->user_id,
            'english_editorial_content_id' => $english->id,
            'consent_scope' => EditorialCustomerApproval::SCOPE_ENGLISH_AND_MALAYALAM,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/staff-action-logs')
            ->assertOk()
            ->assertSee('application.payment_waived', false)
            ->assertSee('Fee waived for this test.', false)
            ->assertDontSee('hidden.person@example.com', false);
        $this->actingAs($editor)->get('/admin/staff-action-logs')->assertForbidden();
        $this->actingAs($support)->get('/admin/staff-action-logs')->assertForbidden();

        $this->actingAs($admin)->get('/admin/customer-approvals')
            ->assertOk()
            ->assertSee(EditorialCustomerApproval::SCOPE_ENGLISH_AND_MALAYALAM, false)
            ->assertDontSee('Consent body that must not be shown on the consent list.', false);
        $this->actingAs($editor)->get('/admin/customer-approvals')->assertForbidden();
        $this->actingAs($support)->get('/admin/customer-approvals')->assertForbidden();
    }

    public function test_published_application_edit_screen_hides_pre_publication_actions(): void
    {
        $editor = User::factory()->editor()->create();
        $open = Application::factory()->paid()->create([
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
        ]);
        $published = Application::factory()->paid()->create([
            'broad_location' => 'Kannur',
        ]);
        $published->forceFill(['status' => Application::STATUS_PUBLISHED])->save();

        $openPage = $this->actingAs($editor)->get('/admin/applications/'.$open->id.'/edit')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Generate\s+AI\s+editorial\s+draft/', $openPage);
        $this->assertMatchesRegularExpression('/Release for customer preview/', $openPage);

        $publishedPage = $this->actingAs($editor)->get('/admin/applications/'.$published->id.'/edit')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/Generate\s+AI\s+editorial\s+draft/', $publishedPage);
        $this->assertDoesNotMatchRegularExpression('/Release for customer preview/', $publishedPage);

        $openView = $this->actingAs($editor)->get('/admin/applications/'.$open->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Generate\s+AI\s+editorial\s+draft/', $openView);
        $this->assertMatchesRegularExpression('/Release for customer preview/', $openView);

        $publishedView = $this->actingAs($editor)->get('/admin/applications/'.$published->id)->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/Generate\s+AI\s+editorial\s+draft/', $publishedView);
        $this->assertDoesNotMatchRegularExpression('/Release for customer preview/', $publishedView);
    }

    /**
     * @return array{0: Application, 1: EditorialContent}
     */
    private function approvedEnglishApplication(User $admin): array
    {
        $profile = $this->profileFor($admin);
        $application = Application::factory()->paid()->create([
            'user_id' => $profile->user_id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'package_tier' => 'accomplished',
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'English title',
            'summary' => 'English summary',
            'body' => 'English published biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);

        return [$application->fresh(), $english];
    }

    /**
     * @return array{0: Application, 1: EditorialContent, 2: Profile}
     */
    private function publishEnglish(User $admin): array
    {
        [$application, $english] = $this->approvedEnglishApplication($admin);
        $published = app(ApplicationWorkflowService::class)->publish(
            $application,
            $admin,
            false,
            'Authorised offline publication for this test.',
        );

        return [$published, $english, $published->profile];
    }

    private function profileFor(User $owner): Profile
    {
        return Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Meera Nair',
            'display_name' => 'Meera Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
    }
}
