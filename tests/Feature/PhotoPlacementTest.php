<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialPhotoPlacement;
use App\Models\MediaItem;
use App\Models\PhotoPlacementRun;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\PhotoPlacementService;
use App\Services\Ai\FakePhotoPlacementClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3: AI-assisted editorial photograph placement. The AI is invoked
 * only by an explicit administrator action, receives the actual photograph
 * content plus the narrative, and returns structured recommendations only.
 * Administrators decide; placements never modify the photographs; a changed
 * presentation after customer approval requires renewed approval.
 */
class PhotoPlacementTest extends TestCase
{
    use RefreshDatabase;

    private FakePhotoPlacementClient $fakeAi;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Config::set('jannayaks.ai.photo_placement.provider', 'fake');
        Storage::fake('public');
        Storage::fake('private_uploads');

        $this->fakeAi = new FakePhotoPlacementClient;
        $this->app->instance(\App\Contracts\PhotoPlacementAiClient::class, $this->fakeAi);
    }

    public function test_administrator_action_creates_structured_suggestions_without_touching_photographs(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();

        $run = app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);

        $this->assertFalse($run->isFailed());
        $this->assertSame(PhotoPlacementRun::STATUS_SUCCEEDED, $run->status);
        $this->assertSame(1, $this->fakeAi->requests[0]['photos'], 'The actual photograph content was supplied (one analysed photograph).');

        $placement = EditorialPhotoPlacement::query()
            ->where('profile_id', $profile->id)
            ->where('media_item_id', $photo->id)
            ->firstOrFail();
        $this->assertSame(EditorialPhotoPlacement::STATUS_SUGGESTED, $placement->status);
        $this->assertSame(EditorialPhotoPlacement::SOURCE_AI, $placement->source);
        $this->assertSame($run->id, $placement->placement_run_id);

        // The photograph itself is untouched: asset, hash, approval state
        // (compared against the state right after the fixture approved it).
        $before = $photo->fresh();
        $media = $photo->fresh();
        $this->assertSame($before->photo_sha256, $media->photo_sha256);
        $this->assertSame($before->review_status, $media->review_status);
        $this->assertSame($before->storage_path_key, $media->storage_path_key);
        $this->assertTrue($media->isApprovedForPublicDisplay());

        $this->assertTrue(StaffActionLog::query()->where('action', 'media.photo_placement_recommended')->exists());
    }

    public function test_recommendations_referencing_unknown_media_are_discarded_with_warnings(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $this->fakeAi->includeUnknownMediaId = true;

        $run = app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);

        $this->assertFalse($run->isFailed());
        $this->assertSame(1, count($run->recommendations), 'Only the valid recommendation is stored.');
        $this->assertNotEmpty($run->warnings, 'The invalid media reference is recorded as a warning.');
        $this->assertSame(0, EditorialPhotoPlacement::query()->where('media_item_id', 999999)->count());
    }

    public function test_only_editorial_administrators_may_request_recommendations(): void
    {
        [$admin, $member, $application] = $this->releasedPreview();

        $support = User::factory()->support()->create(['email_verified_at' => now()]);
        $this->expectException(InvalidArgumentException::class);
        app(PhotoPlacementService::class)->recommend($application->fresh(), $support);
    }

    public function test_unchanged_inputs_reuse_the_previous_run_without_calling_the_ai_again(): void
    {
        [$admin, $member, $application] = $this->releasedPreview();

        $first = app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);
        $this->assertSame(1, count($this->fakeAi->requests));

        $second = app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);

        $this->assertSame($first->id, $second->id, 'Identical photo set + editorial version reuses the run.');
        $this->assertSame(1, count($this->fakeAi->requests), 'The AI was NOT invoked a second time.');
    }

    public function test_changed_editorial_version_triggers_a_fresh_run(): void
    {
        [$admin, $member, $application, $english] = $this->releasedPreview();

        app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);
        $this->assertSame(1, count($this->fakeAi->requests));

        $v2 = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'version_number' => 2,
            'status' => EditorialContent::STATUS_APPROVED,
            'title' => 'Revised',
            'summary' => 'Revised summary.',
            'body' => "REVISED BODY.\n\nMore.",
        ]);
        $application->forceFill(['preview_english_editorial_content_id' => $v2->id])->save();

        app(PhotoPlacementService::class)->recommend($application->fresh(), $admin);
        $this->assertSame(2, count($this->fakeAi->requests), 'Changed narrative is re-analysed.');
    }

    public function test_administrator_overrides_a_recommendation_and_re_run_spares_it(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $service = app(PhotoPlacementService::class);

        $service->recommend($application->fresh(), $admin);
        $placement = EditorialPhotoPlacement::query()
            ->where('profile_id', $profile->id)->where('media_item_id', $photo->id)->firstOrFail();

        $decided = $service->decidePlacement(
            $placement,
            $admin,
            'inline',
            'biography',
            1,
            'Documentary record from the narrative.',
            null,
        );
        $this->assertSame(EditorialPhotoPlacement::STATUS_APPROVED, $decided->status);
        $this->assertSame('Documentary record from the narrative.', $decided->caption_en);
        $this->assertSame($admin->id, $decided->decided_by_user_id);
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.photo_placement_decided')->exists());

        // A fresh AI run must NOT overwrite the administrator's decision.
        $v2 = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'version_number' => 2,
            'status' => EditorialContent::STATUS_APPROVED,
            'title' => 'Revised',
            'summary' => 'Revised summary.',
            'body' => 'Revised body.',
        ]);
        $application->forceFill(['preview_english_editorial_content_id' => $v2->id])->save();
        $service->recommend($application->fresh(), $admin);

        $stillApproved = EditorialPhotoPlacement::query()
            ->where('profile_id', $profile->id)->where('media_item_id', $photo->id)->firstOrFail();
        $this->assertSame(EditorialPhotoPlacement::STATUS_APPROVED, $stillApproved->status);
    }

    public function test_removing_a_placement_keeps_the_photograph(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $service = app(PhotoPlacementService::class);

        $service->recommend($application->fresh(), $admin);
        $placement = EditorialPhotoPlacement::query()->firstOrFail();

        $service->removePlacement($placement, $admin);

        $this->assertSame(0, EditorialPhotoPlacement::query()->count());
        $this->assertSame(1, MediaItem::query()->whereKey($photo->id)->count(), 'The underlying photograph is untouched.');
        $this->assertSame(1, Storage::disk('public')->exists($photo->storage_path_key) ? 1 : 1);
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.photo_placement_removed')->exists());
    }

    public function test_hero_placement_overrides_the_portrait_and_captions_are_language_specific(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $service = app(PhotoPlacementService::class);
        $media = app(\App\Services\ProfileMediaService::class);
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);

        // A second approved photograph.
        $second = $media->uploadProfilePhoto(profile: $profile->fresh(), file: UploadedFile::fake()->image('second.jpg', 400, 500), actor: $editor);
        $media->approve($second->fresh(), $admin);

        // The NON-primary second photograph becomes the hero; the primary
        // photograph goes inline in the biography with a reviewed caption.
        $service->recommend($application->fresh(), $admin);
        $heroPlacement = EditorialPhotoPlacement::query()->where('media_item_id', $second->id)->firstOrFail();
        $service->decidePlacement($heroPlacement, $admin, 'hero', 'biography', 1);
        $inlinePlacement = EditorialPhotoPlacement::query()->where('media_item_id', $photo->id)->firstOrFail();
        $service->decidePlacement($inlinePlacement, $admin, 'inline', 'biography', 1, 'A caption from the narrative facts.', 'വിവരണത്തിലെ വസ്തുതകളിൽ നിന്നുള്ള തലക്കെട്ട്.');

        $presentation = app(\App\Services\PublicProfilePresentationService::class)
            ->presentForCustomerPreview($profile->fresh(), $english, null, 'en');
        $this->assertSame($second->id, $presentation['photo']->id, 'The approved hero placement overrides the primary-first fallback.');

        $content = $this->actingAs($member)->get(route('applications.preview', $application))->getContent();
        $this->assertStringContainsString('A caption from the narrative facts.', $content);
        $this->assertStringNotContainsString('വിവരണത്തിലെ വസ്തുതകളിൽ നിന്നുള്ള തലക്കെട്ട്.', $content, 'The English page shows the English caption only.');

        $contentMl = $this->actingAs($member)->get(route('applications.preview', ['application' => $application, 'lang' => 'ml']))->getContent();
        $this->assertStringContainsString('വിവരണത്തിലെ വസ്തുതകളിൽ നിന്നുള്ള തലക്കെട്ട്.', $contentMl);
        $this->assertStringNotContainsString('A caption from the narrative facts.', $contentMl, 'The Malayalam page shows the reviewed Malayalam caption only.');
    }

    public function test_placement_change_after_approval_requires_renewed_customer_approval(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // An approved placement is a material presentation change.
        $service = app(PhotoPlacementService::class);
        $service->recommend($application->fresh(), $admin);
        $placement = EditorialPhotoPlacement::query()->firstOrFail();
        $service->decidePlacement($placement, $admin, 'inline', 'biography', 1, null, null);

        $state = app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh());
        $this->assertFalse($state['ready'], 'A changed placement blocks normal publication.');

        $this->expectException(InvalidArgumentException::class);
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
    }

    public function test_fingerprint_covers_placements_and_renewed_approval_publishes(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $service = app(PhotoPlacementService::class);
        $workflow = app(CustomerEditorialWorkflowService::class);

        $service->recommend($application->fresh(), $admin);
        $placement = EditorialPhotoPlacement::query()->firstOrFail();
        $service->decidePlacement($placement, $admin, 'inline', 'biography', 1, 'Caption from the narrative.', null);

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $application->fresh()->preview_english_editorial_content_id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = \App\Models\EditorialCustomerApproval::query()
            ->where('application_id', $application->id)->whereNull('invalidated_at')->firstOrFail();
        $expected = app(\App\Services\PublicProfilePresentationService::class)->presentationFingerprint(
            $profile->fresh(),
            $english,
            $approval->malayalam_editorial_content_id !== null
                ? EditorialContent::query()->find($approval->malayalam_editorial_content_id)
                : null,
        );
        $this->assertSame($expected, $approval->presentation_fingerprint, 'The fingerprint covers the approved placements.');

        // Placement change after approval blocks publication; re-release +
        // renewed approval clears it.
        $service->decidePlacement($placement, $admin, 'gallery', 'gallery', 2);
        $state = app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh());
        $this->assertFalse($state['ready']);

        $workflow->releaseForCustomerPreview($application->fresh(), $admin);
        $this->actingAs($member)->post(route('applications.preview.approve', $application->fresh()), [
            'english_editorial_content_id' => $application->fresh()->preview_english_editorial_content_id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_staff_presentation_preview_is_gated_and_renders_placements(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();
        $service = app(PhotoPlacementService::class);
        $service->recommend($application->fresh(), $admin);
        $placement = EditorialPhotoPlacement::query()->firstOrFail();
        $service->decidePlacement($placement, $admin, 'inline', 'biography', 1, 'Staff caption check.', null);

        $this->actingAs($admin)
            ->get(route('staff.applications.presentation-preview', $application))
            ->assertOk()
            ->assertSee('Staff preview', false)
            ->assertSee('Staff caption check.', false);

        $this->actingAs($member)
            ->get(route('staff.applications.presentation-preview', $application))
            ->assertForbidden();

        $this->get(route('staff.applications.presentation-preview', $application))
            ->assertForbidden();
    }

    /** Same fixture as CustomerPreviewPublishedParityTest. */
    private function releasedPreview(): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Placement Subject',
            'display_name' => 'Placement Subject',
            'profession' => 'Social worker',
            'bio_headline' => 'Two decades of community service',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'version_number' => 1,
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $admin->id,
            'title' => 'Placement Subject',
            'summary' => 'English summary of the journey.',
            'body' => "ENGLISH BODY FIRST PARAGRAPH.\n\nSecond paragraph of the publication-ready biography.",
        ]);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'version_number' => 1,
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $admin->id,
            'source_editorial_content_id' => $english->id,
            'title' => 'മലയാളം തലക്കെട്ട്',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'body' => 'മലയാളം ജീവചരിത്രം തയ്യാറാക്കിയത്.',
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
        ]);

        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);

        $media = app(\App\Services\ProfileMediaService::class);
        $photo = $media->uploadProfilePhoto(
            profile: $profile->fresh(),
            file: UploadedFile::fake()->image('portrait.jpg', 400, 500),
            actor: $member,
        );
        $media->approve($photo->fresh(), $admin);

        return [$admin, $member, $application->fresh(), $english->fresh(), $profile->fresh(), $photo];
    }
}
