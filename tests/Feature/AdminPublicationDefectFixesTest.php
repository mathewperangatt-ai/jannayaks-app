<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\EditorialRevisionRequest;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\EditorialContentVersioningService;
use App\Services\PostPublicationUpdateService;
use App\Services\PublicProfilePresentationService;
use App\Services\PublicProfileSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AdminPublicationDefectFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_cannot_demote_published_malayalam_but_can_revise_a_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        [$application, $english, $profile] = $this->publishEnglish($admin);
        $publishedBody = 'Live Malayalam biography stays public.';
        $malayalam = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Draft Malayalam before publication.');
        $workflow = app(ApplicationWorkflowService::class);
        $versioning = app(EditorialContentVersioningService::class);

        $workflow->publishMalayalam($application->fresh(), $admin, $malayalam->fresh());
        $malayalam->forceFill(['body' => $publishedBody])->save();
        $malayalam = $malayalam->fresh();

        $draft = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Editor revises this draft.');
        $revised = $versioning->applyEditorUpdate($draft, [
            'body' => 'Editor revised the unpublished draft.',
            'status' => EditorialContent::STATUS_DRAFT,
            'source_material' => 'Editor revision of the unpublished draft.',
        ], $editor);
        $this->assertSame($draft->id, $revised->id);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $revised->status);
        $this->assertSame('Editor revised the unpublished draft.', $revised->body);

        foreach ([EditorialContent::STATUS_DRAFT, EditorialContent::STATUS_ARCHIVED] as $status) {
            try {
                $versioning->applyEditorUpdate($malayalam->fresh(), ['status' => $status], $editor);
                $this->fail('An editor must not change the published Malayalam status to '.$status.'.');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('Only an authorised Admin may change the status of an approved or published Malayalam version.', $e->getMessage());
            }
        }

        $this->assertSame($malayalam->id, $application->fresh()->published_malayalam_editorial_content_id);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $malayalam->fresh()->status);
        $presented = app(PublicProfilePresentationService::class)->present($profile->fresh(), 'ml');
        $this->assertSame($malayalam->id, $presented['malayalam']?->id);
        $this->get('/'.$profile->fresh()->slug.'?lang=ml')
            ->assertOk()
            ->assertSee($publishedBody, false);

        $separate = $this->malayalam($profile, $english, 3, EditorialContent::STATUS_APPROVED, 'Approved Malayalam that is not the live pointer.');
        $archived = $versioning->applyEditorUpdate($separate, ['status' => EditorialContent::STATUS_ARCHIVED], $admin);
        $this->assertSame(EditorialContent::STATUS_ARCHIVED, $archived->status);
        $this->assertSame($malayalam->id, $application->fresh()->published_malayalam_editorial_content_id);
    }

    public function test_english_replacement_clears_an_unpaired_malayalam_pointer_and_search_follows_it(): void
    {
        $admin = User::factory()->admin()->create();
        [$application, $english, $profile] = $this->publishEnglish($admin);
        $staleBody = 'qxstalemalayalammarker must not remain searchable.';
        $pairedBody = 'qxpairedmalayalammarker remains searchable.';
        $workflow = app(ApplicationWorkflowService::class);
        $stale = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, $staleBody);
        $workflow->publishMalayalam($application->fresh(), $admin, $stale);

        $replacement = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Replacement English',
            'summary' => 'Replacement summary',
            'body' => 'Replacement English biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);
        $republished = $workflow->publish($application->fresh(), $admin, false, 'Replace the English biography offline.');

        $this->assertSame($replacement->id, $republished->published_english_editorial_content_id);
        $this->assertNull($republished->published_malayalam_editorial_content_id);
        $this->assertDoesNotMatchSearch($profile, 'qxstalemalayalammarker');
        $presented = app(PublicProfilePresentationService::class)->present($profile->fresh(), 'ml');
        $this->assertNull($presented['malayalam']);

        $paired = $this->malayalam($profile, $replacement, 2, EditorialContent::STATUS_APPROVED, $pairedBody);
        $workflow->publishMalayalam($republished->fresh(), $admin, $paired);
        $this->assertSame($paired->id, $application->fresh()->published_malayalam_editorial_content_id);
        $this->assertMatchesSearch($profile, 'qxpairedmalayalammarker');
        $this->assertDoesNotMatchSearch($profile, 'qxstalemalayalammarker');
    }

    public function test_maintenance_publishes_the_customer_approved_malayalam_version_only(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        [$application, $english, $profile] = $this->publishEnglish($admin, $member);
        $workflow = app(ApplicationWorkflowService::class);
        $maintenance = app(PostPublicationUpdateService::class);
        $current = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_APPROVED, 'Current published Malayalam.');
        $workflow->publishMalayalam($application->fresh(), $admin, $current);

        $nextEnglish = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Next English',
            'summary' => 'Next summary',
            'body' => 'Next English biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);
        $approvedMalayalam = $this->malayalam($profile, $nextEnglish, 2, EditorialContent::STATUS_APPROVED, 'Customer approved Malayalam.');
        $newerMalayalam = $this->malayalam($profile, $nextEnglish, 3, EditorialContent::STATUS_APPROVED, 'Newer Malayalam that must not replace the approval.');

        $request = $maintenance->submit($application->fresh(), $member, 'Please publish the revised bilingual profile.');
        $request->forceFill([
            'resulting_english_editorial_content_id' => $nextEnglish->id,
            'resulting_malayalam_editorial_content_id' => $approvedMalayalam->id,
        ])->save();
        $maintenance->releaseMaintenancePreview($application->fresh(), $admin);
        $maintenance->approveMaintenancePreview($application->fresh(), $member, $nextEnglish->id);
        $maintenance->completePublication($application->fresh(), $admin);

        $published = $application->fresh();
        $this->assertSame($nextEnglish->id, $published->published_english_editorial_content_id);
        $this->assertSame($approvedMalayalam->id, $published->published_malayalam_editorial_content_id);
        $this->assertNotSame($newerMalayalam->id, $published->published_malayalam_editorial_content_id);

        $secondEnglish = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 3,
            'title' => 'Third English',
            'summary' => 'Third summary',
            'body' => 'Third English biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);
        $validMalayalam = $this->malayalam($profile, $secondEnglish, 4, EditorialContent::STATUS_APPROVED, 'Valid paired Malayalam.');
        $mismatched = $this->malayalam($profile, $english, 5, EditorialContent::STATUS_APPROVED, 'Mismatched Malayalam.');
        $second = $maintenance->submit($published, $member, 'Please publish one more English revision.');
        $second->forceFill([
            'resulting_english_editorial_content_id' => $secondEnglish->id,
            'resulting_malayalam_editorial_content_id' => $validMalayalam->id,
        ])->save();
        $maintenance->releaseMaintenancePreview($published->fresh(), $admin);
        $maintenance->approveMaintenancePreview($published->fresh(), $member, $secondEnglish->id);
        EditorialRevisionRequest::query()->whereKey($second->id)->update([
            'approved_malayalam_editorial_content_id' => $mismatched->id,
        ]);

        try {
            $maintenance->completePublication($published->fresh(), $admin);
            $this->fail('An unpaired Malayalam version must not be published or replaced by another version.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('The customer-approved Malayalam version cannot be published. It must stay approved and paired with the published English version.', $e->getMessage());
        }

        $unchanged = $application->fresh();
        $this->assertSame($nextEnglish->id, $unchanged->published_english_editorial_content_id);
        $this->assertSame($approvedMalayalam->id, $unchanged->published_malayalam_editorial_content_id);
        $this->assertNotSame($newerMalayalam->id, $unchanged->published_malayalam_editorial_content_id);
        $this->assertNotSame($mismatched->id, $unchanged->published_malayalam_editorial_content_id);
    }

    public function test_published_profile_preview_release_is_refused_and_maintenance_remains_available(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $member = User::factory()->create();
        [$application, $english, $profile] = $this->publishEnglish($admin, $member);
        $before = $application->fresh()->only([
            'status',
            'customer_approved_at',
            'customer_approved_english_editorial_content_id',
            'customer_preview_released_at',
            'preview_english_editorial_content_id',
            'first_customer_preview_at',
        ]);

        try {
            app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application->fresh(), $editor);
            $this->fail('A direct preview release must not rewind a published profile.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A published profile cannot be released for the pre-publication preview. Use the maintenance workflow.', $e->getMessage());
        }
        $this->assertSame($before, $application->fresh()->only(array_keys($before)));

        $successor = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Maintained English',
            'summary' => 'Maintained summary',
            'body' => 'Maintained English biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);
        $maintenance = app(PostPublicationUpdateService::class);
        $request = $maintenance->submit($application->fresh(), $member, 'Please update the published biography.');
        $request->forceFill(['resulting_english_editorial_content_id' => $successor->id])->save();
        $maintenance->releaseMaintenancePreview($application->fresh(), $admin);
        $this->assertSame('published', $profile->fresh()->status);
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);
        $maintenance->approveMaintenancePreview($application->fresh(), $member, $successor->id);
        $maintenance->completePublication($application->fresh(), $admin);

        $this->assertSame($successor->id, $application->fresh()->published_english_editorial_content_id);
        $this->assertNull($application->fresh()->published_malayalam_editorial_content_id);
    }

    /**
     * @return array{0: Application, 1: EditorialContent, 2: Profile}
     */
    private function publishEnglish(User $admin, ?User $member = null): array
    {
        $owner = $member ?? $admin;
        $profile = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Publication Fix Subject',
            'display_name' => 'Publication Fix Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create([
            'user_id' => $owner->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'package_tier' => 'accomplished',
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'English title',
            'summary' => 'English summary',
            'body' => 'English published biography.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);
        $published = app(ApplicationWorkflowService::class)->publish(
            $application,
            $admin,
            false,
            'Authorised offline publication for this test.',
        );

        return [$published, $english->fresh(), $published->profile];
    }

    private function malayalam(Profile $profile, EditorialContent $english, int $version, string $status, string $body): EditorialContent
    {
        return EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'source_editorial_content_id' => $english->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => $status,
            'version_number' => $version,
            'title' => 'Malayalam '.$version,
            'summary' => 'Summary '.$version,
            'body' => $body,
            'source_material' => 'Prepared Malayalam version '.$version.'.',
            'created_by_id' => $english->created_by_id,
            'reviewed_by_id' => $status === EditorialContent::STATUS_APPROVED ? $english->reviewed_by_id : null,
        ]);
    }

    private function assertMatchesSearch(Profile $profile, string $term): void
    {
        $ids = app(PublicProfileSearchService::class)->search($term)->getCollection()->pluck('id')->all();
        $this->assertContains($profile->id, $ids);
    }

    private function assertDoesNotMatchSearch(Profile $profile, string $term): void
    {
        $ids = app(PublicProfileSearchService::class)->search($term)->getCollection()->pluck('id')->all();
        $this->assertNotContains($profile->id, $ids);
    }
}
