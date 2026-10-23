<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Schemas\ApplicationInfolist;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Support\EditorialTestBypass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Temporary allowlisted editorial test bypass.
 * Disabled by default. It never records a payment and never marks a draft approved.
 */
class EditorialTestBypassTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = 'JN-DBMQZ';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jannayaks.payments.testing_mode' => false,
            'jannayaks.editorial_test_bypass.enabled' => false,
            'jannayaks.editorial_test_bypass.reference_codes' => '',
        ]);
    }

    public function test_bypass_is_disabled_by_default(): void
    {
        $this->assertFalse(config('jannayaks.editorial_test_bypass.enabled'));
        $this->assertFalse(app(EditorialTestBypass::class)->enabled());

        [$editor, $member, $application, $english] = $this->seedDraftApplication(self::ALLOWED);

        config([
            'jannayaks.editorial_test_bypass.reference_codes' => self::ALLOWED,
        ]);

        $this->assertFalse(app(EditorialTestBypass::class)->appliesTo($application));

        try {
            app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $editor);
            $this->fail('A disabled bypass must not release an unpaid draft.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before customer preview.', $e->getMessage());
        }

        $english->refresh();
        $application->refresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertNull($english->reviewed_by_id);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertSame(0, Payment::query()->where('application_id', $application->id)->count());
        $this->assertNull(app(EditorialTestBypass::class)->adminNotice($application));
        $this->assertStringNotContainsString('Test bypass active', ApplicationInfolist::workflowBannerHtml($application));
    }

    public function test_only_the_allowlisted_application_can_skip_payment_and_editorial_approval(): void
    {
        $this->enableFor(self::ALLOWED);

        [$editor, $member, $allowed, $allowedEnglish] = $this->seedDraftApplication(self::ALLOWED);
        [, $otherMember, $ordinary, $ordinaryEnglish] = $this->seedDraftApplication('JN-OTHER');

        $bypass = app(EditorialTestBypass::class);
        $this->assertTrue($bypass->appliesTo($allowed->fresh()));
        $this->assertFalse($bypass->appliesTo($ordinary->fresh()));
        $this->assertNotSame($member->id, $otherMember->id);

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($allowed, $editor);

        $allowedEnglish->refresh();
        $released->refresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $allowedEnglish->status);
        $this->assertTrue($allowedEnglish->ai_generated);
        $this->assertNull($allowedEnglish->reviewed_by_id);
        $this->assertSame($allowedEnglish->id, $released->preview_english_editorial_content_id);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $released->payment_status);
        $this->assertNull($released->payment_settled_at);
        $this->assertSame(0, Payment::query()->where('application_id', $allowed->id)->count());
        $this->assertSame('customer_preview', $released->profile->status);
        $this->assertFalse($released->profile->isPubliclyListed());

        $audit = StaffActionLog::query()->where('action', 'editorial.test_bypass_used')->sole();
        $this->assertSame(self::ALLOWED, $audit->after['profile_reference_code']);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $audit->after['english_editorial_status']);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $audit->after['payment_status']);
        $this->assertTrue(
            StaffActionLog::query()->where('action', 'editorial.customer_preview_released')->where('subject_id', $allowed->id)->exists()
        );

        $notice = ApplicationInfolist::workflowBannerHtml($allowed->fresh());
        $this->assertStringContainsString('Test bypass active', $notice);
        $this->assertStringContainsString(self::ALLOWED, $notice);
        $this->assertStringNotContainsString('Test bypass active', ApplicationInfolist::workflowBannerHtml($ordinary->fresh()));

        try {
            $workflow->releaseForCustomerPreview($ordinary, $editor);
            $this->fail('An application outside the allowlist must keep the payment gate.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before customer preview.', $e->getMessage());
        }

        $ordinaryEnglish->refresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $ordinaryEnglish->status);
        $this->assertNull($ordinary->fresh()->customer_preview_released_at);

        $paid = $this->settle($ordinary->fresh());
        try {
            $workflow->releaseForCustomerPreview($paid, $editor);
            $this->fail('A paid application outside the allowlist still needs an approved English master.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Approve the English editorial master before releasing customer preview.', $e->getMessage());
        }

        $ordinaryEnglish->refresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $ordinaryEnglish->status);
    }

    public function test_allowlisted_customer_can_preview_request_revision_and_approve_without_publishing(): void
    {
        $this->enableFor(self::ALLOWED);
        [$editor, $member, $application, $english] = $this->seedDraftApplication(self::ALLOWED);
        $workflow = app(CustomerEditorialWorkflowService::class);
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);

        $released = $workflow->releaseForCustomerPreview($application, $editor);

        $this->actingAs($member)
            ->get(route('applications.preview', $released))
            ->assertOk()
            ->assertSee('Usable AI draft body for the private preview.', false);

        $this->get('/test-'.strtolower(self::ALLOWED))->assertNotFound();

        $revision = $workflow->requestRevision(
            $released->fresh(),
            $member,
            'Please soften the opening paragraph.',
            EditorialRevisionRequest::TYPE_REVISION,
        );
        $this->assertSame(1, $revision->round_number);
        $this->assertSame(Application::STATUS_EDITORIAL_REVISION_REQUESTED, $application->fresh()->status);

        $rereleased = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $approval = $workflow->approvePreview(
            $rereleased,
            $member,
            (int) $rereleased->preview_english_editorial_content_id,
            '127.0.0.1',
            'EditorialBypassTest',
        );

        $english->refresh();
        $application->refresh();
        $this->assertSame($english->id, $approval->english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->status);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertSame(0, Payment::query()->where('application_id', $application->id)->count());
        $this->assertFalse($application->profile->isPubliclyListed());
        $this->get('/test-'.strtolower(self::ALLOWED))->assertNotFound();

        $this->settle($application->fresh());
        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('A settled payment must not publish an unapproved draft.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An unapproved editorial draft cannot be published.', $e->getMessage());
        }

        $application->refresh();
        $english->refresh();
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertNull($application->published_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertFalse($application->profile->fresh()->isPubliclyListed());
        $this->get('/test-'.strtolower(self::ALLOWED))->assertNotFound();

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
            $this->fail('The exceptional publication path must not publish an unapproved draft.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An unapproved editorial draft cannot be published.', $e->getMessage());
        }

        $english->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
        ])->save();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame($english->id, $published->published_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
    }

    public function test_approved_replacement_publishes_when_obsolete_pins_reference_an_archived_version(): void
    {
        [$editor, , $application, $previous] = $this->seedDraftApplication('JN-REPL1');
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $workflow = app(ApplicationWorkflowService::class);

        $previous->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
            'body' => 'Original published text',
        ])->save();
        $application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'source_method' => 'online_interview',
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'customer_approved_at' => now()->subDay(),
            'customer_approved_english_editorial_content_id' => $previous->id,
            'preview_english_editorial_content_id' => $previous->id,
            'published_english_editorial_content_id' => $previous->id,
        ])->save();
        $application->profile->forceFill([
            'status' => 'published',
            'published_at' => now()->subDay(),
        ])->save();

        $replacement = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Replacement title',
            'body' => 'Approved replacement text',
            'summary' => 'Replacement summary',
            'source_material' => 'Staff revision',
            'ai_generated' => false,
            'created_by_id' => $editor->id,
            'reviewed_by_id' => $editor->id,
        ]);
        $previous->forceFill(['status' => EditorialContent::STATUS_ARCHIVED])->save();

        try {
            $workflow->publish($application->fresh(), $admin, false);
            $this->fail('An unpaid replacement must still require a settled payment.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before publication.', $e->getMessage());
        }

        $this->assertSame(0, Payment::query()->where('application_id', $application->id)->count());
        $this->assertSame($previous->id, $application->fresh()->published_english_editorial_content_id);

        $this->settle($application->fresh());
        $published = $workflow->publish($application->fresh(), $admin, false);

        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame($replacement->id, $published->published_english_editorial_content_id);
        $this->assertSame($previous->id, $published->customer_approved_english_editorial_content_id);
        $this->assertSame($previous->id, $published->preview_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_ARCHIVED, $previous->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $replacement->fresh()->status);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $published->payment_status);
        $this->assertSame(1, Payment::query()->where('application_id', $application->id)->where('status', Payment::STATUS_PAID)->count());
        $this->assertFalse(config('jannayaks.editorial_test_bypass.enabled'));

        $published->forceFill([
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();
        try {
            $workflow->publish($published->fresh(), $admin, true);
            $this->fail('Approval of the archived version must not authorize the replacement.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An unapproved editorial draft cannot be published.', $e->getMessage());
        }

        $published->refresh();
        $this->assertSame($replacement->id, $published->published_english_editorial_content_id);
        $this->assertNotSame(Application::STATUS_PUBLISHED, $published->status);
    }

    public function test_unapproved_version_cannot_be_published_on_the_exceptional_admin_path(): void
    {
        [$editor, , $application, $draft] = $this->seedDraftApplication('JN-DRAFT');
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $workflow = app(ApplicationWorkflowService::class);

        $older = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Older approved title',
            'body' => 'Older approved text',
            'summary' => 'Older summary',
            'source_material' => '',
            'ai_generated' => false,
            'created_by_id' => $editor->id,
            'reviewed_by_id' => $editor->id,
        ]);
        $older->forceFill(['status' => EditorialContent::STATUS_ARCHIVED])->save();

        $application->forceFill([
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'source_method' => 'online_interview',
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $draft->id,
            'preview_english_editorial_content_id' => $draft->id,
        ])->save();
        $this->settle($application);

        foreach ([true, false] as $requireCustomerApproval) {
            try {
                $workflow->publish($application->fresh(), $admin, $requireCustomerApproval);
                $this->fail('An unapproved draft must not be published.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('An unapproved editorial draft cannot be published.', $e->getMessage());
            }
        }

        $application->refresh();
        $draft->refresh();
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->status);
        $this->assertNull($application->published_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $draft->status);
        $this->assertSame(EditorialContent::STATUS_ARCHIVED, $older->fresh()->status);
        $this->assertFalse($application->profile->fresh()->isPubliclyListed());
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
    }

    public function test_disabling_the_bypass_restores_the_ordinary_gates_without_changing_records(): void
    {
        $this->enableFor(self::ALLOWED);
        [$editor, $member, $application, $english] = $this->seedDraftApplication(self::ALLOWED);
        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application, $editor);
        $this->assertNotNull($released->customer_preview_released_at);

        config(['jannayaks.editorial_test_bypass.enabled' => false]);

        $application->refresh();
        $english->refresh();
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertFalse(app(EditorialTestBypass::class)->appliesTo($application));
        $this->assertNull(app(EditorialTestBypass::class)->adminNotice($application));

        [$editor2, , $next] = $this->seedDraftApplication('JN-NEXT1');
        try {
            $workflow->releaseForCustomerPreview($next, $editor2);
            $this->fail('Disabling the bypass must restore the payment gate for every application.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before customer preview.', $e->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame($member->id, $application->fresh()->user_id);
    }

    private function enableFor(string $reference): void
    {
        config([
            'jannayaks.editorial_test_bypass.enabled' => true,
            'jannayaks.editorial_test_bypass.reference_codes' => $reference,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Application, 3: EditorialContent}
     */
    private function seedDraftApplication(string $reference): array
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);

        $profile = new Profile([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Synthetic Tester '.$reference,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
            'slug' => 'test-'.strtolower($reference),
        ]);
        $profile->reference_code = $reference;
        $profile->save();

        $application = Application::factory()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Synthetic Tester '.$reference,
            'source_method' => 'online_interview',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'Draft title '.$reference,
            'body' => 'Usable AI draft body for the private preview.',
            'summary' => 'Draft summary',
            'source_material' => '',
            'ai_generated' => true,
            'created_by_id' => $editor->id,
        ]);

        return [$editor, $member, $application->fresh(), $english->fresh()];
    }

    private function settle(Application $application): Application
    {
        Payment::query()->create([
            'application_id' => $application->id,
            'transaction_reference' => 'TEST-REAL-'.$application->id,
            'gateway' => Payment::GATEWAY_MANUAL,
            'item_type' => Payment::ITEM_APPLICATION_PAYMENT,
            'amount' => '4000.00',
            'currency' => 'INR',
            'status' => Payment::STATUS_PAID,
            'paid_at' => now(),
            'event_type' => 'application_package',
        ]);

        return $application->fresh() ?? $application;
    }
}
