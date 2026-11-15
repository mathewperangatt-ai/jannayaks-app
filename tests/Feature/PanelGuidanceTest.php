<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use App\Support\PanelGuidance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Phase 3 workstream L: integrated English and Malayalam guidance inside
 * the existing customer panel and the Filament administration panel —
 * concise, context-appropriate, reflecting actual implemented behaviour,
 * with the brand always written as Jannayaks.
 */
class PanelGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_covers_all_approved_customer_topics_bilingually(): void
    {
        foreach (['apply', 'interview', 'uploads', 'photos', 'progress', 'review', 'updates', 'payments', 'renewals', 'support'] as $key) {
            $guidance = PanelGuidance::for($key);
            $this->assertNotEmpty($guidance['title_en'], $key);
            $this->assertNotEmpty($guidance['title_ml'], $key);
            $this->assertNotEmpty($guidance['en'], $key);
            $this->assertNotEmpty($guidance['ml'], $key);
        }
    }

    public function test_customer_intake_and_interview_pages_show_bilingual_guidance(): void
    {
        $intro = $this->get(route('home'))->assertOk(); // homepage unaffected
        $this->assertStringNotContainsString('How applying works', $intro->getContent());

        // The intake flow defaults to Malayalam.
        $apply = $this->get(route('apply'))->assertOk()->getContent();
        $this->assertStringContainsString('അപേക്ഷ എങ്ങനെ നടത്താം', $apply);
        $this->assertStringContainsString('നിരക്ക് നിർണ്ണയിക്കുന്നത് താങ്കൾ താമസിക്കുന്ന രാജ്യമാണ്', $apply);

        $member = User::factory()->create(['email_verified_at' => now()]);
        $applyEn = $this->actingAs($member)->get(route('apply', ['lang' => 'en']))->assertOk()->getContent();
        $this->assertStringContainsString('How applying works', $applyEn);
        // Language-switched page renders the English guidance only.
        $this->assertStringNotContainsString('അപേക്ഷ എങ്ങനെ നടത്താം', $applyEn);

        [$member2, $application] = $this->interviewApplication();
        $interview = $this->actingAs($member2)->get(route('online-interview.show', $application))
            ->assertOk()->getContent();
        $this->assertStringContainsString('About the Online Interview', $interview);
        $this->assertStringContainsString('ഓൺലൈൻ അഭിമുഖത്തെക്കുറിച്ച്', $interview);
    }

    public function test_upload_media_dashboard_and_membership_pages_show_guidance(): void
    {
        [$member, $application] = $this->interviewApplication();

        $this->actingAs($member)->get(route('applications.upload.show', $application))
            ->assertOk()->assertSee('Supporting documents', false)
            ->assertSee('തെളിവുരേഖകൾ', false);

        $this->actingAs($member)->get(route('applications.media', $application))
            ->assertOk()->assertSee('Photographs', false)
            ->assertSee('ഫോട്ടോഗ്രാഫുകൾ', false);

        $dashboard = $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()->getContent();
        $this->assertStringContainsString('What happens next', $dashboard);

        $renewalMember = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $renewalMember->id,
            'status' => 'published',
            'full_name' => 'Guidance Renewal Member',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $membership = \App\Models\Membership::query()->create([
            'profile_id' => $profile->id,
            'user_id' => $renewalMember->id,
            'status' => 'active',
            'tier' => 'basic',
            'starts_on' => now()->subMonths(11),
            'ends_on' => now()->addMonth(),
            'renewal_due_on' => now()->addWeeks(2),
            'auto_renew' => false,
        ]);

        $this->actingAs($renewalMember)->get(route('membership.show', $profile))
            ->assertOk()->assertSee('Annual renewal', false)
            ->assertSee('വാർഷിക പുതുക്കൽ', false);
    }

    public function test_customer_preview_shows_review_guidance(): void
    {
        [$admin, $member, $application, $english] = $this->releasedPreview();

        $this->actingAs($member)->get(route('applications.preview', $application))
            ->assertOk()
            ->assertSee('Reviewing and approving', false)
            ->assertSee('പരിശോധിച്ച് അംഗീകരിക്കൽ', false);
    }

    public function test_admin_infolist_shows_staff_guidance(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create(['country_of_residence' => 'IN']);

        Livewire::actingAs($admin)->test(\App\Filament\Resources\Applications\Pages\ViewApplication::class, [
            'record' => $application->getRouteKey(),
        ])->assertSuccessful()
            ->assertSee('Editorial, media and payments guidance', false)
            ->assertSee('എഡിറ്റോറിയൽ, മീഡിയ, പേയ്മെന്റ്', false);

        Livewire::actingAs($member)->test(\App\Filament\Resources\Applications\Pages\ViewApplication::class, [
            'record' => $application->getRouteKey(),
        ])->assertForbidden();
    }

    public function test_guidance_never_promises_unavailable_payment_capabilities(): void
    {
        foreach (PanelGuidance::catalog() as $key => $guidance) {
            foreach (array_merge($guidance['en'], $guidance['ml']) as $line) {
                $this->assertStringNotContainsString('Razorpay', $line, $key);
                $this->assertStringNotContainsString('instant', strtolower($line), $key);
                $this->assertStringNotContainsString('auto-renew', strtolower($line), $key);
            }
        }
        // Payments guidance states the actual situation honestly.
        $payments = PanelGuidance::for('payments');
        $this->assertStringContainsString('Online payment is not active yet', $payments['en'][2]);
    }

    /** Unpaid application whose interview and upload pages are unlocked. */
    private function interviewApplication(): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Guidance Subject',
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

    private function releasedPreview(): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Guidance Preview Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'version_number' => 1,
            'status' => EditorialContent::STATUS_APPROVED,
            'title' => 'Guidance Preview Subject',
            'summary' => 'Summary.',
            'body' => 'Body.',
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'package_tier' => 'distinguished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
        ]);

        app(\App\Services\CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);

        return [$admin, $member, $application->fresh(), $english->fresh()];
    }
}
