<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HostingDestinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_application_defaults_to_jannayaks_on_application_only(): void
    {
        $prepared = $this->prepareUnpublishedLiving();

        $this->assertSame(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            $prepared['application']->fresh()->hosting_destination,
        );
        $this->assertFalse(
            array_key_exists('hosting_destination', $prepared['profile']->getAttributes()),
        );

        $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('name="hosting_destination"', false)
            ->assertSee('value="jannayaks"', false)
            ->assertSee('value="iconsherald"', false)
            ->assertSee('jannayaks.in/your.name', false)
            ->assertSee('iconsherald.com/your.name', false);
    }

    public function test_applicant_can_select_and_save_jannayaks_or_iconsherald(): void
    {
        $prepared = $this->prepareUnpublishedLiving();

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.update', $prepared['application']), [
            'hosting_destination' => Application::HOSTING_DESTINATION_ICONSHERALD,
            'slug' => 'arun.kumar',
        ])->assertRedirect(route('applications.profile-url', $prepared['application']));

        $this->assertSame(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            $prepared['application']->fresh()->hosting_destination,
        );
        $this->assertSame('arun.kumar', $prepared['profile']->fresh()->slug);

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.hosting', $prepared['application']), [
            'hosting_destination' => Application::HOSTING_DESTINATION_JANNAYAKS,
        ])->assertRedirect(route('applications.profile-url', $prepared['application']));

        $this->assertSame(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            $prepared['application']->fresh()->hosting_destination,
        );
        $this->assertSame('arun.kumar', $prepared['profile']->fresh()->slug);
    }

    public function test_hosting_destination_survives_logout_and_new_session(): void
    {
        $prepared = $this->prepareUnpublishedLiving();

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.update', $prepared['application']), [
            'hosting_destination' => Application::HOSTING_DESTINATION_ICONSHERALD,
            'slug' => 'arun.kumar',
        ])->assertRedirect();

        auth()->logout();
        $this->flushSession();

        $this->actingAs($prepared['user'])
            ->get(route('applications.profile-url', $prepared['application']))
            ->assertOk()
            ->assertSee('value="iconsherald"', false);

        $this->assertDatabaseHas('applications', [
            'id' => $prepared['application']->id,
            'hosting_destination' => Application::HOSTING_DESTINATION_ICONSHERALD,
        ]);
    }

    public function test_invalid_hosting_destination_is_rejected(): void
    {
        $prepared = $this->prepareUnpublishedLiving();

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.update', $prepared['application']), [
            'hosting_destination' => 'elsewhere',
            'slug' => 'arun.kumar',
        ])->assertSessionHasErrors('hosting_destination');

        $this->actingAs($prepared['user'])->post(route('applications.profile-url.hosting', $prepared['application']), [
            'hosting_destination' => 'wordpress',
        ])->assertSessionHasErrors('hosting_destination');

        $this->assertSame(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            $prepared['application']->fresh()->hosting_destination,
        );
        $this->assertNull($prepared['profile']->fresh()->slug);
    }

    public function test_member_cannot_change_hosting_destination_after_publication(): void
    {
        [$member, $application, $profile] = $this->publishLiving();

        $this->assertSame(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            $application->fresh()->hosting_destination,
        );

        $this->actingAs($member)->post(route('applications.profile-url.hosting', $application), [
            'hosting_destination' => Application::HOSTING_DESTINATION_ICONSHERALD,
        ])->assertSessionHasErrors('hosting_destination');

        $this->assertSame(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            $application->fresh()->hosting_destination,
        );

        $this->actingAs($member)
            ->get(route('applications.profile-url', $application))
            ->assertOk()
            ->assertSee('Hosting destination is locked after publication.', false)
            ->assertSee('jannayaks.in/'.$profile->slug, false)
            ->assertDontSee('Save hosting destination', false)
            ->assertDontSee('value="iconsherald"', false);
    }

    public function test_staff_service_flag_can_update_after_publication_without_member_route(): void
    {
        [$member, $application] = $this->publishLiving();

        app(ProfileUrlService::class)->setHostingDestination(
            $application->fresh(),
            Application::HOSTING_DESTINATION_ICONSHERALD,
            allowAfterPublication: true,
        );

        $this->assertSame(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            $application->fresh()->hosting_destination,
        );

        // Member route remains blocked.
        $this->actingAs($member)->post(route('applications.profile-url.hosting', $application), [
            'hosting_destination' => Application::HOSTING_DESTINATION_JANNAYAKS,
        ])->assertSessionHasErrors('hosting_destination');
    }

    public function test_admin_application_view_shows_hosting_destination(): void
    {
        $prepared = $this->prepareUnpublishedLiving();
        app(ProfileUrlService::class)->setHostingDestination(
            $prepared['application'],
            Application::HOSTING_DESTINATION_ICONSHERALD,
        );

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\Applications\Pages\ViewApplication::class, [
                'record' => $prepared['application']->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Hosting destination')
            ->assertSee('IconsHerald (iconsherald.com)');
    }

    /**
     * @return array{user: User, application: Application, profile: Profile}
     */
    private function prepareUnpublishedLiving(string $tier = 'accomplished', string $fullName = 'Arun Kumar Nair'): array
    {
        $member = User::factory()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'customer_approved_at' => null,
        ]);

        return [
            'user' => $member,
            'application' => $application->fresh(),
            'profile' => $profile->fresh(),
        ];
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function publishLiving(string $tier = 'accomplished', string $fullName = 'Arun Kumar Nair'): array
    {
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $published = app(ApplicationWorkflowService::class)->publish(
            $application->fresh(),
            $admin,
            false,
            'Authorised offline publication for this test.',
        );
        $profile = Profile::query()->findOrFail($published->profile_id);

        return [$member, $published->fresh(), $profile->fresh()];
    }
}
