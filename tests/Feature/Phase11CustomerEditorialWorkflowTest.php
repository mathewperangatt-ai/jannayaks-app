<?php

namespace Tests\Feature;

use App\Contracts\EditorialAiClient;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\InterviewAnswer;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\EditorialGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class Phase11CustomerEditorialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private FakeEditorialAiClient $fakeAi;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        Config::set('jannayaks.ai.editorial.included_prepublication_revision_rounds', 2);
        $this->fakeAi = new FakeEditorialAiClient;
        $this->app->instance(EditorialAiClient::class, $this->fakeAi);
    }

    public function test_ai_draft_cannot_directly_publish_application(): void
    {
        [$editor, $member, $application] = $this->seedAiDraft();
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $editor, true);
    }

    public function test_editor_can_release_preview_but_cannot_publish(): void
    {
        [$editor, $member, $application, $english] = $this->seedApprovedPreviewReady();
        $released = app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $editor);

        $this->assertSame(Application::STATUS_EDITORIAL_APPROVED, $released->status);
        $this->assertNotNull($released->customer_preview_released_at);
        $this->assertSame($english->id, $released->preview_english_editorial_content_id);

        $this->expectException(\InvalidArgumentException::class);
        app(ApplicationWorkflowService::class)->publish($released, $editor, true);
    }

    public function test_support_cannot_publish_or_release_preview(): void
    {
        [, , $application] = $this->seedApprovedPreviewReady();
        $support = User::factory()->support()->create();

        $this->expectException(\InvalidArgumentException::class);
        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $support);
    }

    public function test_admin_retains_publication_authority_with_and_without_customer_approval(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $admin = User::factory()->admin()->create();
        $workflow = app(CustomerEditorialWorkflowService::class);

        $released = $workflow->releaseForCustomerPreview($application, $editor);
        $workflow->approvePreview($released, $member, (int) $released->preview_english_editorial_content_id);

        $published = app(ApplicationWorkflowService::class)->publish($released->fresh(), $admin, true);
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertTrue(
            StaffActionLog::query()->where('action', 'application.published')->exists()
        );

        // Exceptional offline path: fresh app without customer approval.
        [, , $offline] = $this->seedApprovedPreviewReady();
        $exceptional = app(ApplicationWorkflowService::class)->publish($offline->fresh(), $admin, false);
        $this->assertSame(Application::STATUS_PUBLISHED, $exceptional->status);
    }

    public function test_member_can_view_own_preview_and_not_another_members(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $editor);

        $this->actingAs($member)
            ->get(route('applications.preview', $application))
            ->assertOk()
            ->assertSee('YOUR Jannayaks PROFILE', false)
            ->assertSee('Approve this profile', false)
            ->assertDontSee('generation_run', false)
            ->assertDontSee('claimTraces', false);

        $other = User::factory()->create();
        $this->actingAs($other)
            ->get(route('applications.preview', $application))
            ->assertForbidden();
    }

    public function test_two_included_revision_rounds_without_payment_and_third_rejected(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);

        $released = $service->releaseForCustomerPreview($application, $editor);
        $r1 = $service->requestRevision($released, $member, 'Please soften the opening paragraph.', EditorialRevisionRequest::TYPE_REVISION);
        $this->assertSame(1, $r1->round_number);
        $this->assertSame(1, $application->fresh()->included_revision_rounds_used);
        $this->assertSame(Application::STATUS_EDITORIAL_REVISION_REQUESTED, $application->fresh()->status);

        // No payment row required / created for revision.
        $this->assertSame(1, $application->fresh()->directPayments()->count()); // original package payment only

        // Staff re-releases after revision work (approve latest drafts already approved).
        $released2 = $service->releaseForCustomerPreview($application->fresh(), $editor);
        $r2 = $service->requestRevision($released2, $member, 'Please correct the chronology in paragraph two.', EditorialRevisionRequest::TYPE_REVISION);
        $this->assertSame(2, $r2->round_number);
        $this->assertSame(2, $application->fresh()->included_revision_rounds_used);

        $released3 = $service->releaseForCustomerPreview($application->fresh(), $editor);
        $this->expectException(\InvalidArgumentException::class);
        $service->requestRevision($released3, $member, 'One more change please.', EditorialRevisionRequest::TYPE_REVISION);
    }

    public function test_revision_round_cannot_be_bypassed_via_request_parameters(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);

        $this->actingAs($member)->post(route('applications.preview.revision', $released), [
            'request_type' => EditorialRevisionRequest::TYPE_REVISION,
            'request_text' => 'First included revision please.',
            'round_number' => 1,
            'included_revision_rounds_used' => 0,
        ])->assertRedirect();

        $service->releaseForCustomerPreview($application->fresh(), $editor);
        $this->actingAs($member)->post(route('applications.preview.revision', $application->fresh()), [
            'request_type' => EditorialRevisionRequest::TYPE_REVISION,
            'request_text' => 'Second included revision please.',
            'round_number' => 1,
            'included_revision_rounds_used' => 0,
        ])->assertRedirect();

        $this->assertSame(2, $application->fresh()->included_revision_rounds_used);

        $service->releaseForCustomerPreview($application->fresh(), $editor);
        $this->actingAs($member)->post(route('applications.preview.revision', $application->fresh()), [
            'request_type' => EditorialRevisionRequest::TYPE_REVISION,
            'request_text' => 'Attempted third round via form tampering.',
            'round_number' => 1,
            'included_revision_rounds_used' => 0,
        ])->assertSessionHasErrors('revision');
    }

    public function test_revision_history_is_preserved(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $service->requestRevision($released, $member, 'Round one comments.', EditorialRevisionRequest::TYPE_REVISION);
        $service->releaseForCustomerPreview($application->fresh(), $editor);
        $service->requestRevision($application->fresh(), $member, 'Round two comments.', EditorialRevisionRequest::TYPE_REVISION);

        $this->assertSame(2, EditorialRevisionRequest::query()->where('application_id', $application->id)->count());
        $this->assertTrue(
            EditorialRevisionRequest::query()->where('application_id', $application->id)->where('round_number', 1)->exists()
        );
    }

    public function test_customer_approval_is_authenticated_and_version_specific(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $englishId = (int) $released->preview_english_editorial_content_id;

        $this->actingAs($member)->post(route('applications.preview.approve', $released), [
            'english_editorial_content_id' => $englishId,
            'confirm_approval' => '1',
        ])->assertRedirect(route('applications.preview', $released));

        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->fresh()->status);
        $this->assertSame($englishId, (int) $application->fresh()->customer_approved_english_editorial_content_id);

        // Stale/wrong version rejected.
        [$editor2, $member2, $application2] = $this->seedApprovedPreviewReady();
        $released2 = $service->releaseForCustomerPreview($application2, $editor2);
        $this->expectException(\InvalidArgumentException::class);
        $service->approvePreview($released2, $member2, $englishId + 99999);
    }

    public function test_content_change_after_approval_invalidates_and_requires_renewed_approval(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $approval = $service->approvePreview($released, $member, (int) $released->preview_english_editorial_content_id);
        $this->assertTrue($approval->isActive());

        $english = EditorialContent::query()->findOrFail($released->preview_english_editorial_content_id);
        $originalBody = $english->body;

        $successor = app(EditorialContentVersioningService::class)->applyEditorUpdate(
            $english,
            [
                'title' => $english->title,
                'summary' => $english->summary,
                'body' => $english->body."\n\nEditor revised after approval.",
                'status' => EditorialContent::STATUS_DRAFT,
                'source_material' => $english->source_material,
            ],
            $editor,
        );

        $this->assertNotSame($english->id, $successor->id);
        $this->assertSame($originalBody, $english->fresh()->body);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertSame(2, $successor->version_number);
        $this->assertNotNull($approval->fresh()->invalidated_at);
        $this->assertNull($application->fresh()->customer_approved_at);
        $this->assertSame(Application::STATUS_IN_EDITORIAL_REVIEW, $application->fresh()->status);
        $this->assertNull($application->fresh()->customer_preview_released_at);
    }

    public function test_approved_editorial_narrative_is_immutable_and_creates_new_version(): void
    {
        [$editor, , $application, $english] = $this->seedApprovedPreviewReady();
        $versioning = app(EditorialContentVersioningService::class);
        $this->assertTrue($versioning->isNarrativeImmutable($english));

        $successor = $versioning->applyEditorUpdate($english, [
            'title' => 'Revised Title',
            'summary' => $english->summary,
            'body' => 'Completely revised body for editorial round.',
            'status' => EditorialContent::STATUS_DRAFT,
            'source_material' => 'human edit',
        ], $editor);

        $this->assertSame($english->title, $english->fresh()->title);
        $this->assertNotSame($english->id, $successor->id);
        $this->assertSame('Revised Title', $successor->title);
        $this->assertFalse($successor->ai_generated);
        $this->assertSame((int) $application->profile_id, (int) $successor->profile_id);
    }

    public function test_customer_cannot_directly_edit_biography_body_via_preview_routes(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $englishId = (int) $released->preview_english_editorial_content_id;
        $before = EditorialContent::query()->findOrFail($englishId)->body;

        $this->actingAs($member)->post(route('applications.preview.approve', $released), [
            'english_editorial_content_id' => $englishId,
            'confirm_approval' => '1',
            'body' => '<script>alert(1)</script>Hacked biography',
            'status' => Application::STATUS_PUBLISHED,
        ])->assertRedirect();

        $this->assertSame($before, EditorialContent::query()->findOrFail($englishId)->body);
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_factual_correction_does_not_consume_included_revision_round(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);

        $req = $service->requestRevision(
            $released,
            $member,
            'The year should be 2011 not 2012.',
            EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION,
        );

        $this->assertNull($req->round_number);
        $this->assertSame(0, $application->fresh()->included_revision_rounds_used);
        $this->assertSame(EditorialRevisionRequest::TYPE_FACTUAL_CORRECTION, $req->request_type);
    }

    public function test_customer_approval_does_not_grant_publication_authority(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $service->approvePreview($released, $member, (int) $released->preview_english_editorial_content_id);

        $this->expectException(\InvalidArgumentException::class);
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $member, true);
    }

    public function test_normal_publication_requires_approved_state_and_preserves_approved_version(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $admin = User::factory()->admin()->create();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);
        $englishId = (int) $released->preview_english_editorial_content_id;
        $service->approvePreview($released, $member, $englishId);

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame($englishId, (int) $published->customer_approved_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, EditorialContent::query()->findOrFail($englishId)->status);
    }

    public function test_xss_in_revision_text_is_sanitised_and_escaped_in_preview(): void
    {
        [$editor, $member, $application] = $this->seedApprovedPreviewReady();
        $service = app(CustomerEditorialWorkflowService::class);
        $released = $service->releaseForCustomerPreview($application, $editor);

        $req = $service->requestRevision(
            $released,
            $member,
            "<script>alert('xss')</script>Please fix the spelling of Thrissur.",
            EditorialRevisionRequest::TYPE_REVISION,
        );

        $this->assertStringNotContainsString('<script>', $req->request_text);
        $this->assertStringContainsString('Please fix the spelling of Thrissur.', $req->request_text);
    }

    public function test_in_memoriam_excluded_from_customer_preview_workflow(): void
    {
        $editor = User::factory()->editor()->create();
        $application = Application::factory()->paid()->create([
            'package_tier' => 'in_memoriam',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $editor);
    }

    /**
     * @return array{0: User, 1: User, 2: Application}
     */
    private function seedAiDraft(): array
    {
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Preview Leader',
            'preferred_display_name' => 'Preview Leader',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);
        InterviewAnswer::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'question_id' => 'q1',
            'original_answer' => 'Served locally since 2010.',
            'answered_at' => now(),
        ]);

        app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $editor);

        return [$editor, $member, $application->fresh()];
    }

    /**
     * @return array{0: User, 1: User, 2: Application, 3: EditorialContent}
     */
    private function seedApprovedPreviewReady(): array
    {
        [$editor, $member, $application] = $this->seedAiDraft();
        $runEnglishId = $application->fresh()->profile_id;
        $this->assertNotNull($runEnglishId);

        $english = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->orderByDesc('version_number')
            ->firstOrFail();
        $english->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
        ])->save();

        $malayalam = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->orderByDesc('version_number')
            ->first();
        if ($malayalam) {
            $malayalam->forceFill([
                'status' => EditorialContent::STATUS_APPROVED,
                'reviewed_by_id' => $editor->id,
            ])->save();
        }

        return [$editor, $member, $application->fresh(), $english->fresh()];
    }
}
