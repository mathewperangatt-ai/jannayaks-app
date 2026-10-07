<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use LogicException;
use Tests\TestCase;

/**
 * Profile Reference Number (e.g. JN-7K4P2): assigned at profile creation,
 * unique, immutable, independent of the public slug, never used for routing.
 */
class ProfileReferenceNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.url', 'https://www.jannayaks.in');
        URL::forceRootUrl('https://www.jannayaks.in');
        URL::forceScheme('https');
    }

    public function test_reference_number_is_assigned_automatically_at_profile_creation(): void
    {
        $member = User::factory()->create();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'full_name' => 'Arun Kumar Nair',
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);

        $profile = app(ProfileUrlService::class)->ensureDraftProfileForApplication($application->fresh());

        $this->assertMatchesRegularExpression('/^JN-[A-HJ-KM-NP-Z2-9]{5}$/', (string) $profile->reference_code);
        $this->assertNotSame((string) $profile->slug, (string) $profile->reference_code);
    }

    public function test_reference_numbers_are_unique_across_profiles(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $member = User::factory()->create();
            Profile::query()->create([
                'user_id' => $member->id,
                'status' => 'under_editorial_review',
                'full_name' => 'Member '.$i,
                'profession' => '',
                'display_phone_consent' => false,
                'display_email_consent' => false,
            ]);
        }

        $codes = Profile::query()->pluck('reference_code');
        $this->assertSame(20, $codes->filter()->count());
        $this->assertSame(20, $codes->unique()->count());
    }

    public function test_reference_number_is_immutable_once_assigned(): void
    {
        $profile = $this->createProfile();

        $original = (string) $profile->reference_code;

        $this->expectException(LogicException::class);
        $profile->reference_code = 'JN-ZZZZZ';
        $profile->save();
    }

    public function test_reference_number_cannot_be_changed_via_mass_assignment(): void
    {
        $profile = $this->createProfile();
        $original = (string) $profile->reference_code;

        $profile->fill(['reference_code' => 'JN-AAAAA']);
        $profile->save();

        $this->assertSame($original, (string) $profile->fresh()->reference_code);
        $this->assertNotSame('JN-AAAAA', (string) $profile->fresh()->reference_code);
    }

    public function test_reference_number_is_never_used_for_routing(): void
    {
        $profile = $this->createProfile();

        // The reference number is not a slug: the public catch-all must not resolve it.
        $this->get('/'.$profile->reference_code)->assertNotFound();
    }

    public function test_customer_dashboard_shows_reference_and_public_action_only_when_available(): void
    {
        $member = User::factory()->create();
        $profile = $this->createProfile($member);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        // Profile exists but is not public: reference shows, public action must not.
        $this->actingAs($member)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee((string) $profile->reference_code)
            ->assertDontSee('View public profile');

        // Published with an approved primary photograph: reference, photograph
        // and the public-profile action appear together.
        $profile->forceFill([
            'slug' => 'arun.kumar.nair',
            'status' => 'published',
            'published_at' => now(),
            'slug_generated_at' => now(),
        ])->save();

        MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
            'storage_path_key' => 'profiles/arun.jpg',
            'privacy' => MediaItem::PRIVACY_PUBLIC,
            'review_status' => MediaItem::REVIEW_APPROVED,
            'is_primary' => true,
            'mime_type' => 'image/jpeg',
        ]);

        $this->actingAs($member)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee((string) $profile->reference_code)
            ->assertSee('View public profile')
            ->assertSee('dash-photo');
    }

    public function test_public_profile_displays_the_reference_number_unobtrusively(): void
    {
        $profile = $this->createProfile();
        $profile->forceFill([
            'slug' => 'arun.kumar.nair',
            'status' => 'published',
            'published_at' => now(),
            'slug_generated_at' => now(),
        ])->save();

        $this->get('/'.$profile->slug)
            ->assertOk()
            ->assertSee('Profile reference')
            ->assertSee((string) $profile->reference_code);
    }

    private function createProfile(?User $member = null): Profile
    {
        $member ??= User::factory()->create();

        return Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Arun Kumar Nair',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
    }
}
