<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Schemas\ApplicationInfolist;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\StaffActionLog;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Support\PublicationTestBypass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Temporary allowlisted publication test bypass.
 * Disabled by default. It never records a payment and never skips customer approval.
 */
class PublicationTestBypassTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED = 'JN-DBMQZ';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jannayaks.payments.testing_mode' => false,
            'jannayaks.editorial_test_bypass.enabled' => false,
            'jannayaks.editorial_test_bypass.reference_codes' => '',
            'jannayaks.publication_test_bypass.enabled' => false,
            'jannayaks.publication_test_bypass.reference_codes' => '',
        ]);
    }

    public function test_publication_bypass_is_disabled_by_default_and_does_not_name_a_profile(): void
    {
        $this->assertFalse(config('jannayaks.publication_test_bypass.enabled'));
        $this->assertSame('', config('jannayaks.publication_test_bypass.reference_codes'));
        $this->assertFalse(app(PublicationTestBypass::class)->enabled());

        config(['jannayaks.publication_test_bypass.reference_codes' => self::ALLOWED]);

        [$admin, $application, $english] = $this->seedApprovedApplication(self::ALLOWED);
        $this->assertFalse(app(PublicationTestBypass::class)->appliesTo($application));
        $this->assertNull(app(PublicationTestBypass::class)->adminNotice($application));
        $this->assertStringNotContainsString('Publication test active', ApplicationInfolist::workflowBannerHtml($application));

        try {
            app(ApplicationWorkflowService::class)->publish($application, $admin, true);
            $this->fail('A disabled publication bypass must not publish an unpaid profile.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before publication.', $e->getMessage());
        }

        $application->refresh();
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $application->payment_status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertFalse($application->profile->fresh()->isPubliclyListed());
    }

    public function test_allowlisted_profile_publishes_only_the_customer_approved_version_without_payment(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, $application, $approved] = $this->seedApprovedApplication(self::ALLOWED);
        $workflow = app(ApplicationWorkflowService::class);
        $bypass = app(PublicationTestBypass::class);

        $replacement = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Newer approved title',
            'body' => 'Newer approved text that the customer did not approve.',
            'summary' => 'Newer summary',
            'source_material' => '',
            'ai_generated' => false,
            'created_by_id' => $approved->created_by_id,
            'reviewed_by_id' => $approved->reviewed_by_id,
        ]);

        $this->assertTrue($bypass->appliesTo($application->fresh()));
        $notice = ApplicationInfolist::workflowBannerHtml($application->fresh());
        $this->assertStringContainsString('Publication test active', $notice);
        $this->assertStringContainsString(self::ALLOWED, $notice);
        $this->assertStringContainsString('without a settled payment', $notice);

        try {
            $workflow->publish($application->fresh(), $admin, false);
            $this->fail('The exceptional admin path must not use the publication test bypass.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The publication test bypass requires customer approval of the exact approved English version.', $e->getMessage());
        }

        $published = $workflow->publish($application->fresh(), $admin, true);

        $this->assertSame(Application::STATUS_PUBLISHED, $published->status);
        $this->assertSame($approved->id, $published->published_english_editorial_content_id);
        $this->assertNotSame($replacement->id, $published->published_english_editorial_content_id);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $published->payment_status);
        $this->assertNull($published->payment_settled_at);
        $this->assertSame(0, Payment::query()->count());
        $this->assertTrue($published->profile->isPubliclyListed());
        $this->get('/test-'.strtolower(self::ALLOWED))
            ->assertOk()
            ->assertSee('Approved public biography for the layout test.', false)
            ->assertDontSee('Newer approved text that the customer did not approve.', false);

        $audit = StaffActionLog::query()->where('action', 'application.publication_test_bypass_used')->sole();
        $this->assertSame(self::ALLOWED, $audit->after['profile_reference_code']);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $audit->after['payment_status']);
        $this->assertSame($approved->id, $audit->after['published_english_editorial_content_id']);
        $this->assertSame($approved->id, $audit->after['customer_approved_english_editorial_content_id']);
        $this->assertFalse($bypass->appliesTo($published->fresh()));
        $this->assertStringContainsString('publicly visible', ApplicationInfolist::workflowBannerHtml($published->fresh()));

        $hidden = $workflow->unpublishPublicationTest($published->fresh(), $admin);
        $this->assertSame(Application::STATUS_AWAITING_PUBLICATION, $hidden->status);
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $hidden->payment_status);
        $this->assertFalse($hidden->profile->isPubliclyListed());
        $this->assertNotNull($hidden->profile->unpublished_at);
        $this->get('/test-'.strtolower(self::ALLOWED))->assertNotFound();
        $this->assertSame(0, Payment::query()->count());
        $this->assertTrue(StaffActionLog::query()->where('action', 'application.publication_test_unpublished')->exists());

        config(['jannayaks.publication_test_bypass.enabled' => false]);
        try {
            $workflow->publish($hidden->fresh(), $admin, true);
            $this->fail('Disabling the publication bypass must restore the payment gate.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before publication.', $e->getMessage());
        }

        $hidden->refresh();
        $this->assertSame(Application::PAYMENT_STATUS_PENDING, $hidden->payment_status);
        $this->assertSame(0, Payment::query()->count());
        $this->assertFalse($hidden->profile->fresh()->isPubliclyListed());
    }

    public function test_unapproved_or_wrong_version_stays_blocked_and_other_applications_still_require_settlement(): void
    {
        $this->enableFor(self::ALLOWED);
        [$admin, $application, $draft] = $this->seedApprovedApplication(self::ALLOWED);
        $draft->forceFill(['status' => EditorialContent::STATUS_DRAFT, 'reviewed_by_id' => null])->save();

        $older = EditorialContent::query()->create([
            'profile_id' => $application->profile_id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Older approved title',
            'body' => 'Older approved text.',
            'summary' => 'Older summary',
            'source_material' => '',
            'ai_generated' => false,
            'created_by_id' => $draft->created_by_id,
            'reviewed_by_id' => $admin->id,
        ]);

        $workflow = app(ApplicationWorkflowService::class);
        try {
            $workflow->publish($application->fresh(), $admin, true);
            $this->fail('Customer approval of a draft must not publish it or an older approved version.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('An unapproved editorial draft cannot be published.', $e->getMessage());
        }

        try {
            $workflow->publish($application->fresh(), $admin, false);
            $this->fail('The exceptional path must stay closed under the publication test bypass.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The publication test bypass requires customer approval of the exact approved English version.', $e->getMessage());
        }

        $application->refresh();
        $this->assertNotSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertNull($application->published_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $draft->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $older->fresh()->status);
        $this->assertFalse($application->profile->fresh()->isPubliclyListed());
        $this->assertSame(0, Payment::query()->count());

        [, $ordinary] = $this->seedApprovedApplication('JN-OTHER');
        try {
            $workflow->publish($ordinary->fresh(), $admin, true);
            $this->fail('An application outside the allowlist must still require settlement.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before publication.', $e->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
        $this->assertFalse($ordinary->fresh()->profile->isPubliclyListed());
        $this->assertStringNotContainsString('Publication test active', ApplicationInfolist::workflowBannerHtml($ordinary->fresh()));
    }

    public function test_editorial_preview_bypass_still_does_not_authorize_publication(): void
    {
        config([
            'jannayaks.editorial_test_bypass.enabled' => true,
            'jannayaks.editorial_test_bypass.reference_codes' => self::ALLOWED,
        ]);

        [$admin, $application] = $this->seedApprovedApplication(self::ALLOWED);
        $this->assertFalse(app(PublicationTestBypass::class)->appliesTo($application));

        try {
            app(ApplicationWorkflowService::class)->publish($application, $admin, true);
            $this->fail('The editorial preview bypass must not waive publication payment.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Payment must be settled before publication.', $e->getMessage());
        }

        $this->assertSame(0, Payment::query()->count());
    }

    private function enableFor(string $reference): void
    {
        config([
            'jannayaks.publication_test_bypass.enabled' => true,
            'jannayaks.publication_test_bypass.reference_codes' => $reference,
        ]);
    }

    /**
     * @return array{0: User, 1: Application, 2: EditorialContent}
     */
    private function seedApprovedApplication(string $reference): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);

        $profile = new Profile([
            'user_id' => $member->id,
            'status' => 'customer_preview',
            'full_name' => 'Layout Tester '.$reference,
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
            'slug' => 'test-'.strtolower($reference),
        ]);
        $profile->reference_code = $reference;
        $profile->save();

        $application = Application::factory()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Layout Tester '.$reference,
            'source_method' => 'online_interview',
            'status' => Application::STATUS_AWAITING_PUBLICATION,
            'payment_status' => Application::PAYMENT_STATUS_PENDING,
            'package_tier' => 'distinguished',
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Approved title '.$reference,
            'body' => 'Approved public biography for the layout test.',
            'summary' => 'Approved summary',
            'source_material' => '',
            'ai_generated' => false,
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_by_user_id' => $member->id,
            'customer_approved_english_editorial_content_id' => $english->id,
            'preview_english_editorial_content_id' => $english->id,
        ])->save();

        return [$admin, $application->fresh(), $english->fresh()];
    }
}
