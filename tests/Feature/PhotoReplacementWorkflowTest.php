<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\PendingPhotoReplacement;
use App\Models\ProfileIntegritySnapshot;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\PhotoReplacementRequestService;
use App\Services\ProfileIntegrityService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Wave 3 — member-submitted photograph replacement lifecycle: submission →
 * screening → Security Administrator review → approval (publication through
 * the authorised media workflow) or rejection, with notifications.
 */
class PhotoReplacementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('private_uploads');
        Mail::fake();
        config([
            'jannayaks.security.photo_moderation.enabled' => true,
            'jannayaks.ai.openai.api_key' => 'test-key-'.str_repeat('a', 20),
        ]);
    }

    /** Per-test moderation stubs: Http::fake stubs are first-match-wins. */
    private function fakeModerationClear(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response([
                'results' => [['categories' => ['sexual' => false], 'category_scores' => ['sexual' => 0.01]]],
            ], 200),
        ]);
    }

    private function fakeModerationFlagged(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response([
                'results' => [['categories' => ['sexual' => true], 'category_scores' => ['sexual' => 0.98]]],
            ], 200),
        ]);
    }

    private function fakeModerationOutage(): void
    {
        Http::fake([
            'api.openai.com/v1/moderations' => Http::response([], 500),
        ]);
    }

    private function seedPublishedWithPhoto(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'source_method' => 'admin_test_demo',
            'full_name' => 'Photo Replacement Fixture',
            'preferred_display_name' => 'Photo Replacement Fixture',
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Photo Replacement Fixture',
            'display_name' => 'Photo Replacement Fixture',
            'slug' => 'photo-repl-'.strtolower(\Illuminate\Support\Str::random(8)),
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('original.jpg', 400, 500),
            $editor,
        );
        app(ProfileMediaService::class)->approve($photo, $reviewer);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Photo Replacement Fixture',
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

        return [
            'member' => $member,
            'admin' => $admin,
            'profile' => $profile->fresh(),
            'photo' => $photo->fresh(),
        ];
    }

    private function jpegUpload(int $width = 400, int $height = 500, string $name = 'replacement.jpg'): UploadedFile
    {
        // Textured content so the JPEG exceeds the small-file screening floor.
        $img = imagecreatetruecolor($width, $height);
        for ($i = 0; $i < 400; $i++) {
            imagefilledrectangle(
                $img,
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255)),
            );
        }
        ob_start();
        imagejpeg($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        $tmp = tempnam(sys_get_temp_dir(), 'jpgrepl');
        file_put_contents($tmp, $bytes);

        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    public function test_member_submission_enters_pending_review_without_touching_published_photos(): void
    {
        ['member' => $member, 'profile' => $profile, 'photo' => $photo] = $this->seedPublishedWithPhoto();
        $this->fakeModerationClear();

        $service = app(PhotoReplacementRequestService::class);
        $submission = $service->submit($profile, $member, $this->jpegUpload(), 'My new portrait.');

        $this->assertSame(PendingPhotoReplacement::STATUS_SUBMITTED, $submission->status);
        $this->assertSame(PendingPhotoReplacement::SCREENING_CLEAR, $submission->screening_result);

        // The published photograph is untouched; the submission lives on
        // PRIVATE storage, separate from the public R2-style disk.
        $this->assertTrue($photo->fresh()->is_primary);
        $this->assertStringStartsWith('photo-replacements/', $submission->storage_path);
        $this->assertTrue(Storage::disk('private_uploads')->exists($submission->storage_path));
        $this->assertFalse(Storage::disk('public')->exists($submission->storage_path));

        // No new published media rows appeared.
        $this->assertSame(1, MediaItem::query()
            ->where('mediable_type', Profile::class)->where('mediable_id', $profile->id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)->count());
    }

    public function test_other_members_cannot_submit_for_a_foreign_profile(): void
    {
        ['member' => $member, 'profile' => $profile] = $this->seedPublishedWithPhoto();
        $stranger = User::factory()->create(['role' => User::ROLE_MEMBER]);

        $this->expectException(\InvalidArgumentException::class);
        app(PhotoReplacementRequestService::class)->submit($profile, $stranger, $this->jpegUpload());
    }

    public function test_bad_images_are_flagged_for_manual_examination(): void
    {
        ['member' => $member, 'profile' => $profile] = $this->seedPublishedWithPhoto();

        $submission = app(PhotoReplacementRequestService::class)->submit(
            $profile, $member, $this->jpegUpload(120, 90, 'tiny.jpg'),
        );

        $this->assertSame(PendingPhotoReplacement::SCREENING_FLAGGED, $submission->screening_result);
        $this->assertContains('dimensions_below_minimum', $submission->screening_reasons);
    }

    public function test_approval_publishes_through_the_authorised_media_workflow(): void
    {
        ['member' => $member, 'profile' => $profile, 'photo' => $photo] = $this->seedPublishedWithPhoto();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        $beforeSnapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first()->snapshot_hash;
        $oldPrimaryId = $photo->id;
        $this->fakeModerationClear();

        $submission = app(PhotoReplacementRequestService::class)->submit($profile, $member, $this->jpegUpload());
        $approved = app(PhotoReplacementRequestService::class)->approve($submission, $securityAdmin, 'Approved.');

        $this->assertSame(PendingPhotoReplacement::STATUS_APPROVED, $approved->status);
        $this->assertNotNull($approved->published_at);
        $this->assertSame($securityAdmin->id, (int) $approved->reviewer_user_id);

        // The NEW photo is approved, primary and public; the OLD photo retired.
        $this->assertNotSame($oldPrimaryId, (int) $approved->fresh()->id);
        $newPrimary = MediaItem::query()
            ->where('mediable_type', Profile::class)->where('mediable_id', $profile->id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)->where('is_primary', true)->first();
        $this->assertNotNull($newPrimary);
        $this->assertNotSame($oldPrimaryId, (int) $newPrimary->id);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $newPrimary->review_status);
        $this->assertNull(MediaItem::query()->find($oldPrimaryId));

        // The trusted integrity baseline refreshed to the new photograph.
        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first();
        $this->assertNotSame($beforeSnapshot, $snapshot->snapshot_hash);
        $this->assertSame((int) $newPrimary->id, (int) $snapshot->primary_photo_media_id);

        // The member is notified of the approval.
        Mail::assertQueued(\App\Mail\PhotoReplacementDecisionMail::class, function (
            \App\Mail\PhotoReplacementDecisionMail $mail,
        ): bool {
            return $mail->approved === true;
        });

        // Authorisation is recorded.
        $this->assertTrue(StaffActionLog::query()->where('action', 'photo_replacement.approved')->exists());
    }

    public function test_flagged_submissions_require_manual_examination(): void
    {
        ['member' => $member, 'profile' => $profile] = $this->seedPublishedWithPhoto();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        $this->fakeModerationClear();
        $submission = app(PhotoReplacementRequestService::class)->submit(
            $profile, $member, $this->jpegUpload(120, 90, 'tiny.jpg'),
        );
        $this->assertSame(PendingPhotoReplacement::SCREENING_FLAGGED, $submission->screening_result);

        // Without explicit manual-examination confirmation, approval refuses.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('manual');
        app(PhotoReplacementRequestService::class)->approve($submission, $securityAdmin, '', confirmManualExamination: false);
    }

    public function test_rejection_leaves_published_content_untouched_and_notifies(): void
    {
        ['member' => $member, 'profile' => $profile, 'photo' => $photo] = $this->seedPublishedWithPhoto();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        $beforeSnapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first()->snapshot_hash;
        $this->fakeModerationClear();
        $submission = app(PhotoReplacementRequestService::class)->submit($profile, $member, $this->jpegUpload());
        $rejected = app(PhotoReplacementRequestService::class)->reject($submission, $securityAdmin, 'Image too dark.');

        $this->assertSame(PendingPhotoReplacement::STATUS_REJECTED, $rejected->status);
        $this->assertNotNull($rejected->reviewed_at);
        $this->assertNull($rejected->published_at);

        // Published photograph untouched; baseline unchanged.
        $this->assertTrue($photo->fresh()->is_primary);
        $this->assertSame($beforeSnapshot, ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->first()->snapshot_hash);

        Mail::assertQueued(\App\Mail\PhotoReplacementDecisionMail::class, function (
            \App\Mail\PhotoReplacementDecisionMail $mail,
        ): bool {
            return $mail->approved === false;
        });
    }

    public function test_content_moderation_flags_explicit_imagery(): void
    {
        ['member' => $member, 'profile' => $profile] = $this->seedPublishedWithPhoto();

        $this->fakeModerationFlagged();

        $submission = app(PhotoReplacementRequestService::class)->submit($profile, $member, $this->jpegUpload());

        $this->assertSame(\App\Security\PhotoModerationService::VERDICT_FLAGGED, $submission->moderation_result,
            'moderation_error='.var_export($submission->moderation_error, true).' categories='.var_export($submission->moderation_categories, true));

        // Approval without manual examination refuses even though the
        // deterministic file screening was clear.
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);
        $this->expectException(\InvalidArgumentException::class);
        app(PhotoReplacementRequestService::class)->approve($submission, $securityAdmin, '', confirmManualExamination: false);
    }

    public function test_moderation_outage_yields_uncertain_and_requires_manual_examination(): void
    {
        ['member' => $member, 'profile' => $profile] = $this->seedPublishedWithPhoto();
        $securityAdmin = User::factory()->create(['role' => User::ROLE_SECURITY_ADMIN]);

        // Provider unreachable: fail-safe 'uncertain', never auto-cleared.
        $this->fakeModerationOutage();

        $submission = app(PhotoReplacementRequestService::class)->submit($profile, $member, $this->jpegUpload());
        $this->assertSame(\App\Security\PhotoModerationService::VERDICT_UNCERTAIN, $submission->moderation_result);
        $this->assertNotNull($submission->moderation_error);

        $this->expectException(\InvalidArgumentException::class);
        app(PhotoReplacementRequestService::class)->approve($submission, $securityAdmin, '', confirmManualExamination: false);
    }

    public function test_strict_mode_blocks_the_existing_admin_from_review(): void
    {
        config(['jannayaks.security.integrity.published_profile_admin.allow_legacy_admin' => false]);
        ['member' => $member, 'profile' => $profile, 'admin' => $admin, 'photo' => $photo] = $this->seedPublishedWithPhoto();
        $this->fakeModerationClear();

        $submission = app(PhotoReplacementRequestService::class)->submit($profile, $member, $this->jpegUpload());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('recorded');
        try {
            app(PhotoReplacementRequestService::class)->approve($submission, $admin, '', false);
        } finally {
            $this->assertTrue(StaffActionLog::query()
                ->where('actor_user_id', $admin->id)
                ->where('action', 'security.published_profile_access_denied')->exists());
            // Published photos are untouched.
            $this->assertTrue($photo->fresh()->is_primary);
        }
    }
}
