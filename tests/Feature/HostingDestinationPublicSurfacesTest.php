<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\ProfileContactMessage;
use App\Models\ProfileReaction;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileQrCodeService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HostingDestinationPublicSurfacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_lists_both_destinations_with_correct_absolute_links(): void
    {
        [$jnName, $jnSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Arun Kumar Nair',
            'arun.kumar.nair',
        );
        [$ihName, $ihSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Meera Lakshmi Pillai',
            'meera.lakshmi.pillai',
        );

        $response = $this->get(route('gallery.index'))->assertOk();
        $html = $response->getContent();

        $response->assertSee($jnName, false);
        $response->assertSee($ihName, false);
        $response->assertDontSee('noindex', false);

        $this->assertStringContainsString(
            'href="'.url('/'.$jnSlug).'"',
            $html,
        );
        $this->assertStringContainsString(
            'href="'.ProfileUrlService::ICONSHERALD_PUBLIC_ORIGIN.'/'.$ihSlug.'"',
            $html,
        );
        $this->assertStringNotContainsString(
            'href="'.url('/'.$ihSlug).'"',
            $html,
        );
    }

    public function test_jannayaks_hosted_profile_page_still_renders(): void
    {
        [$name, $slug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Suresh Babu Menon',
            'suresh.babu.menon',
        );

        $this->get('/'.$slug)
            ->assertOk()
            ->assertSee($name, false);
        $this->get('/p/'.$slug)
            ->assertRedirect('/'.$slug);
    }

    public function test_iconsherald_hosted_profile_returns_404_on_jannayaks_without_redirect(): void
    {
        [$name, $slug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Anitha Rajan Thomas',
            'anitha.rajan.thomas',
        );

        $this->get('/'.$slug)
            ->assertNotFound()
            ->assertDontSee($name, false);
        $this->get('/p/'.$slug)
            ->assertNotFound();
    }

    public function test_qr_payload_uses_selected_hosting_destination(): void
    {
        [, $jnSlug, $jnProfile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Ravi Krishnan Nair',
            'ravi.krishnan.nair',
            returnProfile: true,
        );
        [, $ihSlug, $ihProfile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Priya Nair Das',
            'priya.nair.das',
            returnProfile: true,
        );

        $qr = app(ProfileQrCodeService::class);

        $this->assertSame(
            url('/'.$jnSlug),
            $qr->encodedPayload($jnProfile->fresh()),
        );
        $this->assertSame(
            ProfileUrlService::ICONSHERALD_PUBLIC_ORIGIN.'/'.$ihSlug,
            $qr->encodedPayload($ihProfile->fresh()),
        );
    }

    public function test_sitemap_lists_only_jannayaks_hosted_profile_urls(): void
    {
        [, $jnSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Vijay Menon',
            'vijay.menon',
        );
        [, $ihSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Lakshmi Devi',
            'lakshmi.devi',
        );

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(url('/'.$jnSlug), $xml);
        $this->assertStringContainsString(url('/gallery'), $xml);
        $this->assertStringNotContainsString(
            ProfileUrlService::ICONSHERALD_PUBLIC_ORIGIN.'/'.$ihSlug,
            $xml,
        );
        $this->assertStringNotContainsString(url('/'.$ihSlug), $xml);
    }

    public function test_iconsherald_historical_slug_returns_404_without_redirect(): void
    {
        [, $oldSlug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Anitha Rajan Thomas',
            'anitha.rajan.thomas',
            returnProfile: true,
        );

        $admin = User::factory()->admin()->create();
        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile->fresh(),
            $admin,
            'anitha.r.thomas',
            'accomplished',
        );

        $this->assertTrue(
            SlugRedirect::query()
                ->where('old_slug', $oldSlug)
                ->where('redirectable_id', $profile->id)
                ->exists()
        );

        $canonical = $this->get('/'.$oldSlug);
        $canonical->assertNotFound();
        $this->assertFalse($canonical->isRedirect());

        $legacy = $this->get('/p/'.$oldSlug);
        $legacy->assertNotFound();
        $this->assertFalse($legacy->isRedirect());
    }

    public function test_jannayaks_hosted_engagement_contact_and_react_still_work(): void
    {
        Mail::fake();

        [, $slug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Suresh Babu Menon',
            'suresh.babu.menon',
            returnProfile: true,
        );

        $this->from('/'.$slug)
            ->post('/profiles/'.$slug.'/contact', [
                'visitor_name' => 'Arun K.',
                'visitor_mobile' => '+91 98765 43210',
                'message' => 'Would like to discuss a community programme invitation.',
            ])
            ->assertRedirect('/'.$slug.'#wellwishers');

        $this->assertDatabaseHas('profile_contact_messages', [
            'profile_id' => $profile->id,
            'visitor_name' => 'Arun K.',
        ]);

        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($visitor)
            ->from('/'.$slug)
            ->post('/profiles/'.$slug.'/react', ['reaction' => ProfileReaction::REACTION_LIKE])
            ->assertRedirect();

        $this->assertDatabaseHas('profile_reactions', [
            'profile_id' => $profile->id,
            'user_id' => $visitor->id,
            'reaction' => ProfileReaction::REACTION_LIKE,
        ]);
    }

    public function test_iconsherald_hosted_engagement_contact_and_react_return_404(): void
    {
        Mail::fake();

        [, $slug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Meera Lakshmi Pillai',
            'meera.lakshmi.pillai',
            returnProfile: true,
        );

        $this->from('/'.$slug)
            ->post('/profiles/'.$slug.'/contact', [
                'visitor_name' => 'Arun K.',
                'visitor_mobile' => '+91 98765 43210',
                'message' => 'Would like to discuss a community programme invitation.',
            ])
            ->assertNotFound();

        $this->assertSame(0, ProfileContactMessage::query()->where('profile_id', $profile->id)->count());
        Mail::assertNothingSent();

        $visitor = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($visitor)
            ->from('/'.$slug)
            ->post('/profiles/'.$slug.'/react', ['reaction' => ProfileReaction::REACTION_LIKE])
            ->assertNotFound();

        $this->assertSame(0, ProfileReaction::query()->where('profile_id', $profile->id)->count());
    }

    /**
     * @return array{0: string, 1: string}|array{0: string, 1: string, 2: Profile}
     */
    private function publishWithHosting(
        string $destination,
        string $fullName,
        string $slug,
        bool $returnProfile = false,
    ): array {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => 'Public servant',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'hosting_destination' => $destination,
        ]);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $fullName,
            'body' => 'Approved English biography.',
            'summary' => '',
            'ai_generated' => false,
        ]);

        $published = app(ApplicationWorkflowService::class)->publish(
            $application->fresh(),
            $admin,
            false,
            'Authorised offline publication for this test.',
        );

        $profile = Profile::query()->findOrFail($published->profile_id);
        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile,
            $admin,
            $slug,
            'accomplished',
        );
        $profile = $profile->fresh();

        $this->assertSame($destination, $published->fresh()->hosting_destination);
        $this->assertSame($slug, $profile->slug);

        if ($returnProfile) {
            return [$fullName, $slug, $profile];
        }

        return [$fullName, $slug];
    }
}
