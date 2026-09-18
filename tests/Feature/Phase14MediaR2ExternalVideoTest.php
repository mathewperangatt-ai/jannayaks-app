<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\SourceMaterial;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ExternalUrlValidator;
use App\Services\ExternalVideoLinkService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Phase14MediaR2ExternalVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('https://www.jannayaks.in');
        URL::forceScheme('https');
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_filesystem_r2_disk_is_environment_driven(): void
    {
        $r2 = config('filesystems.disks.r2');
        $this->assertSame('s3', $r2['driver']);
        $this->assertArrayHasKey('key', $r2);
        $this->assertArrayHasKey('secret', $r2);
        $this->assertArrayHasKey('bucket', $r2);
        $this->assertArrayHasKey('endpoint', $r2);
        $this->assertSame('public', config('jannayaks.media.public_disk'));
    }

    public function test_emerging_photo_limit_enforced_server_side(): void
    {
        [$member, $application, $profile] = $this->makeReadyApplication('emerging');
        $service = app(ProfileMediaService::class);

        $first = $service->uploadProfilePhoto($profile, $this->jpegUpload('a.jpg'), $member);
        $this->assertTrue($first->is_primary);
        $this->assertSame(MediaItem::REVIEW_PENDING, $first->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $first->privacy);

        try {
            $service->uploadProfilePhoto($profile, $this->jpegUpload('b.jpg'), $member);
            $this->fail('Second Emerging photo should be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('photo', $e->errors());
        }

        $this->assertSame(1, $service->profilePhotoCount($profile));
    }

    public function test_accomplished_and_distinguished_photo_limits(): void
    {
        $service = app(ProfileMediaService::class);

        [, , $accomplished] = $this->makeReadyApplication('accomplished');
        $ownerA = User::query()->findOrFail($accomplished->user_id);
        for ($i = 0; $i < 3; $i++) {
            $service->uploadProfilePhoto($accomplished, $this->jpegUpload("a{$i}.jpg"), $ownerA);
        }
        try {
            $service->uploadProfilePhoto($accomplished, $this->jpegUpload('a3.jpg'), $ownerA);
            $this->fail('Fourth Accomplished photo should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame(3, $service->profilePhotoCount($accomplished));

        [, , $distinguished] = $this->makeReadyApplication('distinguished');
        $ownerD = User::query()->findOrFail($distinguished->user_id);
        for ($i = 0; $i < 5; $i++) {
            $service->uploadProfilePhoto($distinguished, $this->jpegUpload("d{$i}.jpg"), $ownerD);
        }
        try {
            $service->uploadProfilePhoto($distinguished, $this->jpegUpload('d5.jpg'), $ownerD);
            $this->fail('Sixth Distinguished photo should be rejected.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        $this->assertSame(5, $service->profilePhotoCount($distinguished));
    }

    public function test_member_cannot_manage_another_members_media(): void
    {
        [$memberA, $applicationA, $profileA] = $this->makeReadyApplication('accomplished');
        [$memberB] = $this->makeReadyApplication('accomplished');
        $service = app(ProfileMediaService::class);
        $photo = $service->uploadProfilePhoto($profileA, $this->jpegUpload('a.jpg'), $memberA);

        $this->actingAs($memberB)
            ->post(route('applications.media.store', $applicationA), [
                'photo' => $this->jpegUpload('x.jpg'),
            ])
            ->assertForbidden();

        try {
            $service->setPrimary($profileA, $photo, $memberB);
            $this->fail('Cross-user primary change should fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        try {
            $service->deletePhoto($profileA, $photo, $memberB);
            $this->fail('Cross-user delete should fail.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_primary_photo_is_unique_and_switchable(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('accomplished');
        $service = app(ProfileMediaService::class);
        $one = $service->uploadProfilePhoto($profile, $this->jpegUpload('1.jpg'), $member);
        $two = $service->uploadProfilePhoto($profile, $this->jpegUpload('2.jpg'), $member);

        $this->assertTrue($one->fresh()->is_primary);
        $this->assertFalse($two->fresh()->is_primary);

        $service->setPrimary($profile, $two, $member);
        $this->assertFalse($one->fresh()->is_primary);
        $this->assertTrue($two->fresh()->is_primary);
        $this->assertSame(1, MediaItem::query()
            ->where('mediable_id', $profile->id)
            ->where('is_primary', true)
            ->count());
    }

    public function test_upload_security_rejects_dangerous_and_unsupported_files(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('accomplished');
        $service = app(ProfileMediaService::class);

        foreach ([
            UploadedFile::fake()->create('shell.php', 20, 'application/x-php'),
            UploadedFile::fake()->create('note.html', 20, 'text/html'),
            UploadedFile::fake()->create('clip.mp4', 100, 'video/mp4'),
            UploadedFile::fake()->create('icon.svg', 20, 'image/svg+xml'),
            UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
        ] as $bad) {
            try {
                $service->uploadProfilePhoto($profile, $bad, $member);
                $this->fail('Dangerous upload should be rejected: '.$bad->getClientOriginalName());
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, $service->profilePhotoCount($profile));
    }

    public function test_publication_gate_and_source_material_isolation(): void
    {
        [$member, $application, $profile] = $this->makeReadyApplication('accomplished');
        $service = app(ProfileMediaService::class);
        $editor = User::factory()->editor()->create();
        $photo = $service->uploadProfilePhoto(
            $profile,
            $this->jpegUpload('pub.jpg'),
            $member,
        );

        $this->get(route('profiles.public.photo', [$profile, $photo]))->assertNotFound();

        $admin = User::factory()->admin()->create();
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
        $profile = $profile->fresh();

        // Still pending — must not be public even after profile publication.
        $this->get(route('profiles.public.photo', [$profile, $photo]))->assertNotFound();
        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertDontSee(route('profiles.public.photo', [$profile, $photo], false), false);

        $approved = $service->approve($photo, $editor);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $approved->review_status);
        $this->assertSame(MediaItem::PRIVACY_PUBLIC, $approved->privacy);

        $this->get(route('profiles.public.photo', [$profile, $approved]))->assertOk();
        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertDontSee('generation_run', false)
            ->assertDontSee('staff_action', false);

        Storage::disk('private_uploads')->put('source-materials/applications/'.$application->id.'/secret.pdf', 'private');
        $source = SourceMaterial::query()->create([
            'application_id' => $application->id,
            'user_id' => $member->id,
            'material_type' => 'other',
            'storage_disk' => 'private_uploads',
            'storage_path' => 'source-materials/applications/'.$application->id.'/secret.pdf',
            'original_filename' => 'secret.pdf',
            'mime_type' => 'application/pdf',
            'file_bytes' => 7,
            'uploaded_at' => now(),
        ]);

        $this->get('/p/'.$profile->id.'/photo/'.$source->id)->assertNotFound();
        $this->get(route('profiles.public.photo', [$profile, $approved]))
            ->assertOk()
            ->assertHeaderMissing('X-Storage-Key');
    }

    public function test_pending_primary_does_not_replace_live_approved_photo(): void
    {
        [$member, $application, $profile] = $this->makeReadyApplication('accomplished');
        $service = app(ProfileMediaService::class);
        $editor = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $photoA = $service->uploadProfilePhoto($profile, $this->jpegUpload('a.jpg'), $member, 'A');
        $service->approve($photoA, $editor);
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
        $profile = $profile->fresh();

        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertSee(route('profiles.public.photo', [$profile, $photoA], false), false);

        $photoB = $service->uploadProfilePhoto($profile, $this->jpegUpload('b.jpg'), $member, 'B', true);
        $this->assertTrue($photoB->fresh()->is_primary);
        $this->assertFalse($photoA->fresh()->is_primary);
        $this->assertSame(MediaItem::REVIEW_PENDING, $photoB->review_status);

        // Public must still show approved A, not pending B.
        $this->get(route('profiles.public.photo', [$profile, $photoB]))->assertNotFound();
        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
        $this->assertStringContainsString('/p/'.$profile->id.'/photo/'.$photoA->id, $html);
        $this->assertStringNotContainsString('/p/'.$profile->id.'/photo/'.$photoB->id, $html);

        $service->approve($photoB->fresh(), $editor);
        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
        $this->assertStringContainsString('/p/'.$profile->id.'/photo/'.$photoB->id, $html);
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.profile_photo_approved')->exists());
    }

    public function test_support_cannot_approve_photographs(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('accomplished');
        $photo = app(ProfileMediaService::class)->uploadProfilePhoto($profile, $this->jpegUpload('s.jpg'), $member);
        $support = User::factory()->support()->create();

        try {
            app(ProfileMediaService::class)->approve($photo, $support);
            $this->fail('Support must not approve photographs.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_uploaded_images_are_resized_and_original_not_stored(): void
    {
        config(['jannayaks.media.optimization.max_edge_px' => 400]);
        [$member, , $profile] = $this->makeReadyApplication('emerging');
        $item = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('huge.jpg', 2400, 1800),
            $member,
        );

        $this->assertSame('image/jpeg', $item->mime_type);
        $this->assertLessThanOrEqual(400, max((int) $item->width, (int) $item->height));
        $this->assertTrue(Storage::disk('public')->exists($item->storage_path_key));
        $this->assertStringEndsWith('.jpg', $item->storage_path_key);
    }

    public function test_external_url_validation_rules(): void
    {
        $validator = app(ExternalUrlValidator::class);
        $ok = $validator->validateHttpsUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertStringStartsWith('https://', $ok);

        foreach ([
            'http://example.com/video',
            'javascript:alert(1)',
            'data:text/html;base64,xxxx',
            'file:///etc/passwd',
            '<iframe src="https://evil.test"></iframe>',
            'https://127.0.0.1/video',
        ] as $bad) {
            try {
                $validator->validateHttpsUrl($bad);
                $this->fail('Should reject: '.$bad);
            } catch (\InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_emerging_cannot_submit_video_links(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('emerging');
        try {
            app(ExternalVideoLinkService::class)->submit(
                $profile,
                $member,
                'https://www.youtube.com/watch?v=abc123',
            );
            $this->fail('Emerging should not get video links.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('url', $e->errors());
        }
    }

    public function test_post_publication_video_change_requires_approval(): void
    {
        [$member, $application, $profile] = $this->makeReadyApplication('accomplished');
        $videos = app(ExternalVideoLinkService::class);
        $editor = User::factory()->editor()->create();

        $pending = $videos->submit($profile, $member, 'https://www.youtube.com/watch?v=original1', 'Original');
        $this->assertFalse($pending->is_publicly_active);
        $active = $videos->approve($pending, $editor);
        $this->assertTrue($active->is_publicly_active);

        $admin = User::factory()->admin()->create();
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, false);
        $profile = $profile->fresh();

        $change = $videos->submit($profile, $member, 'https://www.youtube.com/watch?v=changed2', 'Changed');
        $this->assertSame(ProfileExternalLink::STATUS_PENDING_REVIEW, $change->status);
        $this->assertFalse($change->is_publicly_active);
        $this->assertSame($active->id, $change->replaces_link_id);
        $this->assertTrue($active->fresh()->is_publicly_active);

        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertSee('https://www.youtube.com/watch?v=original1', false)
            ->assertDontSee('https://www.youtube.com/watch?v=changed2', false);

        $rejected = $videos->reject($change, $editor, 'Not suitable');
        $this->assertSame(ProfileExternalLink::STATUS_REJECTED, $rejected->status);
        $this->assertTrue($active->fresh()->is_publicly_active);

        $change2 = $videos->submit($profile, $member, 'https://vimeo.com/123456789', 'Approved change');
        $approved = $videos->approve($change2, $editor);
        $this->assertTrue($approved->is_publicly_active);
        $this->assertFalse($active->fresh()->is_publicly_active);
        $this->assertSame(ProfileExternalLink::STATUS_REPLACED, $active->fresh()->status);

        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertSee('https://vimeo.com/123456789', false)
            ->assertDontSee('https://www.youtube.com/watch?v=original1', false);

        $this->assertTrue(StaffActionLog::query()->where('action', 'media.external_video_change_requested')->exists());
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.external_video_approved')->exists());
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.external_video_rejected')->exists());
    }

    public function test_support_cannot_approve_video_links(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('accomplished');
        $pending = app(ExternalVideoLinkService::class)->submit(
            $profile,
            $member,
            'https://www.youtube.com/watch?v=supportblock',
        );
        $support = User::factory()->support()->create();

        try {
            app(ExternalVideoLinkService::class)->approve($pending, $support);
            $this->fail('Support must not approve video links.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_member_media_page_and_http_upload_flow(): void
    {
        [$member, $application] = $this->makeReadyApplication('accomplished');

        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('Photographs', false);

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => $this->jpegUpload('member.jpg'),
                'alt_text' => 'Portrait',
                'make_primary' => '1',
            ])
            ->assertRedirect(route('applications.media', $application));

        $this->assertSame(1, MediaItem::query()->where('mediable_id', $application->profile_id)->count());
        $this->assertTrue(StaffActionLog::query()->where('action', 'media.profile_photo_uploaded')->exists());
    }

    public function test_storage_object_keys_are_server_generated_without_pii(): void
    {
        [$member, , $profile] = $this->makeReadyApplication('emerging');
        $item = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            $this->jpegUpload('My Passport Scan.jpg'),
            $member,
        );

        $this->assertStringStartsWith('profile-media/profiles/'.$profile->id.'/', $item->storage_path_key);
        $this->assertStringNotContainsString('Passport', $item->storage_path_key);
        $this->assertStringNotContainsString('@', $item->storage_path_key);
        $this->assertSame('public', $item->disk);
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function makeReadyApplication(string $tier): array
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Media Leader '.uniqid(),
            'display_name' => 'Media Leader',
            'profession' => 'Organiser',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        return [$member, $application->fresh(), $profile->fresh()];
    }

    private function jpegUpload(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 400, 500);
    }
}
