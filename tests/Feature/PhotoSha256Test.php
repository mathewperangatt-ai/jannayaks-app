<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\InMemoriamMediaService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoSha256Test extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_upload_stores_sha256_of_exact_stored_bytes(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApplication();
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $application->profile,
            UploadedFile::fake()->image('hash-me.jpg', 400, 500),
            $member,
        );

        $this->assertNotNull($photo->photo_sha256);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $photo->photo_sha256);

        // The hash must represent the EXACT bytes stored (and later served).
        $storedBytes = Storage::disk('public')->get($photo->storage_path_key);
        $this->assertSame(hash('sha256', $storedBytes), (string) $photo->photo_sha256);
    }

    public function test_in_memoriam_photo_upload_stores_sha256_of_exact_stored_bytes(): void
    {
        Storage::fake('public');
        $editor = User::factory()->editor()->create();
        $memorial = \App\Models\InMemoriamProfile::factory()->paid()->create([
            'slug' => 'hash-memorial',
            'status' => \App\Models\InMemoriamProfile::STATUS_UNDER_EDITORIAL_REVIEW,
        ]);
        $service = app(InMemoriamMediaService::class);

        $photo = $service->uploadPhotograph(
            $memorial,
            UploadedFile::fake()->image('memorial.jpg', 400, 500),
            $editor,
        );

        $this->assertNotNull($photo->photo_sha256);
        $storedBytes = Storage::disk('public')->get($photo->storage_path_key);
        $this->assertSame(hash('sha256', $storedBytes), (string) $photo->photo_sha256);
    }

    public function test_backfill_hashes_existing_photo_with_null_hash(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApplication();
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $application->profile,
            UploadedFile::fake()->image('legacy.jpg', 400, 500),
            $member,
        );
        $photo->forceFill(['photo_sha256' => null])->save();
        $this->assertNull($photo->fresh()->photo_sha256);

        $exit = Artisan::call('jannayaks:backfill-photo-hashes');

        $this->assertSame(0, $exit);
        $storedBytes = Storage::disk('public')->get($photo->storage_path_key);
        $this->assertSame(hash('sha256', $storedBytes), (string) $photo->fresh()->photo_sha256);
    }

    public function test_backfill_is_idempotent(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApplication();
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $application->profile,
            UploadedFile::fake()->image('twice.jpg', 400, 500),
            $member,
        );
        $photo->forceFill(['photo_sha256' => null])->save();

        Artisan::call('jannayaks:backfill-photo-hashes');
        $firstHash = $photo->fresh()->photo_sha256;
        $this->assertNotNull($firstHash);

        Artisan::call('jannayaks:backfill-photo-hashes');

        $this->assertSame($firstHash, (string) $photo->fresh()->photo_sha256);
        $this->assertSame(
            1,
            MediaItem::query()->whereKey($photo->id)->whereNotNull('photo_sha256')->count()
        );
    }

    public function test_backfill_does_not_invent_hash_for_missing_object(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApplication();
        $service = app(ProfileMediaService::class);

        $photo = $service->uploadProfilePhoto(
            $application->profile,
            UploadedFile::fake()->image('vanished.jpg', 400, 500),
            $member,
        );
        $photo->forceFill(['photo_sha256' => null])->save();
        Storage::disk('public')->delete($photo->storage_path_key);

        $exit = Artisan::call('jannayaks:backfill-photo-hashes');

        $this->assertSame(0, $exit);
        $this->assertNull($photo->fresh()->photo_sha256, 'A missing object must never receive a hash.');
    }

    public function test_backfill_ignores_non_photo_media(): void
    {
        Storage::fake('public');
        [$member, $application] = $this->seedApplication();

        $document = MediaItem::query()->create([
            'mediable_type' => $application->profile->getMorphClass(),
            'mediable_id' => $application->profile->id,
            'media_type' => MediaItem::TYPE_DOCUMENT,
            'storage_path_key' => 'documents/not-a-photo.bin',
            'disk' => 'public',
            'privacy' => MediaItem::PRIVACY_PRIVATE,
            'review_status' => MediaItem::REVIEW_PENDING,
            'uploaded_by_id' => $member->id,
        ]);

        Artisan::call('jannayaks:backfill-photo-hashes');

        $this->assertNull($document->fresh()->photo_sha256, 'Non-photo media must not be hashed.');
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function seedApplication(): array
    {
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
            'status' => 'under_editorial_review',
            'full_name' => 'Hash Leader',
            'display_name' => 'Hash Leader',
            'profession' => 'Leader',
            'slug' => 'hash-leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        return [$member, $application->fresh()];
    }
}
