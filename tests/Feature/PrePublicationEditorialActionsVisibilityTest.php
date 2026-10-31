<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\EditApplication;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Characterises shared pre-publication Filament header actions on Edit and View.
 */
class PrePublicationEditorialActionsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_editor_sees_generate_and_release_on_edit_and_view_for_eligible_application(): void
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $application = $this->prePublicationApplication();

        foreach ([EditApplication::class, ViewApplication::class] as $page) {
            Livewire::actingAs($editor)
                ->test($page, ['record' => $application->getRouteKey()])
                ->assertSuccessful()
                ->assertActionVisible('generateAiEditorial')
                ->assertActionVisible('releaseCustomerPreview');
        }
    }

    public function test_published_application_hides_generate_and_release_on_edit_and_view(): void
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $application = $this->prePublicationApplication();
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();

        foreach ([EditApplication::class, ViewApplication::class] as $page) {
            Livewire::actingAs($editor)
                ->test($page, ['record' => $application->fresh()->getRouteKey()])
                ->assertSuccessful()
                ->assertActionHidden('generateAiEditorial')
                ->assertActionHidden('releaseCustomerPreview');
        }
    }

    public function test_open_maintenance_request_hides_generate_and_release_on_edit_and_view(): void
    {
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $application = $this->prePublicationApplication();
        $this->attachOpenMaintenanceRequest($application);

        foreach ([EditApplication::class, ViewApplication::class] as $page) {
            Livewire::actingAs($editor)
                ->test($page, ['record' => $application->fresh()->getRouteKey()])
                ->assertSuccessful()
                ->assertActionHidden('generateAiEditorial')
                ->assertActionHidden('releaseCustomerPreview');
        }
    }

    public function test_support_staff_do_not_see_generate_or_release_on_view(): void
    {
        $support = User::factory()->support()->create(['email_verified_at' => now()]);
        $application = $this->prePublicationApplication();

        // Support may view the application queue but cannot update/edit.
        $this->actingAs($support)
            ->get('/admin/applications/'.$application->id.'/edit')
            ->assertForbidden();

        Livewire::actingAs($support)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSuccessful()
            ->assertActionHidden('generateAiEditorial')
            ->assertActionHidden('releaseCustomerPreview');
    }

    public function test_suspended_editor_loses_editorial_capability_used_by_action_visibility(): void
    {
        $editor = User::factory()->editor()->suspended()->create(['email_verified_at' => now()]);

        // Filament panel access and canManageEditorial both require an active account.
        // Visibility delegates to canManageEditorial(); suspended staff are refused there.
        $this->assertFalse($editor->isActiveAccount());
        $this->assertFalse($editor->canManageEditorial());
        $this->assertFalse($editor->canAccessPanel(filament()->getDefaultPanel()));
    }

    public function test_admin_sees_generate_and_release_like_editor_on_both_pages(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $application = $this->prePublicationApplication();

        foreach ([EditApplication::class, ViewApplication::class] as $page) {
            Livewire::actingAs($admin)
                ->test($page, ['record' => $application->getRouteKey()])
                ->assertSuccessful()
                ->assertActionVisible('generateAiEditorial')
                ->assertActionVisible('releaseCustomerPreview');
        }
    }

    private function prePublicationApplication(): Application
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Pre Publication Subject',
            'display_name' => 'Pre Publication Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        return Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Thrissur',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
        ]);
    }

    private function attachOpenMaintenanceRequest(Application $application): EditorialRevisionRequest
    {
        return EditorialRevisionRequest::query()->create([
            'application_id' => $application->id,
            'profile_id' => $application->profile_id,
            'requested_by_user_id' => $application->user_id,
            'round_number' => 1,
            'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
            'billing_classification' => EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            'status' => EditorialRevisionRequest::STATUS_SUBMITTED,
            'request_text' => 'Synthetic open maintenance request for visibility tests.',
        ]);
    }
}
