<?php

namespace Tests\Feature;

use App\Filament\Widgets\PendingAttentionWidget;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\Schemas\ApplicationInfolist;
use App\Models\Application;
use App\Models\EditorialRevisionRequest;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pass 3 — admin operational scale & wayfinding: reference/slug search,
 * honest Pending Attention counts, maintenance filters, maintenance-aware
 * workspace banner, and bounded request history.
 *
 * List behaviour is asserted through the Livewire table component (Filament
 * defers search/filters to Livewire, so plain HTTP GETs render unfiltered);
 * the widget through its Livewire component.
 */
class AdminScaleWayfindingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
    }

    /** @return \Livewire\Testing\Testable */
    private function listPage(array $filters = [], ?string $search = null)
    {
        $page = Livewire::actingAs($this->admin)
            ->test(ListApplications::class);

        foreach ($filters as $name => $value) {
            $page = $page->filterTable($name, $value);
        }

        if ($search !== null) {
            $page = $page->searchTable($search);
        }

        return $page;
    }

    /** @return \Livewire\Testing\Testable */
    private function workspacePage(Application $application)
    {
        return Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()]);
    }

    private function makePublishedApp(string $name, string $slug, string $classification = 'complimentary', string $status = 'submitted', ?string $correctionText = null): Application
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => $name,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $profile->forceFill([
            'slug' => $slug,
            'published_at' => '2026-06-01',
            'slug_generated_at' => now(),
        ])->save();

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();

        if ($status !== 'none') {
            EditorialRevisionRequest::query()->create([
                'application_id' => $application->id,
                'profile_id' => $profile->id,
                'requested_by_user_id' => $member->id,
                'round_number' => null,
                'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
                'billing_classification' => $classification,
                'eligibility_published_on' => '2026-06-01',
                'next_eligible_on' => '2026-09-01',
                'status' => $status,
                'request_text' => $name.' update request text.',
                'customer_correction_text' => $correctionText,
            ]);
        }

        return $application;
    }

    // ------------------------------------------------------------------
    // 1–4. Applications list search
    // ------------------------------------------------------------------

    public function test_search_by_valid_jn_reference_returns_the_correct_application(): void
    {
        $target = $this->makePublishedApp('Target Person', 'target.person');
        $this->makePublishedApp('Other Person', 'other.person');

        $this->listPage(search: (string) $target->profile->reference_code)
            ->assertOk()
            ->assertSee('Target Person')
            ->assertDontSee('Other Person');
    }

    public function test_search_by_nonexistent_jn_reference_returns_no_records(): void
    {
        $this->makePublishedApp('Target Person', 'target.person');
        $this->makePublishedApp('Other Person', 'other.person');

        $this->listPage(search: 'JN-ZZZZZ')
            ->assertOk()
            ->assertDontSee('Target Person')
            ->assertDontSee('Other Person');
    }

    public function test_search_by_public_slug_works(): void
    {
        $this->makePublishedApp('Target Person', 'target.person');
        $this->makePublishedApp('Other Person', 'other.person');

        $this->listPage(search: 'other.person')
            ->assertOk()
            ->assertSee('Other Person')
            ->assertDontSee('Target Person');
    }

    public function test_existing_name_search_remains_functional(): void
    {
        $this->makePublishedApp('Target Person', 'target.person');
        $this->makePublishedApp('Other Person', 'other.person');

        $this->listPage(search: 'Target Person')
            ->assertOk()
            ->assertSee('Target Person')
            ->assertDontSee('Other Person');
    }

    // ------------------------------------------------------------------
    // 5–8. Pending Attention: honest counts, cap, destinations
    // ------------------------------------------------------------------

    public function test_pending_attention_counts_all_outstanding_records_beyond_ten(): void
    {
        $this->appWithPhotos(12);

        Livewire::actingAs($this->admin)
            ->test(PendingAttentionWidget::class)
            ->assertSee('Photographs awaiting approval — 12')
            ->assertSee('View all')
            ->assertSee('filters%5Bphoto_queue%5D%5Bvalue%5D=pending', false);
    }

    public function test_completed_maintenance_requests_are_excluded_from_actionable_counts(): void
    {
        $this->makePublishedApp('Done Person', 'done.person', status: 'completed');
        $this->makePublishedApp('Cancelled Person', 'cancelled.person', status: 'cancelled');

        Livewire::actingAs($this->admin)
            ->test(PendingAttentionWidget::class)
            ->assertDontSee('Published-profile updates')
            ->assertSee('0 outstanding items');
    }

    public function test_pending_attention_still_displays_only_ten_items_per_category(): void
    {
        // 12 distinct applications, each with one submitted maintenance
        // request: the category shows a true count of 12 but lists only 10.
        for ($i = 1; $i <= 12; $i++) {
            $this->makePublishedApp('Maintenance Person '.$i, 'maintenance.person.'.$i);
        }

        $rendered = Livewire::actingAs($this->admin)
            ->test(PendingAttentionWidget::class)
            ->assertSee('Published-profile updates — 12')
            ->assertSee('View all');

        $html = (string) $rendered->html();
        // Only the maintenance category is non-empty: exactly 10 item rows
        // (newest first — Persons 12 down to 3; Persons 1 and 2 are the
        // hidden overflow).
        $this->assertSame(10, substr_count($html, 'Open →'));
        $this->assertStringContainsString('Maintenance Person 12', $html);
        $this->assertStringNotContainsString('Maintenance Person 1 ·', $html);
        $this->assertStringNotContainsString('Maintenance Person 2 ·', $html);
    }

    public function test_view_all_destination_applies_the_maintenance_filter(): void
    {
        $this->makePublishedApp('Ready Person', 'ready.person', status: 'customer_approved');
        $this->makePublishedApp('Prep Person', 'prep.person', status: 'submitted');

        $this->listPage(['maintenance_stage' => 'ready'])
            ->assertOk()
            ->assertSee('Ready Person')
            ->assertDontSee('Prep Person');
    }

    public function test_view_all_url_renders_the_filtered_list_server_side(): void
    {
        // The exact URL shape the widget's "View all" link produces —
        // ListRecords binds tableFilters to the URL as `filters`.
        $this->makePublishedApp('Ready Person', 'ready.person', status: 'customer_approved');
        $this->makePublishedApp('Prep Person', 'prep.person', status: 'submitted');

        $this->actingAs($this->admin)
            ->get('/admin/applications?'.http_build_query(['filters' => ['maintenance_stage' => ['value' => 'ready']]]))
            ->assertOk()
            ->assertSee('Ready Person')
            ->assertDontSee('Prep Person');
    }

    public function test_view_all_destination_applies_the_photo_filter(): void
    {
        $this->appWithPhotos(2, 'Photo Person', 'photo.person');
        $this->makePublishedApp('Clean Person', 'clean.person', status: 'none');

        $this->listPage(['photo_queue' => 'pending'])
            ->assertOk()
            ->assertSee('Photo Person')
            ->assertDontSee('Clean Person');
    }

    // ------------------------------------------------------------------
    // 9–14. Maintenance filters
    // ------------------------------------------------------------------

    public function test_maintenance_editorial_work_filter(): void
    {
        $this->makePublishedApp('Prep Person', 'prep.person', status: 'submitted');
        $this->makePublishedApp('Waiting Person', 'waiting.person', status: 'customer_preview');

        $this->listPage(['maintenance_stage' => 'prep'])
            ->assertOk()
            ->assertSee('Prep Person')
            ->assertDontSee('Waiting Person');
    }

    public function test_awaiting_customer_filter(): void
    {
        $this->makePublishedApp('Prep Person', 'prep.person', status: 'submitted');
        $this->makePublishedApp('Waiting Person', 'waiting.person', status: 'customer_preview');

        $this->listPage(['maintenance_stage' => 'awaiting_customer'])
            ->assertOk()
            ->assertSee('Waiting Person')
            ->assertDontSee('Prep Person');
    }

    public function test_ready_to_publish_filter(): void
    {
        $this->makePublishedApp('Approved Person', 'approved.person', status: 'customer_approved');
        $this->makePublishedApp('Prep Person', 'prep.person', status: 'submitted');

        $this->listPage(['maintenance_stage' => 'ready'])
            ->assertOk()
            ->assertSee('Approved Person')
            ->assertDontSee('Prep Person');
    }

    public function test_correction_pending_filter(): void
    {
        $this->makePublishedApp('Corrected Person', 'corrected.person', status: 'in_progress', correctionText: 'Please change 2026 to 2025.');
        $this->makePublishedApp('Plain Prep Person', 'plain.person', status: 'in_progress');

        $this->listPage(['maintenance_stage' => 'correction'])
            ->assertOk()
            ->assertSee('Corrected Person')
            ->assertDontSee('Plain Prep Person');
    }

    public function test_complimentary_and_paid_filters(): void
    {
        $this->makePublishedApp('Free Person', 'free.person', classification: 'complimentary', status: 'submitted');
        $this->makePublishedApp('Paid Person', 'paid.person', classification: 'paid', status: 'submitted');

        $this->listPage(['maintenance_billing' => 'paid'])
            ->assertOk()
            ->assertSee('Paid Person')
            ->assertDontSee('Free Person');

        $this->listPage(['maintenance_billing' => 'complimentary'])
            ->assertOk()
            ->assertSee('Free Person')
            ->assertDontSee('Paid Person');
    }

    public function test_completed_history_never_matches_open_maintenance_filters(): void
    {
        // Only a COMPLETED cycle exists — no open work, so every open-stage
        // filter must exclude this application.
        $this->makePublishedApp('Historic Person', 'historic.person', status: 'completed');
        $this->makePublishedApp('Current Person', 'current.person', status: 'submitted');

        // The submitted open request matches the open/prep stages; the
        // completed-only application matches none of them, ever.
        foreach (['open', 'prep'] as $stage) {
            $this->listPage(['maintenance_stage' => $stage])
                ->assertOk()
                ->assertSee('Current Person')
                ->assertDontSee('Historic Person');
        }

        foreach (['awaiting_customer', 'ready', 'correction'] as $stage) {
            $this->listPage(['maintenance_stage' => $stage])
                ->assertOk()
                ->assertDontSee('Historic Person')
                ->assertDontSee('Current Person');
        }
    }

    // ------------------------------------------------------------------
    // 15–16. Workspace banner wayfinding
    // ------------------------------------------------------------------

    public function test_banner_reflects_each_active_maintenance_stage(): void
    {
        $cases = [
            'submitted' => ['Maintenance update requested (Complimentary)', 'prepare the editorial update'],
            'in_progress' => ['Maintenance update in progress (Complimentary)', 'prepare the revised EN/ML versions'],
            'customer_preview' => ['Maintenance preview awaiting customer (Complimentary)', 'Await customer approval'],
            'customer_approved' => ['Customer approved maintenance update (Complimentary)', 'publish the approved update'],
        ];

        foreach ($cases as $status => [$expectedState, $expectedActionFragment]) {
            $application = $this->makePublishedApp('Stage Person', 'stage.person.'.$status, status: $status);

            $wayfinding = ApplicationInfolist::wayfindingFor($application);
            $this->assertStringContainsString($expectedState, $wayfinding['state'], "state for {$status}");
            $this->assertStringContainsString($expectedActionFragment, $wayfinding['action'], "action for {$status}");
        }
    }

    public function test_banner_flags_received_customer_corrections(): void
    {
        $application = $this->makePublishedApp(
            'Correction Person',
            'correction.person',
            status: 'in_progress',
            correctionText: 'Please change 2026 to 2025.',
        );

        $wayfinding = ApplicationInfolist::wayfindingFor($application);
        $this->assertStringContainsString('Customer correction received', $wayfinding['state']);
        $this->assertStringContainsString("customer's requested corrections", $wayfinding['action']);
    }

    public function test_banner_returns_to_published_wayfinding_without_an_open_cycle(): void
    {
        // No request at all.
        $application = $this->makePublishedApp('Plain Person', 'plain.person', status: 'none');
        $wayfinding = ApplicationInfolist::wayfindingFor($application);
        $this->assertSame('Published', $wayfinding['state']);
        $this->assertStringContainsString('None — the profile is live', $wayfinding['action']);

        // A completed cycle must not keep the banner in maintenance mode.
        $application2 = $this->makePublishedApp('Finished Person', 'finished.person', status: 'completed');
        $wayfinding2 = ApplicationInfolist::wayfindingFor($application2);
        $this->assertSame('Published', $wayfinding2['state']);
        $this->assertStringContainsString('None — the profile is live', $wayfinding2['action']);
    }

    // ------------------------------------------------------------------
    // 17. Maintenance history presentation
    // ------------------------------------------------------------------

    public function test_workspace_shows_open_cycle_prominently_and_keeps_completed_history_accessible(): void
    {
        $application = $this->makePublishedApp('History Person', 'history.person', status: 'submitted');

        $completed = EditorialRevisionRequest::query()->create([
            'application_id' => $application->id,
            'profile_id' => $application->profile_id,
            'requested_by_user_id' => $application->user_id,
            'round_number' => null,
            'request_type' => EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE,
            'billing_classification' => EditorialRevisionRequest::BILLING_COMPLIMENTARY,
            'eligibility_published_on' => '2026-03-01',
            'next_eligible_on' => '2026-06-01',
            'status' => EditorialRevisionRequest::STATUS_COMPLETED,
            'request_text' => 'Historical cycle request text for the profile.',
            'processed_at' => now()->subDays(3),
        ]);
        $completedBodyBefore = (string) $completed->request_text;

        $this->workspacePage($application)
            ->assertSee('Customer revision requests')
            ->assertSee('History Person update request text.')
            ->assertSee('Completed request history')
            ->assertSee('Historical cycle request text for the profile.');

        // Historical records are unchanged by the presentation.
        $completed->refresh();
        $this->assertSame($completedBodyBefore, $completed->request_text);
        $this->assertSame(EditorialRevisionRequest::STATUS_COMPLETED, $completed->status);
    }

    /** @return array{0: Application, 1: Profile} */
    private function appWithPhotos(int $count, string $name = 'Photo Person', string $slug = 'photo.person'): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => $name,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $profile->forceFill([
            'slug' => $slug,
            'published_at' => '2026-06-01',
            'slug_generated_at' => now(),
        ])->save();

        for ($i = 1; $i <= $count; $i++) {
            MediaItem::query()->create([
                'mediable_type' => $profile->getMorphClass(),
                'mediable_id' => $profile->id,
                'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
                'storage_path_key' => 'profiles/verify/pending-'.$i.'.jpg',
                'privacy' => MediaItem::PRIVACY_PUBLIC,
                'review_status' => MediaItem::REVIEW_PENDING,
                'mime_type' => 'image/jpeg',
            ]);
        }

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
        ]);
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();

        return [$application, $profile];
    }
}
