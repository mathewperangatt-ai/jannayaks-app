<?php

namespace Tests\Feature;

use App\Contracts\EditorialAiClient;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\InterviewAnswer;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\Ai\FakeEditorialAiClient;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\EditorialGenerationService;
use App\Services\PublicProfilePresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.ai.provider', 'fake');
        Config::set('jannayaks.ai.malayalam_pipeline_enabled', true);
        $this->app->instance(EditorialAiClient::class, new FakeEditorialAiClient);
    }

    public function test_approved_version_a_stays_public_while_successor_b_is_revised(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();

        $this->get('/'.$application->profile->slug)
            ->assertOk()
            ->assertSee('Public biography version A', false);

        $versionB = app(EditorialContentVersioningService::class)->applyEditorUpdate($versionA, [
            'title' => $versionA->title,
            'summary' => $versionA->summary,
            'body' => 'Unapproved successor biography version B',
            'status' => EditorialContent::STATUS_APPROVED,
            'source_material' => 'human edit',
        ], $editor);

        $this->assertSame(EditorialContent::STATUS_DRAFT, $versionB->status);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $versionA->fresh()->status);

        $html = $this->get('/'.$application->profile->fresh()->slug)->assertOk()->getContent();

        $this->assertStringContainsString('Public biography version A', $html);
        $this->assertStringNotContainsString('Unapproved successor biography version B', $html);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('Replacement publication must not proceed while the application is still marked published.');
        } catch (\InvalidArgumentException) {
            // Expected until B is customer-approved and awaiting publication.
        }
    }

    public function test_successor_cannot_become_public_without_fresh_customer_approval(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();

        $versionB = $this->createApprovedSuccessor($versionA, $editor, 'Successor B awaiting approval');

        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application->fresh(), $editor);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('Successor B must not publish without customer approval.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('customer approval', strtolower($e->getMessage()));
        }

        $this->get('/'.$application->profile->fresh()->slug)
            ->assertOk()
            ->assertSee('Public biography version A', false)
            ->assertDontSee('Successor B awaiting approval', false);
    }

    public function test_invalidating_version_a_approval_does_not_expose_approved_successor(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();
        $versionB = $this->createApprovedSuccessor($versionA, $editor, 'Approved but unpublished successor B');

        app(CustomerEditorialWorkflowService::class)->invalidateApprovalForEditorialContent(
            $versionA->fresh(),
            $editor,
            'Simulated invalidation of A',
        );

        $presented = app(PublicProfilePresentationService::class)->present($application->profile->fresh());
        $this->assertSame($versionA->id, $presented['english']?->id);
        $this->assertSame('Public biography version A', $presented['english']?->body);

        $this->get('/'.$application->profile->slug)
            ->assertOk()
            ->assertSee('Public biography version A', false)
            ->assertDontSee('Approved but unpublished successor B', false);
    }

    public function test_modified_after_approval_content_cannot_be_published_under_old_approval(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();
        $versionB = $this->createApprovedSuccessor($versionA, $editor, 'Exact approved successor B');

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $this->assertSame($versionB->id, (int) $released->preview_english_editorial_content_id);
        $workflow->approvePreview($released, $member, $versionB->id);

        app(EditorialContentVersioningService::class)->applyEditorUpdate($versionB->fresh(), [
            'title' => $versionB->title,
            'summary' => $versionB->summary,
            'body' => 'Modified after customer approval',
            'status' => EditorialContent::STATUS_APPROVED,
            'source_material' => 'post-approval edit',
        ], $editor);

        $this->assertNull($application->fresh()->customer_approved_at);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('Modified-after-approval content must not publish under the old approval.');
        } catch (\InvalidArgumentException) {
            // Expected: approval was invalidated by the post-approval edit.
        }

        $this->get('/'.$application->profile->fresh()->slug)
            ->assertOk()
            ->assertSee('Public biography version A', false)
            ->assertDontSee('Modified after customer approval', false);
    }

    public function test_deliberate_replacement_publication_swaps_public_content(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();
        $livePublishedAt = $application->profile->fresh()->published_at;
        $versionB = $this->createApprovedSuccessor($versionA, $editor, 'Replacement biography version B');

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $this->assertSame('published', $application->profile->fresh()->status);

        $workflow->approvePreview($released, $member, $versionB->id);
        $this->assertSame('published', $application->profile->fresh()->status);
        $this->get('/'.$application->profile->slug)
            ->assertOk()
            ->assertSee('Public biography version A', false)
            ->assertDontSee('Replacement biography version B', false);

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $this->assertSame($versionB->id, (int) $published->published_english_editorial_content_id);
        $this->assertEquals($livePublishedAt?->toDateTimeString(), $published->profile->fresh()->published_at?->toDateTimeString());

        $this->get('/'.$published->profile->slug)
            ->assertOk()
            ->assertSee('Replacement biography version B', false)
            ->assertDontSee('Public biography version A', false);

        $this->assertTrue(StaffActionLog::query()->where('action', 'application.published.replacement')->exists());
    }

    public function test_unpublished_profile_is_not_publicly_accessible_by_slug(): void
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Hidden Person',
            'display_name' => 'Hidden Person',
            'slug' => 'hidden.person',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'preferred_slug' => 'hidden.person',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $this->get('/hidden.person')->assertNotFound();
        $this->get('/p/hidden.person')->assertNotFound();
    }

    public function test_staff_form_cannot_publish_and_members_cannot_write_biography(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $pending = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
        ]);

        try {
            app(ApplicationWorkflowService::class)->updateStaffFields(
                $pending,
                ['status' => Application::STATUS_PUBLISHED],
                $admin,
            );
            $this->fail('Staff form status must not publish.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Publish action', $e->getMessage());
        }

        $this->assertNotSame(Application::STATUS_PUBLISHED, $pending->fresh()->status);

        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $versionA->id,
            'confirm_approval' => '1',
            'body' => 'Forged biography overwrite',
        ]);

        $this->assertSame('Public biography version A', $versionA->fresh()->body);
        $this->assertSame($versionA->id, (int) $application->fresh()->published_english_editorial_content_id);
    }

    public function test_legacy_approved_row_without_published_binding_is_not_exposed(): void
    {
        [$admin, $editor, $member, $application, $versionA] = $this->publishApprovedVersion();
        $versionB = $this->createApprovedSuccessor($versionA, $editor, 'Legacy latest approved B');
        $versionB->forceFill(['status' => EditorialContent::STATUS_APPROVED])->save();

        $application->forceFill([
            'published_english_editorial_content_id' => null,
            'published_malayalam_editorial_content_id' => null,
            'customer_approved_english_editorial_content_id' => $versionB->id,
            'customer_approved_at' => now(),
        ])->save();

        $presented = app(PublicProfilePresentationService::class)->present($application->profile->fresh());
        $this->assertNull($presented['english']);

        $this->get('/'.$application->profile->slug)
            ->assertOk()
            ->assertDontSee('Legacy latest approved B', false)
            ->assertDontSee('Public biography version A', false);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Application, 4: EditorialContent}
     */
    private function publishApprovedVersion(): array
    {
        $editor = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Integrity Leader',
            'preferred_display_name' => 'Integrity Leader',
            'package_tier' => 'accomplished',
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
        $application = $application->fresh();

        $english = EditorialContent::query()
            ->where('profile_id', $application->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->orderByDesc('version_number')
            ->firstOrFail();
        $english->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
            'body' => 'Public biography version A',
        ])->save();

        $workflow = app(CustomerEditorialWorkflowService::class);
        $released = $workflow->releaseForCustomerPreview($application->fresh(), $editor);
        $workflow->approvePreview($released, $member, $english->id);
        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return [$admin, $editor, $member, $published->fresh(), $english->fresh()];
    }

    private function createApprovedSuccessor(EditorialContent $original, User $editor, string $body): EditorialContent
    {
        $successor = app(EditorialContentVersioningService::class)->applyEditorUpdate($original, [
            'title' => $original->title,
            'summary' => $original->summary,
            'body' => $body,
            'status' => EditorialContent::STATUS_APPROVED,
            'source_material' => 'human successor',
        ], $editor);

        $successor->forceFill([
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $editor->id,
        ])->save();

        return $successor->fresh();
    }
}
