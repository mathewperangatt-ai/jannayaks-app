<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Publication-test-only editorial approval of the exact unchanged English
 * draft the customer already approved. It never approves Malayalam, never
 * writes a payment, and never clears the customer approval.
 */
class PublicationTestEditorialApprovalTest extends TestCase
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
            'jannayaks.publication_test_bypass.enabled' => false,
            'jannayaks.publication_test_bypass.reference_codes' => '',
        ]);
    }

    public function test_admin_marks_only_the_unchanged_customer_approved_english_draft_approved(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, $member, $application, $english, $malayalam] = $this->seedCustomerApprovedDraft(self::ALLOWED);
        $approvedAt = $application->customer_approved_at;
        $title = $english->title;
        $summary = $english->summary;
        $body = $english->body;

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('approvePublicationTestEnglishDraft')
            ->callAction('approvePublicationTestEnglishDraft');

        $english->refresh();
        $application->refresh();
        $approval = EditorialCustomerApproval::query()->sole();

        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->status);
        $this->assertSame($admin->id, $english->reviewed_by_id);
        $this->assertSame($title, $english->title);
        $this->assertSame($summary, $english->summary);
        $this->assertSame($body, $english->body);
        $this->assertSame(EditorialContent::LANGUAGE_EN, $english->language);
        $this->assertSame(3, $english->version_number);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $malayalam->fresh()->status);
        $this->assertNull($malayalam->fresh()->reviewed_by_id);
        $this->assertTrue($approval->isActive());
        $this->assertSame($english->id, $approval->english_editorial_content_id);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame($english->id, $application->preview_english_editorial_content_id);
        $this->assertSame($malayalam->id, $application->preview_malayalam_editorial_content_id);
        $this->assertEquals($approvedAt, $application->customer_approved_at);
        $this->assertSame($member->id, $application->customer_approved_by_user_id);
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->status);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertNull($application->payment_settled_at);
        $this->assertSame(0, Payment::query()->count());
        $this->assertFalse($application->profile->isPubliclyListed());

        $audit = StaffActionLog::query()->where('action', 'editorial.publication_test_english_draft_approved')->sole();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame($english->id, $audit->after['editorial_content_id']);
        $this->assertSame(3, $audit->after['version_number']);
        $this->assertSame('Publication test only: mark the unchanged customer-approved English draft as editorially approved.', $audit->after['reason']);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $audit->before['status']);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $audit->after['status']);

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $this->assertSame($english->id, $published->published_english_editorial_content_id);
        $this->assertNull($published->published_malayalam_editorial_content_id);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $published->payment_status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_action_is_refused_outside_the_allowlist_and_for_a_public_profile(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, , $application, $english] = $this->seedCustomerApprovedDraft(self::ALLOWED);
        [, , $other, $otherEnglish] = $this->seedCustomerApprovedDraft('JN-OTHER');

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $other->getRouteKey()])
            ->assertActionHidden('approvePublicationTestEnglishDraft');

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($other, $admin);
            $this->fail('A profile outside the publication-test allowlist must not be editorially approved by this action.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The publication test bypass is not active for this profile.', $e->getMessage());
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, $otherEnglish->fresh()->status);

        config(['jannayaks.publication_test_bypass.enabled' => false]);
        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application->fresh(), $admin);
            $this->fail('A disabled publication bypass must not approve the draft.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The publication test bypass is not active for this profile.', $e->getMessage());
        }

        $this->enableFor(self::ALLOWED);
        $application->profile->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application->fresh(), $admin);
            $this->fail('A publicly listed profile must not use the publication-test editorial approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The publication test bypass is not active for this profile.', $e->getMessage());
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->fresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_action_refuses_a_missing_or_mismatched_customer_approval(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, , $application, $english] = $this->seedCustomerApprovedDraft(self::ALLOWED);
        $replacement = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 4,
            'title' => 'Different draft',
            'body' => 'Text the customer did not approve.',
            'summary' => 'Different summary',
            'source_material' => '',
            'ai_generated' => false,
            'created_by_id' => $admin->id,
        ]);
        $application->forceFill([
            'customer_approved_english_editorial_content_id' => $replacement->id,
        ])->save();

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application->fresh(), $admin);
            $this->fail('A pin that does not match the customer approval record must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The customer approval record does not match the application\'s approved English version.', $e->getMessage());
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $replacement->fresh()->status);
        $this->assertTrue(EditorialCustomerApproval::query()->sole()->isActive());

        EditorialCustomerApproval::query()->update(['invalidated_at' => now(), 'invalidation_reason' => 'Withdrawn.']);
        $application->forceFill([
            'customer_approved_english_editorial_content_id' => $english->id,
        ])->save();

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application->fresh(), $admin);
            $this->fail('A missing active customer approval must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An active customer approval of the exact English version is required.', $e->getMessage());
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->fresh()->status);
        $this->assertNull($english->fresh()->reviewed_by_id);
        $this->assertSame(0, StaffActionLog::query()->where('action', 'editorial.publication_test_english_draft_approved')->count());
    }

    public function test_action_refuses_when_the_customer_approved_text_changed(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, , $application, $english] = $this->seedCustomerApprovedDraft(self::ALLOWED);
        $english->forceFill([
            'title' => 'Edited after approval',
            'body' => 'Narrative changed after the customer approved it.',
        ])->save();

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application->fresh(), $admin);
            $this->fail('Narrative edited after customer approval must not be marked editorially approved.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The customer-approved English version has changed since the customer approved it.', $e->getMessage());
        }

        $english->refresh();
        $application->refresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->status);
        $this->assertSame('Edited after approval', $english->title);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertNotNull($application->customer_approved_at);
        $this->assertTrue(EditorialCustomerApproval::query()->sole()->isActive());
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_editor_cannot_approve_the_publication_test_draft(): void
    {
        $this->enableFor(self::ALLOWED);
        [, , $application, $english] = $this->seedCustomerApprovedDraft(self::ALLOWED);
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);

        Livewire::actingAs($editor)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('approvePublicationTestEnglishDraft');

        try {
            app(ApplicationWorkflowService::class)->approvePublicationTestEnglishDraft($application, $editor);
            $this->fail('An editor must not use the publication-test editorial approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Only an authorised Admin may approve an editorial draft for the publication test.', $e->getMessage());
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, $english->fresh()->status);
    }

    private function enableFor(string $reference): void
    {
        config([
            'jannayaks.publication_test_bypass.enabled' => true,
            'jannayaks.publication_test_bypass.reference_codes' => $reference,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Application, 3: EditorialContent, 4: EditorialContent}
     */
    private function seedCustomerApprovedDraft(string $reference): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $approvedAt = now()->subHour();

        $profile = new Profile([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Layout Tester '.$reference,
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
            'full_name' => 'Layout Tester '.$reference,
            'source_method' => 'online_interview',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 3,
            'title' => 'Customer approved title '.$reference,
            'body' => 'The exact biography the customer approved.',
            'summary' => 'Customer approved summary',
            'source_material' => '',
            'ai_generated' => true,
            'created_by_id' => $admin->id,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'source_editorial_content_id' => $english->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 3,
            'title' => 'Malayalam draft '.$reference,
            'body' => 'Malayalam text that must stay a draft.',
            'summary' => 'Malayalam summary',
            'source_material' => '',
            'ai_generated' => true,
            'created_by_id' => $admin->id,
        ]);

        EditorialContent::query()->whereKey([$english->id, $malayalam->id])->update([
            'created_at' => $approvedAt->copy()->subMinute(),
            'updated_at' => $approvedAt->copy()->subMinute(),
        ]);

        EditorialCustomerApproval::query()->create([
            'application_id' => $application->id,
            'profile_id' => $profile->id,
            'approved_by_user_id' => $member->id,
            'english_editorial_content_id' => $english->id,
            'malayalam_editorial_content_id' => $malayalam->id,
            'approved_at' => $approvedAt,
        ]);

        $application->forceFill([
            'customer_approved_at' => $approvedAt,
            'customer_approved_by_user_id' => $member->id,
            'customer_approved_english_editorial_content_id' => $english->id,
            'preview_english_editorial_content_id' => $english->id,
            'preview_malayalam_editorial_content_id' => $malayalam->id,
        ])->save();

        return [$admin, $member, $application->fresh(), $english->fresh(), $malayalam->fresh()];
    }
}
