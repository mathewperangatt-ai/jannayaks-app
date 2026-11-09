<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Customer approval of the exact versions shown, its visibility to the admin,
 * and the separation between normal (customer-approved) and exceptional
 * publication.
 */
class CustomerApprovalPublicationTest extends TestCase
{
    use RefreshDatabase;

    private const BOTH_CONSENT = 'I have reviewed both the English and Malayalam versions of this editorially prepared profile shown above. I consent to the publication of both versions.';

    private const ENGLISH_ONLY_CONSENT = 'This approval covers the English version only; no Malayalam version was shown for my review.';

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        ini_set('memory_limit', '1G');
        parent::setUp();
    }

    public function test_customer_approval_of_both_versions_is_persisted_and_shown_to_admin(): void
    {
        [$application, $english, $malayalam] = $this->releasedPreview(withMalayalam: true);

        $this->actingAs($this->member)->get(route('applications.preview', $application))
            ->assertOk()
            ->assertSee(self::BOTH_CONSENT, false);
        $this->customerApproves($application, $english);

        $approval = EditorialCustomerApproval::query()->where('application_id', $application->id)->sole();
        $this->assertSame($english->id, (int) $approval->english_editorial_content_id);
        $this->assertSame($malayalam->id, (int) $approval->malayalam_editorial_content_id);
        $this->assertSame(EditorialCustomerApproval::SCOPE_ENGLISH_AND_MALAYALAM, $approval->consent_scope);

        $application->refresh();
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $application->status);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Customer approval received')
            ->assertSee($application->customer_approved_at->format('M j, Y'))
            ->assertSee('EN version #'.$english->id.' (v1)')
            ->assertSee('ML version #'.$malayalam->id.' (v1)')
            ->assertSee('Ready — Publish uses this customer approval')
            ->assertActionVisible('publishApplication')
            ->assertActionEnabled('publishApplication')
            ->assertActionVisible('publishWithoutCustomerApproval');
    }

    public function test_english_only_preview_records_english_only_consent(): void
    {
        [$application, $english] = $this->releasedPreview(withMalayalam: false);

        $this->actingAs($this->member)->get(route('applications.preview', $application))
            ->assertOk()
            ->assertSee(self::ENGLISH_ONLY_CONSENT, false)
            ->assertDontSee(self::BOTH_CONSENT, false);
        $this->customerApproves($application, $english);

        $approval = EditorialCustomerApproval::query()->where('application_id', $application->id)->sole();
        $this->assertNull($approval->malayalam_editorial_content_id);
        $this->assertSame(EditorialCustomerApproval::SCOPE_ENGLISH, $approval->consent_scope);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('Not included — the customer approved the English version only');
    }

    public function test_normal_publish_succeeds_with_approval_of_the_exact_current_versions(): void
    {
        [$application, $english] = $this->releasedPreview(withMalayalam: true);
        $this->customerApproves($application, $english);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('publishApplication')
            ->assertNotified('Profile published');

        $application->refresh();
        $this->assertSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertSame($english->id, (int) $application->published_english_editorial_content_id);

        $audit = StaffActionLog::query()->where('action', 'application.published')->sole();
        $this->assertTrue($audit->after['require_customer_approval']);
        $this->assertNull($audit->after['exceptional_publication_reason']);
    }

    public function test_normal_publish_is_blocked_without_customer_approval(): void
    {
        [$application] = $this->releasedPreview(withMalayalam: true);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('Not yet approved by the customer')
            ->assertSee('Blocked — The customer has not approved the current editorial version.')
            ->assertActionDisabled('publishApplication');

        $this->expectPublishRefused($application, 'Normal publication requires customer approval (awaiting publication).');
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_newer_english_version_makes_the_approval_stale_for_normal_publication(): void
    {
        [$application, $english] = $this->releasedPreview(withMalayalam: true);
        $this->customerApproves($application, $english);
        $this->version(EditorialContent::LANGUAGE_EN, 2, 'A later English draft the customer never saw.');

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('No — a newer English version exists')
            ->assertActionDisabled('publishApplication');

        $this->expectPublishRefused($application->fresh(), 'A newer English version exists that the customer has not approved.');
    }

    public function test_newer_malayalam_version_makes_the_approval_stale_for_normal_publication(): void
    {
        [$application, $english, $malayalam] = $this->releasedPreview(withMalayalam: true);
        $this->customerApproves($application, $english);
        $this->version(EditorialContent::LANGUAGE_ML, 2, 'പിന്നീടുള്ള മലയാളം.', $english->id);

        $this->assertFalse(app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh())['ready']);
        $this->expectPublishRefused($application->fresh(), 'A newer Malayalam version exists that the customer has not approved.');
        $this->assertSame($malayalam->id, (int) EditorialCustomerApproval::query()->sole()->malayalam_editorial_content_id);
    }

    public function test_edit_after_approval_withdraws_it_and_admin_sees_why(): void
    {
        [$application, $english] = $this->releasedPreview(withMalayalam: true);
        $this->customerApproves($application, $english);

        app(EditorialContentVersioningService::class)->applyEditorUpdate($english->fresh(), ['body' => 'Edited after approval.'], $this->admin);

        $application->refresh();
        $this->assertNull($application->customer_approved_at);
        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('Approval withdrawn — not currently approved')
            ->assertSee('Editorial narrative revised via new version')
            ->assertActionDisabled('publishApplication');
    }

    public function test_exceptional_publication_requires_a_reason_and_is_audited(): void
    {
        [$application] = $this->releasedPreview(withMalayalam: true);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('publishWithoutCustomerApproval', ['exceptional_reason' => ''])
            ->assertHasActionErrors(['exceptional_reason' => 'required']);
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->fresh()->status);

        Livewire::actingAs($this->admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('publishWithoutCustomerApproval', ['exceptional_reason' => 'Offline approval received in writing.'])
            ->assertNotified('Profile published (exceptional)');

        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
        $audit = StaffActionLog::query()->where('action', 'application.published')->sole();
        $this->assertFalse($audit->after['require_customer_approval']);
        $this->assertSame('Offline approval received in writing.', $audit->after['exceptional_publication_reason']);
    }

    public function test_publish_actions_are_admin_only(): void
    {
        [$application] = $this->releasedPreview(withMalayalam: true);
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);

        Livewire::actingAs($editor)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertActionHidden('publishApplication')
            ->assertActionHidden('publishWithoutCustomerApproval');
    }

    private function expectPublishRefused(Application $application, string $message): void
    {
        try {
            app(ApplicationWorkflowService::class)->publish($application, $this->admin, true);
            $this->fail('Normal publication should have been refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith($message, $e->getMessage());
        }
    }

    private function customerApproves(Application $application, EditorialContent $english): void
    {
        $this->actingAs($this->member)
            ->post(route('applications.preview.approve', $application), [
                'english_editorial_content_id' => $english->id,
                'confirm_approval' => '1',
            ])
            ->assertRedirect(route('applications.preview', $application))
            ->assertSessionHasNoErrors();
    }

    /**
     * @return array{0: Application, 1: EditorialContent, 2: ?EditorialContent}
     */
    private function releasedPreview(bool $withMalayalam): array
    {
        $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $this->member = User::factory()->create(['email_verified_at' => now()]);
        $this->profile = Profile::query()->create([
            'user_id' => $this->member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Approval Subject',
            'display_name' => 'Approval Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->create([
            'user_id' => $this->member->id,
            'profile_id' => $this->profile->id,
            'full_name' => 'Approval Subject',
            'broad_location' => 'Kochi',
            'source_method' => 'online_interview',
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);
        app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $this->admin, 'Launch QA.');
        $application->refresh()->forceFill([
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'online_interview_completed_at' => now(),
        ])->save();

        $english = $this->version(EditorialContent::LANGUAGE_EN, 1, 'English body for review.');
        $malayalam = $this->version(EditorialContent::LANGUAGE_ML, 1, 'മലയാളം കരട്.', $english->id);

        app(ApplicationWorkflowService::class)->approveEnglishMaster($application, $this->admin);
        if ($withMalayalam) {
            app(EditorialContentVersioningService::class)->applyEditorUpdate($malayalam, ['status' => EditorialContent::STATUS_APPROVED], $this->admin);
        }
        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application->fresh(), $this->admin);

        return [$application->fresh(), $english->fresh(), $withMalayalam ? $malayalam->fresh() : null];
    }

    private Profile $profile;

    private function version(string $language, int $version, string $body, ?int $sourceId = null): EditorialContent
    {
        return EditorialContent::query()->create([
            'profile_id' => $this->profile->id,
            'language' => $language,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => $version,
            'title' => strtoupper($language).' title v'.$version,
            'body' => $body,
            'summary' => 'Summary',
            'source_material' => '',
            'ai_generated' => true,
            'source_editorial_content_id' => $sourceId,
        ]);
    }
}
