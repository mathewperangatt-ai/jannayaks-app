<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2: customers can select up to three photographs together in one
 * upload operation. The three-photograph total limit (including previously
 * uploaded photographs), validation, optimisation, SHA-256 hashing, R2
 * storage path, privacy and staff approval controls are unchanged; partial
 * batch failures never store an unreported state.
 */
class MultiPhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_customer_selects_three_photographs_together_in_one_upload(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => [
                    UploadedFile::fake()->image('one.jpg', 400, 500),
                    UploadedFile::fake()->image('two.jpg', 400, 500),
                    UploadedFile::fake()->image('three.jpg', 400, 500),
                ],
                'alt_text' => 'Group upload portrait',
                'make_primary' => '1',
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasNoErrors();

        $photos = $this->photos($application);
        $this->assertSame(3, $photos->count(), 'All three photographs stored from the single operation.');

        $primary = $photos->firstWhere('is_primary', true);
        $this->assertNotNull($primary, 'The first photograph carries the primary designation.');
        $this->assertSame('Group upload portrait', $primary->alt_text, 'Shared alt text applies.');
        $this->assertSame(1, $photos->where('is_primary', true)->count(), 'Exactly one primary photograph.');

        foreach ($photos as $photo) {
            $this->assertSame('pending_review', $photo->review_status);
            $this->assertSame('private', $photo->privacy);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $photo->photo_sha256, 'SHA-256 of stored bytes recorded.');
            Storage::disk('public')->assertExists($photo->storage_path_key);
        }

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('3 of 3 photographs used', false);
    }

    public function test_batch_respects_photographs_already_uploaded(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->image('first.jpg', 400, 500),
        ])->assertSessionHasNoErrors();

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => [
                UploadedFile::fake()->image('second.jpg', 400, 500),
                UploadedFile::fake()->image('third.jpg', 400, 500),
            ],
        ])->assertRedirect(route('applications.media', $application))
          ->assertSessionHasNoErrors();

        $this->assertSame(3, $this->photos($application)->count());
    }

    public function test_oversized_batch_is_refused_before_anything_is_stored(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->image('existing.jpg', 400, 500),
        ])->assertSessionHasNoErrors();

        $this->actingAs($member)
            ->from(route('applications.media', $application))
            ->post(route('applications.media.store', $application), [
                'photo' => [
                    UploadedFile::fake()->image('a.jpg', 400, 500),
                    UploadedFile::fake()->image('b.jpg', 400, 500),
                    UploadedFile::fake()->image('c.jpg', 400, 500),
                ],
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasErrors(['photo' => 'You may upload 2 more photographs in this batch.']);

        $this->assertSame(1, $this->photos($application)->count(), 'Nothing from the oversized batch was stored.');
    }

    public function test_invalid_file_anywhere_in_the_batch_stores_nothing(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)
            ->from(route('applications.media', $application))
            ->post(route('applications.media.store', $application), [
                'photo' => [
                    UploadedFile::fake()->image('valid.jpg', 400, 500),
                    UploadedFile::fake()->create('evil.php', 100),
                ],
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasErrors();

        $this->assertSame(0, $this->photos($application)->count(), 'The whole batch is rejected when any file fails validation.');
    }

    public function test_mid_batch_rejection_reports_successes_and_the_failed_file(): void
    {
        [$member, $application] = $this->application();

        // The second file passes HTTP validation but is not a real image, so
        // the service-level image check rejects it mid-batch.
        $fakeImage = UploadedFile::fake()->image('good.jpg', 400, 500);
        $notAnImage = UploadedFile::fake()->create('looks-like.jpg', 20, 'image/jpeg');

        $response = $this->actingAs($member)
            ->from(route('applications.media', $application))
            ->post(route('applications.media.store', $application), [
                'photo' => [$fakeImage, $notAnImage],
            ]);

        $photos = $this->photos($application);
        $this->assertSame(1, $photos->count(), 'The first photograph stays stored (individually transactional).');
        $response->assertRedirect(route('applications.media', $application));
        $response->assertSessionHas('status', function ($value) {
            return str_contains((string) $value, 'looks-like.jpg') === false
                && str_contains((string) $value, 'good.jpg') === true;
        });
        $response->assertSessionHas('error', function ($value) {
            return str_contains((string) $value, 'looks-like.jpg was not uploaded');
        });
    }

    public function test_legacy_single_file_upload_still_works(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('single.jpg', 400, 500),
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasNoErrors();

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('1 of 3 photographs used', false);
    }

    public function test_media_page_offers_multi_select_within_the_limit(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('name="photo[]"', false)
            ->assertSee('multiple', false)
            ->assertSee('Select up to <b>3</b> photograph', false);

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => [
                UploadedFile::fake()->image('a.jpg', 400, 500),
                UploadedFile::fake()->image('b.jpg', 400, 500),
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('Select up to <b>1</b> photograph', false);
    }

    /** Same fixture as PhotoLimitThreeTest: paid, upload-unlocked application. */
    private function application(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Multi Upload Subject',
            'display_name' => 'Multi Upload Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        return [$member, $application->fresh()];
    }

    private function photos(Application $application)
    {
        return MediaItem::query()
            ->where('mediable_id', $application->profile_id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->orderBy('id')
            ->get();
    }
}
