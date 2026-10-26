<?php

namespace Tests\Feature;

use App\Contracts\PhotoEnhancementClient;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\ProfileMediaRelationManager;
use App\Models\Application;
use App\Models\PhotoEnhancementRun;
use App\Models\Profile;
use App\Models\User;
use App\Services\Ai\FakePhotoEnhancementClient;
use App\Services\PhotoEnhancementService;
use App\Services\ProfileMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 2 — admin UI surface of AI photo enhancement inside the existing
 * Profile photographs relation manager: status badge, side-by-side
 * comparison modal, and the three decision actions.
 */
class PhotoEnhancementAdminUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('jannayaks.ai.image_enhancement.enabled', true);
        Config::set('jannayaks.ai.image_enhancement.provider', 'fake');
        Storage::fake('public');
        config(['jannayaks.media.public_disk' => 'public']);

        $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'UI Person',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $this->application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'UI Person',
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
        ]);
    }

    private function relation()
    {
        return Livewire::actingAs($this->admin)
            ->test(ProfileMediaRelationManager::class, [
                'ownerRecord' => $this->application,
                'pageClass' => ViewApplication::class,
            ]);
    }

    public function test_completed_run_shows_status_badge_and_all_three_actions(): void
    {
        $source = app(ProfileMediaService::class)->uploadProfilePhoto(
            profile: $this->application->profile,
            file: UploadedFile::fake()->image('p.jpg', 600, 800),
            actor: $this->application->user,
        );

        $this->relation()
            ->assertSuccessful()
            ->assertSeeText('Ready for review')
            ->assertSeeText('Compare AI enhancement')
            ->assertSeeText('Accept Enhanced')
            ->assertSeeText('Keep Original')
            ->assertSeeText('Regenerate');
    }

    public function test_comparison_modal_renders_source_and_candidate_labels(): void
    {
        $source = app(ProfileMediaService::class)->uploadProfilePhoto(
            profile: $this->application->profile,
            file: UploadedFile::fake()->image('p.jpg', 600, 800),
            actor: $this->application->user,
        );
        $run = app(PhotoEnhancementService::class)->latestRunFor($source);
        $this->assertSame(PhotoEnhancementRun::STATUS_COMPLETED, $run->status);

        // The modal content is exactly this view with these bindings.
        $html = view('filament.relation-managers.photo-enhancement-comparison', [
            'source' => $source,
            'run' => $run,
        ])->render();

        $this->assertStringContainsString('Customer source', $html);
        $this->assertStringContainsString('AI enhanced candidate', $html);
        $this->assertStringContainsString('Status:', $html);
        $this->assertStringContainsString('Ready for review', $html);
        $this->assertStringContainsString(route('staff.profile-media.preview', ['media' => $source]), $html);
        $this->assertStringContainsString(route('staff.profile-media.preview', ['media' => $run->candidate]), $html);
        $this->assertStringContainsString('enhance the photograph, never reinvent the person', $html);
        $this->assertStringContainsString('jy-enhance-compare', $html);
        $this->assertStringContainsString('@media (min-width: 768px)', $html);
        $this->assertStringContainsString('Simulated provider', $html);
        $this->assertStringContainsString('not a genuine AI enhancement', $html);

        // And the action itself renders on the table, including on the stacked mobile layout.
        $this->relation()
            ->assertSeeText('Compare AI enhancement')
            ->assertSeeText('Accept Enhanced')
            ->assertSeeText('Keep Original')
            ->assertSeeText('stacked on a phone');
    }

    public function test_no_run_shows_no_enhancement_ui(): void
    {
        // Feature enabled but no photos uploaded — no badges or actions.
        $this->relation()
            ->assertSuccessful()
            ->assertDontSeeText('Ready for review')
            ->assertDontSeeText('Accept Enhanced')
            ->assertDontSeeText('Compare AI enhancement');
    }

    public function test_failed_run_shows_retry_and_no_decision_actions(): void
    {
        app()->instance(PhotoEnhancementClient::class, tap(new FakePhotoEnhancementClient, fn ($c) => $c->failNext = true));

        $source = app(ProfileMediaService::class)->uploadProfilePhoto(
            profile: $this->application->profile,
            file: UploadedFile::fake()->image('p.jpg', 600, 800),
            actor: $this->application->user,
        );

        $this->relation()
            ->assertSuccessful()
            ->assertSeeText('Enhancement failed')
            ->assertSeeText('Retry')
            ->assertDontSeeText('Accept Enhanced')
            ->assertDontSeeText('Keep Original')
            ->assertDontSeeText('Regenerate');
    }
}
