<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Separation of duties: the uploader of a profile photograph must never be
 * able to approve that same photograph (authoritative service-level rule).
 */
class PhotoSelfApprovalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_approve_own_uploaded_photo(): void
    {
        Storage::fake('public');
        [$editor, $application] = $this->seedApplicationWithEditor();
        $profile = $application->profile;
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('self-approved.jpg', 400, 500),
            $editor,
        );
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->review_status);

        $denied = false;
        try {
            $service->approve($photo->fresh(), $editor);
        } catch (ValidationException $e) {
            $denied = true;
            $this->assertArrayHasKey('media', $e->errors());
        }

        $this->assertTrue($denied, 'Self-approval must be denied.');

        // No partial approval state: still pending, still private, not publicly served.
        $photo->refresh();
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $photo->privacy);
        $this->assertNull($photo->reviewed_by_user_id);
        $this->assertNull($photo->reviewed_at);

        $this->get(route('profiles.public.photo', [$profile, $photo]))->assertNotFound();

        // The denial itself is audited.
        $this->assertTrue(
            StaffActionLog::query()
                ->where('action', 'media.profile_photo_approval_denied')
                ->where('subject_id', $photo->id)
                ->where('actor_user_id', $editor->id)
                ->exists()
        );
    }

    public function test_different_editor_can_approve_photo_uploaded_by_another_editor(): void
    {
        Storage::fake('public');
        [$editorA, $application] = $this->seedApplicationWithEditor();
        $editorB = User::factory()->editor()->create();
        $profile = $application->profile;
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('two-person.jpg', 400, 500),
            $editorA,
        );

        $approved = $service->approve($photo->fresh(), $editorB, 'Reviewed');

        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);
        $this->assertSame($editorB->id, (int) $approved->reviewed_by_user_id);

        $this->assertTrue(
            StaffActionLog::query()
                ->where('action', 'media.profile_photo_approved')
                ->where('subject_id', $approved->id)
                ->where('actor_user_id', $editorB->id)
                ->exists()
        );
    }

    public function test_admin_cannot_approve_own_uploaded_photo_but_another_admin_can(): void
    {
        Storage::fake('public');
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => User::factory()->create(['role' => User::ROLE_MEMBER])->id,
            'package_tier' => 'accomplished',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'customer_approved_at' => now(),
            'customer_approved_by_user_id' => null,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $application->user_id,
            'status' => 'member_approved',
            'full_name' => 'Admin Upload Leader',
            'display_name' => 'Admin Upload Leader',
            'profession' => 'Leader',
            'slug' => 'admin-upload-leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $service = app(ProfileMediaService::class);
        $photo = $service->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('admin-upload.jpg', 400, 500),
            $adminA,
        );

        $denied = false;
        try {
            $service->approve($photo->fresh(), $adminA);
        } catch (ValidationException) {
            $denied = true;
        }
        $this->assertTrue($denied, 'Admin self-approval must be denied.');
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->fresh()->review_status);

        $approved = $service->approve($photo->fresh(), $adminB);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);
    }

    public function test_member_cannot_approve_photos(): void
    {
        Storage::fake('public');
        $member = User::factory()->create(['role' => User::ROLE_MEMBER, 'email_verified_at' => now()]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
            'customer_approved_by_user_id' => null,
        ]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Member Photo Leader',
            'display_name' => 'Member Photo Leader',
            'profession' => 'Leader',
            'slug' => 'member-photo-leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $service = app(ProfileMediaService::class);
        $photo = $service->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('member-upload.jpg', 400, 500),
            $member,
        );

        $denied = false;
        try {
            $service->approve($photo->fresh(), $member);
        } catch (ValidationException $e) {
            $denied = true;
            $this->assertSame('You are not allowed to approve photographs.', $e->errors()['media'][0]);
        }

        $this->assertTrue($denied, 'Members must never approve photographs.');
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->fresh()->review_status);
    }

    public function test_missing_uploader_identity_fails_closed(): void
    {
        Storage::fake('public');
        [, $application] = $this->seedApplicationWithEditor(memberApproved: false);
        $editorB = User::factory()->editor()->create();
        $profile = $application->profile;
        $member = User::query()->findOrFail($application->user_id);
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('legacy.jpg', 400, 500),
            $member,
        );

        // Simulate a legacy row whose uploader user row was deleted (set null).
        $photo->forceFill(['uploaded_by_id' => null])->save();

        $denied = false;
        try {
            $service->approve($photo->fresh(), $editorB);
        } catch (ValidationException $e) {
            $denied = true;
            $this->assertStringContainsString('no recorded uploader', $e->errors()['media'][0]);
        }

        $this->assertTrue($denied, 'Missing uploader identity must fail closed.');
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->fresh()->review_status);
    }

    /**
     * Seed a paid application with a profile, plus the editor who will
     * (attempt to) work on its media. When $memberApproved is false the
     * application is still pre-approval so the member may upload photos.
     *
     * @return array{0: User, 1: Application}
     */
    private function seedApplicationWithEditor(?User $editor = null, bool $memberApproved = true): array
    {
        $editor ??= User::factory()->editor()->create();
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'status' => $memberApproved
                ? Application::STATUS_AWAITING_PUBLICATION
                : Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'customer_approved_at' => $memberApproved ? now() : null,
            'customer_approved_by_user_id' => $memberApproved ? $member->id : null,
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Photo Security Leader',
            'display_name' => 'Photo Security Leader',
            'profession' => 'Leader',
            'slug' => 'photo-security-leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application->forceFill(['profile_id' => $profile->id])->save();

        return [$editor, $application->fresh()];
    }
}
