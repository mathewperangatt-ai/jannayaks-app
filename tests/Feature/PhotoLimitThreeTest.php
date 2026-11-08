<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\ProfileMediaRelationManager;
use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileMediaService;
use App\Support\ProfileEntitlements;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Approved specification: a maximum of three photographs per profile,
 * enforced on customer upload, on admin approval, and in customer wording.
 */
class PhotoLimitThreeTest extends TestCase
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

    public function test_limit_is_three_and_config_agrees_with_enforcement(): void
    {
        [, $application] = $this->application();

        $this->assertSame(3, ProfileEntitlements::CUSTOMER_VISIBLE_PHOTO_LIMIT);
        $this->assertSame(3, ProfileEntitlements::photoLimit());
        $this->assertSame(3, ProfileEntitlements::photoSlots($application));
        $this->assertSame(3, app(ProfileMediaService::class)->photoSlotLimitForApplication($application));
        $this->assertSame(3, (int) config('jannayaks.tier_pricing.standard_profile.photo_slots'));
        foreach (['emerging', 'accomplished', 'distinguished'] as $tier) {
            $this->assertSame(3, (int) config("jannayaks.tier_pricing.packages.{$tier}.photo_slots"), $tier);
            $this->assertStringContainsString('capped at three', (string) config("jannayaks.tier_pricing.packages.{$tier}.description"));
        }

        config(['jannayaks.tier_pricing.standard_profile.photo_slots' => 2]);
        $this->assertSame(2, ProfileEntitlements::photoLimit(), 'Configuration may lower the limit.');
        config(['jannayaks.tier_pricing.standard_profile.photo_slots' => 6]);
        $this->assertSame(3, ProfileEntitlements::photoLimit(), 'Configuration can never raise it above three.');
    }

    public function test_customer_uploads_three_photographs_and_the_fourth_is_rejected(): void
    {
        [$member, $application] = $this->application();

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('0 of 3 photographs used', false);

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($member)
                ->post(route('applications.media.store', $application), [
                    'photo' => UploadedFile::fake()->image("portrait-{$i}.jpg", 400, 500),
                ])
                ->assertRedirect(route('applications.media', $application))
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($member)
            ->from(route('applications.media', $application))
            ->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('fourth.jpg', 400, 500),
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasErrors(['photo' => 'Each profile may include a maximum of 3 photographs.']);

        $this->assertSame(3, $this->photoCount($application));

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('3 of 3 photographs used', false)
            ->assertSee('You have reached the maximum of 3 photographs for this profile.', false)
            ->assertDontSee('four', false)
            ->assertDontSee('of 4 photographs', false);
    }

    public function test_admin_approves_three_photographs_and_a_fourth_approval_is_refused(): void
    {
        [$member, $application] = $this->application();
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $service = app(ProfileMediaService::class);

        $photos = [];
        for ($i = 1; $i <= 3; $i++) {
            $photos[] = $service->uploadProfilePhoto($application->profile, UploadedFile::fake()->image("p{$i}.jpg", 400, 500), $member);
        }
        foreach ($photos as $photo) {
            $this->assertSame(MediaItem::REVIEW_APPROVED, $service->approve($photo, $admin)->review_status);
        }

        // A pending fourth photograph left over from the previous four-photo limit.
        $legacyPending = $photos[0]->replicate();
        $legacyPending->forceFill([
            'is_primary' => false,
            'review_status' => MediaItem::REVIEW_PENDING,
            'privacy' => MediaItem::PRIVACY_PRIVATE,
            'display_order' => 4,
            'storage_path_key' => 'profile-media/profiles/'.$application->profile_id.'/legacy-fourth.jpg',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ])->save();

        try {
            $service->approve($legacyPending, $admin);
            $this->fail('A fourth approved photograph must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'This profile already has the maximum of 3 approved photographs. Reject or remove one before approving another.',
                $e->errors()['media'][0],
            );
        }
        $this->assertSame(MediaItem::REVIEW_PENDING, $legacyPending->fresh()->review_status);

        Livewire::actingAs($admin)
            ->test(ProfileMediaRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->callAction(TestAction::make('approve')->table($legacyPending), ['review_note' => null])
            ->assertNotified('Cannot approve photograph');

        $this->assertSame(MediaItem::REVIEW_PENDING, $legacyPending->fresh()->review_status);
        $this->assertSame(3, MediaItem::query()
            ->where('mediable_id', $application->profile_id)
            ->where('review_status', MediaItem::REVIEW_APPROVED)
            ->count());

        $service->reject($photos[2], $admin, 'Replaced.');
        $this->assertSame(MediaItem::REVIEW_APPROVED, $service->approve($legacyPending->fresh(), $admin)->review_status);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function application(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Photo Limit Subject',
            'display_name' => 'Photo Limit Subject',
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

    private function photoCount(Application $application): int
    {
        return MediaItem::query()
            ->where('mediable_id', $application->profile_id)
            ->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)
            ->count();
    }
}
