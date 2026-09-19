<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileMediaService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberPostApprovalLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_upload_photo_after_customer_approval_via_http(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApprovedApplication();

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('after-approval.jpg', 400, 500),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, MediaItem::query()->count());
    }

    public function test_member_cannot_change_slug_after_customer_approval(): void
    {
        [$member, $application] = $this->seedApprovedApplication();
        $profile = $application->profile;
        $this->assertNotNull($profile);

        $this->expectException(\InvalidArgumentException::class);
        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile,
            $member,
            'new-approved-slug',
            (string) $application->package_tier,
        );
    }

    public function test_staff_can_still_upload_photo_after_member_approval(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApprovedApplication();
        $editor = User::factory()->editor()->create();
        $profile = $application->profile;
        $this->assertNotNull($profile);

        $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('staff-correction.jpg', 400, 500),
            $editor,
        );

        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->review_status);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function seedApprovedApplication(): array
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'package_tier' => 'accomplished',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'customer_approved_at' => now(),
            'customer_approved_by_user_id' => $member->id,
        ]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Approved Member',
            'display_name' => 'Approved Member',
            'profession' => 'Leader',
            'slug' => 'approved-member-lock',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application->forceFill([
            'profile_id' => $profile->id,
        ])->save();

        return [$member, $application->fresh()];
    }
}
