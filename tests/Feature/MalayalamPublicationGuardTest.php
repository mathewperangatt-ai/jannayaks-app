<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialCustomerApproval;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The published English approval does not authorise Malayalam. A draft
 * Malayalam version paired to that English text stays unpublished, and the
 * normal replacement publication path cannot attach it.
 */
class MalayalamPublicationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_english_approval_does_not_publish_paired_draft_malayalam(): void
    {
        [$admin, $member, $application, $english, $malayalam] = $this->publishedEnglishWithDraftMalayalam();

        $this->get('/ratish.ravindra')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertDontSee('aria-label="Language"', false)
            ->assertDontSee('Draft Malayalam biography.', false);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('A published profile must not be replacement-published over the existing English approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Replacement publication', $e->getMessage());
        }

        $application->refresh();
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertNull($application->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $malayalam->fresh()->status);
        $this->assertSame($member->id, (int) $application->customer_approved_by_user_id);
    }

    /**
     * @return array{0: User, 1: User, 2: Application, 3: EditorialContent, 4: EditorialContent}
     */
    private function publishedEnglishWithDraftMalayalam(): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $admin = User::factory()->admin()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Ratish Ravindra',
            'display_name' => 'Ratish Ravindra',
            'profession' => 'Public life',
            'slug' => 'ratish.ravindra',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 3,
            'title' => 'Ratish Ravindra',
            'summary' => 'Approved English summary.',
            'body' => 'Approved English biography.',
            'source_material' => '',
            'ai_generated' => false,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 3,
            'title' => 'രതീഷ് രവീന്ദ്ര',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'body' => 'Draft Malayalam biography.',
            'source_material' => '',
            'source_editorial_content_id' => $english->id,
            'ai_generated' => false,
        ]);
        $approvedAt = now()->subHour();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Ratish Ravindra',
            'preferred_display_name' => 'Ratish Ravindra',
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_PUBLISHED,
            'customer_approved_at' => $approvedAt,
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'preview_english_editorial_content_id' => $english->id,
            'preview_malayalam_editorial_content_id' => $malayalam->id,
            'published_english_editorial_content_id' => $english->id,
            'published_malayalam_editorial_content_id' => null,
        ]);
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();
        EditorialCustomerApproval::query()->create([
            'application_id' => $application->id,
            'profile_id' => $profile->id,
            'approved_by_user_id' => $member->id,
            'english_editorial_content_id' => $english->id,
            'malayalam_editorial_content_id' => $malayalam->id,
            'approved_at' => $approvedAt,
        ]);

        return [$admin, $member, $application->fresh(), $english->fresh(), $malayalam->fresh()];
    }
}
