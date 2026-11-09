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
 * Existing profile-URL availability and photograph upload capabilities are
 * reachable and explained from the customer journey.
 */
class CustomerJourneyDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_profile_url_page_wires_suggestions_to_the_availability_check(): void
    {
        [$member, $application] = $this->application('Meera Nair');

        $html = $this->actingAs($member)->get(route('applications.profile-url', $application))
            ->assertOk()
            ->assertSee('subject to availability and formatting requirements', false)
            ->assertSee('Once your profile is published, its URL cannot be changed.', false)
            ->getContent();

        $this->assertStringContainsString('class="btn ghost js-slug-choice" data-slug="meera.nair"', $html);
        $this->assertStringContainsString('id="slug-availability"', $html);
        $this->assertStringContainsString(
            json_encode(route('applications.profile-url.availability', $application)),
            $html,
            'The page script calls the existing availability endpoint.'
        );

        $this->actingAs($member)
            ->getJson(route('applications.profile-url.availability', [$application, 'slug' => 'meera.nair']))
            ->assertOk()
            ->assertJson(['available' => true]);
        $this->actingAs($member)
            ->getJson(route('applications.profile-url.availability', [$application, 'slug' => 'admin']))
            ->assertOk()
            ->assertJson(['available' => false]);
    }

    public function test_dashboard_offers_photographs_before_a_profile_exists_and_upload_creates_the_draft(): void
    {
        [$member, $application] = $this->application('Photo Customer');
        $this->assertNull($application->profile_id);

        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Photographs &amp; video', false)
            ->assertSee('Upload up to 3 photographs for your profile', false)
            ->assertSee(route('applications.media', $application), false);

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('You may upload up to 3 photographs for your profile, separately from the Online Interview.', false)
            ->assertSee('any enhanced version stays private unless Jannayaks approves it', false);

        $application->refresh();
        $this->assertNotNull($application->profile_id);
        $profile = Profile::query()->findOrFail($application->profile_id);
        $this->assertNull($profile->slug, 'Opening photographs never assigns a profile URL.');

        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 500),
        ])->assertRedirect(route('applications.media', $application));

        $photo = MediaItem::query()->where('mediable_id', $profile->id)->sole();
        $this->assertSame(MediaItem::REVIEW_PENDING, $photo->review_status);
        $this->assertSame(MediaItem::PRIVACY_PRIVATE, $photo->privacy);
    }

    public function test_locked_application_does_not_offer_photographs(): void
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertDontSee('Upload up to 3 photographs', false);
        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertRedirect(route('applications.payment', $application));
        $this->assertNull($application->fresh()->profile_id);
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function application(string $name): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->paid()->create([
            'full_name' => $name,
            'package_tier' => 'distinguished',
            'source_method' => 'online_interview',
        ]);

        return [$member, $application->fresh()];
    }
}
