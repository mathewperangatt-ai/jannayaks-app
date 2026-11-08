<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin "Approve English" for the English editorial master before the first
 * customer preview, on a waived ordinary application.
 */
class EnglishMasterApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_release_is_refused_until_english_is_approved(): void
    {
        [$admin, $application] = $this->waivedApplicationWithDrafts();

        try {
            app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);
            $this->fail('Release should require an approved English master.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Approve the English editorial master before releasing customer preview.', $e->getMessage());
        }
    }

    public function test_admin_sees_approve_and_edit_english_on_view_and_edit_pages(): void
    {
        [$admin, $application, $english] = $this->waivedApplicationWithDrafts();

        foreach ([ViewApplication::class, EditApplication::class] as $page) {
            Livewire::actingAs($admin)
                ->test($page, ['record' => $application->getRouteKey()])
                ->assertSuccessful()
                ->assertActionVisible('editEnglishDraft')
                ->assertActionVisible('approveEnglishMaster')
                ->assertActionVisible('releaseCustomerPreview');
        }

        $this->assertStringContainsString(
            '/admin/editorial-contents/'.$english->id.'/edit',
            (string) Livewire::actingAs($admin)
                ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
                ->instance()
                ->getAction('editEnglishDraft')
                ->getUrl(),
        );
    }

    public function test_editor_may_edit_but_not_approve_english_and_support_sees_neither(): void
    {
        [, $application] = $this->waivedApplicationWithDrafts();
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $support = User::factory()->support()->create(['email_verified_at' => now()]);

        Livewire::actingAs($editor)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionVisible('editEnglishDraft')
            ->assertActionHidden('approveEnglishMaster');

        Livewire::actingAs($support)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('editEnglishDraft')
            ->assertActionHidden('approveEnglishMaster');

        foreach ([$editor, User::factory()->admin()->suspended()->create()] as $actor) {
            try {
                app(ApplicationWorkflowService::class)->approveEnglishMaster($application, $actor);
                $this->fail('Only an active Admin may approve the English master.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Only an authorised Admin may approve the English editorial master.', $e->getMessage());
            }
        }

        $this->assertSame(EditorialContent::STATUS_DRAFT, EditorialContent::query()
            ->where('profile_id', $application->profile_id)->where('language', 'en')->value('status'));
    }

    public function test_admin_approval_records_reviewer_keeps_history_and_does_not_publish(): void
    {
        [$admin, $application, $english, $malayalam] = $this->waivedApplicationWithDrafts();
        $statusBefore = $application->status;

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('approveEnglishMaster')
            ->assertHasNoActionErrors();

        $approved = $english->fresh();
        $this->assertSame(EditorialContent::STATUS_APPROVED, $approved->status);
        $this->assertSame($admin->id, (int) $approved->reviewed_by_id);
        $this->assertSame(1, (int) $approved->version_number);
        $this->assertSame('English master body for review.', $approved->body);
        $this->assertSame(1, EditorialContent::query()->where('profile_id', $application->profile_id)->where('language', 'en')->count());

        $ml = $malayalam->fresh();
        $this->assertSame(EditorialContent::STATUS_DRAFT, $ml->status);
        $this->assertSame($english->id, (int) $ml->source_editorial_content_id);

        $application->refresh();
        $this->assertSame($statusBefore, $application->status);
        $this->assertNull($application->preview_english_editorial_content_id);
        $this->assertNull($application->customer_approved_at);
        $this->assertNull($application->published_english_editorial_content_id);

        $log = StaffActionLog::query()->where('action', 'editorial.english_master_approved')->sole();
        $this->assertSame($admin->id, (int) $log->actor_user_id);
        $this->assertSame($english->id, (int) $log->subject_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $log->before['status']);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $log->after['status']);
        $this->assertNotEmpty($log->after['approved_at']);

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('approveEnglishMaster')
            ->assertActionHidden('editEnglishDraft');

        $released = app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);
        $this->assertSame($english->id, (int) $released->preview_english_editorial_content_id);
        $this->assertNull($released->customer_approved_at);
        $this->assertNotSame(Application::STATUS_PUBLISHED, $released->status);
    }

    public function test_a_newer_draft_is_approved_without_touching_the_earlier_approved_version(): void
    {
        [$admin, $application, $english] = $this->waivedApplicationWithDrafts();
        $service = app(ApplicationWorkflowService::class);
        $service->approveEnglishMaster($application, $admin);

        $this->assertNull($service->englishMasterAwaitingApproval($application->fresh()));
        try {
            $service->approveEnglishMaster($application, $admin);
            $this->fail('Nothing should await approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('There is no English editorial draft awaiting approval.', $e->getMessage());
        }

        $successor = $this->english($application->profile, 2, 'Edited English body.');
        $this->assertSame($successor->id, $service->englishMasterAwaitingApproval($application->fresh())?->id);

        $service->approveEnglishMaster($application, $admin);

        $this->assertSame(EditorialContent::STATUS_APPROVED, $successor->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertSame('English master body for review.', $english->fresh()->body);
    }

    public function test_published_application_hides_approve_english(): void
    {
        [$admin, $application] = $this->waivedApplicationWithDrafts();
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->fresh()->getRouteKey()])
            ->assertActionHidden('approveEnglishMaster')
            ->assertActionHidden('editEnglishDraft');
    }

    /**
     * @return array{0: User, 1: Application, 2: EditorialContent, 3: EditorialContent}
     */
    private function waivedApplicationWithDrafts(): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'English Approval Subject',
            'display_name' => 'English Approval Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Kozhikode',
            'source_method' => 'online_interview',
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);

        app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $admin, 'Production QA waiver.');
        $application->refresh()->forceFill(['status' => Application::STATUS_IN_EDITORIAL_REVIEW])->save();

        $english = $this->english($profile, 1, 'English master body for review.');
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം',
            'body' => 'മലയാളം കരട്.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => true,
            'source_editorial_content_id' => $english->id,
        ]);

        return [$admin, $application->fresh(), $english, $malayalam];
    }

    private function english(Profile $profile, int $version, string $body): EditorialContent
    {
        return EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => $version,
            'title' => 'English title v'.$version,
            'body' => $body,
            'summary' => 'Summary',
            'source_material' => '',
            'ai_generated' => true,
        ]);
    }
}
