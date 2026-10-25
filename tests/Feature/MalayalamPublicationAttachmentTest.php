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
 * A published English version does not authorise its paired Malayalam draft.
 * The current workflow has no separate customer approval for Malayalam that
 * can be attached without a replacement publication.
 */
class MalayalamPublicationAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paired_malayalam_draft_stays_unpublished_and_does_not_replace_english(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'published',
            'full_name' => 'Ratish Ravindra',
            'display_name' => 'Ratish Ravindra',
            'profession' => '',
            'slug' => 'ratish.publication.hold',
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
            'body' => 'Approved English biography.',
            'summary' => 'Approved English summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 3,
            'title' => 'രതീഷ് രവീന്ദ്ര',
            'body' => 'മലയാളം കരട്.',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'source_material' => '',
            'source_editorial_content_id' => $english->id,
            'ai_generated' => false,
        ]);
        $approvedAt = now()->subHour();
        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Ratish Ravindra',
            'package_tier' => 'distinguished',
            'pricing_generation' => Application::PRICING_GENERATION_STANDARD,
            'status' => Application::STATUS_PUBLISHED,
            'source_method' => 'admin_test_demo',
            'published_english_editorial_content_id' => $english->id,
            'published_malayalam_editorial_content_id' => null,
            'customer_approved_at' => $approvedAt,
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'preview_english_editorial_content_id' => $english->id,
            'preview_malayalam_editorial_content_id' => $malayalam->id,
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

        $this->get('/ratish.publication.hold')
            ->assertOk()
            ->assertSee('Approved English biography.', false)
            ->assertDontSee('aria-label="Language"', false)
            ->assertDontSee('മലയാളം കരട്', false);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
            $this->fail('A published profile must not be replaced merely to attach a Malayalam draft.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('fresh customer-approved version', $e->getMessage());
        }

        $application->refresh();
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertNull($application->published_malayalam_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $english->fresh()->status);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $malayalam->fresh()->status);
        $this->assertSame($english->id, $application->customer_approved_english_editorial_content_id);
        $this->assertSame(
            $approvedAt->format('Y-m-d H:i:s'),
            $application->customer_approved_at?->format('Y-m-d H:i:s'),
        );
    }
}
