<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\ApplicationResource;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\MalayalamEditorialRelationManager;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\EditorialGenerationService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class MalayalamEditorialAdminTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Filament Livewire relation-manager tests accumulate memory across
        // cases in this file. Keep the raise local to this suite so phpunit.xml
        // does not need an unrelated global memory_limit change.
        ini_set('memory_limit', '512M');
        parent::setUp();
    }

    public function test_application_resource_registers_the_malayalam_editorial_tab(): void
    {
        $this->assertContains(
            MalayalamEditorialRelationManager::class,
            ApplicationResource::getRelations(),
        );
    }

    public function test_application_view_and_tab_expose_visible_edit_and_save_controls(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Original Malayalam biography.');

        Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSuccessful()
            ->assertSeeText('Malayalam editorial');

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->assertSuccessful()
            ->assertSeeText('Edit Malayalam')
            ->assertSeeText('Edit')
            ->assertSeeText('Approve Malayalam')
            ->assertSeeText('Approve and publish Malayalam')
            ->assertSeeText('Draft')
            ->assertSeeText('Not published')
            ->assertActionVisible(TestAction::make('editLatestMalayalam')->table())
            ->assertActionVisible(TestAction::make('approveAndPublishMalayalam')->table())
            ->assertActionVisible(TestAction::make('edit')->table($application->malayalamEditorialContents()->first()))
            ->assertActionVisible(TestAction::make('approve')->table($application->malayalamEditorialContents()->first()));
    }

    public function test_edit_modal_exposes_title_summary_body_and_save_cancel_controls(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $malayalam = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Original Malayalam biography.');

        $component = Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->mountAction(TestAction::make('edit')->table($malayalam));

        $action = $component->instance()->getMountedAction();
        $this->assertNotNull($action);
        $this->assertSame('Save Malayalam Draft', $action->getModalSubmitActionLabel());
        $this->assertSame('Cancel', $action->getModalCancelActionLabel());

        $component
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('summary')
            ->assertFormFieldExists('body')
            ->assertActionDataSet([
                'title' => 'Malayalam 1',
                'summary' => 'Summary 1',
                'body' => 'Original Malayalam biography.',
            ]);
    }

    public function test_tab_lists_only_malayalam_versions_for_the_current_profile(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $ownMl = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Own Malayalam body unique token qxownml.');
        $englishSameProfile = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 2,
            'title' => 'English draft must not appear',
            'summary' => 'English summary',
            'body' => 'English body qxenghidden.',
            'created_by_id' => $admin->id,
        ]);
        $other = $this->seedOtherProfileMalayalam($admin);

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$ownMl])
            ->assertCanNotSeeTableRecords([$englishSameProfile, $other]);
    }

    public function test_edit_action_saves_malayalam_without_changing_english_or_publication_pointers(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $publishedMl = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Published Malayalam body.');
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $publishedMl->fresh());
        $application->refresh();

        $draft = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Editable Malayalam draft.');
        $englishTitle = $english->title;
        $englishBody = $english->body;
        $publishedEn = $application->published_english_editorial_content_id;
        $publishedMlId = $application->published_malayalam_editorial_content_id;

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->assertActionVisible(TestAction::make('edit')->table($draft))
            ->callAction(
                TestAction::make('edit')->table($draft),
                [
                    'title' => 'Revised Malayalam title',
                    'summary' => 'Revised Malayalam summary',
                    'body' => 'Revised Malayalam biography after proofreading.',
                ],
            )
            ->assertHasNoActionErrors();

        $draft->refresh();
        $english->refresh();
        $application->refresh();

        $this->assertSame('Revised Malayalam title', $draft->title);
        $this->assertSame('Revised Malayalam summary', $draft->summary);
        $this->assertSame('Revised Malayalam biography after proofreading.', $draft->body);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $draft->status);
        $this->assertSame(EditorialContent::LANGUAGE_ML, $draft->language);
        $this->assertSame($profile->id, $draft->profile_id);
        $this->assertSame($englishTitle, $english->title);
        $this->assertSame($englishBody, $english->body);
        $this->assertSame($publishedEn, $application->published_english_editorial_content_id);
        $this->assertSame($publishedMlId, $application->published_malayalam_editorial_content_id);
    }

    public function test_header_edit_malayalam_action_saves_the_latest_version(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Older Malayalam.');
        $latest = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Latest Malayalam.');

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->assertActionVisible(TestAction::make('editLatestMalayalam')->table())
            ->callAction(TestAction::make('editLatestMalayalam')->table(), [
                'editorial_content_id' => $latest->id,
                'title' => 'Latest corrected title',
                'summary' => 'Latest corrected summary',
                'body' => 'Latest corrected Malayalam biography.',
            ])
            ->assertHasNoActionErrors();

        $latest->refresh();
        $this->assertSame('Latest corrected title', $latest->title);
        $this->assertSame('Latest corrected Malayalam biography.', $latest->body);
        $this->assertSame(EditorialContent::LANGUAGE_ML, $latest->language);
    }

    public function test_header_edit_malayalam_stays_bound_when_a_newer_version_appears(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Older Malayalam.');
        $opened = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Opened Malayalam body.');

        $component = Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->mountAction(TestAction::make('editLatestMalayalam')->table())
            ->assertActionDataSet([
                'editorial_content_id' => $opened->id,
                'body' => 'Opened Malayalam body.',
            ]);

        $newer = $this->malayalam($profile, $english, 3, EditorialContent::STATUS_DRAFT, 'Newer Malayalam must not receive redirected edits.');

        $component
            ->setActionData([
                'editorial_content_id' => $opened->id,
                'title' => 'Bound corrected title',
                'summary' => 'Bound corrected summary',
                'body' => 'Bound corrected Malayalam biography.',
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $opened->refresh();
        $newer->refresh();

        $this->assertSame('Bound corrected title', $opened->title);
        $this->assertSame('Bound corrected Malayalam biography.', $opened->body);
        $this->assertSame(EditorialContent::LANGUAGE_ML, $opened->language);
        $this->assertSame($profile->id, $opened->profile_id);
        $this->assertSame('Newer Malayalam must not receive redirected edits.', $newer->body);
        $this->assertSame('Malayalam 3', $newer->title);
    }

    public function test_edit_form_cannot_alter_language_or_profile_through_payload(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $malayalam = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Malayalam body.');

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->callAction(
                TestAction::make('edit')->table($malayalam),
                [
                    'title' => 'Still Malayalam',
                    'summary' => 'Summary',
                    'body' => 'Body stays Malayalam for this profile.',
                    'language' => EditorialContent::LANGUAGE_EN,
                    'profile_id' => $profile->id + 999,
                ],
            );

        $malayalam->refresh();
        $this->assertSame(EditorialContent::LANGUAGE_ML, $malayalam->language);
        $this->assertSame($profile->id, $malayalam->profile_id);
        $this->assertSame('Still Malayalam', $malayalam->title);
    }

    public function test_editing_published_malayalam_creates_successor_without_moving_pointer(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $publishedMl = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Live Malayalam body.');
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $publishedMl->fresh());
        $publishedMl->refresh();
        $application->refresh();

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application,
                'pageClass' => ViewApplication::class,
            ])
            ->callAction(
                TestAction::make('edit')->table($publishedMl),
                [
                    'title' => 'Successor title',
                    'summary' => 'Successor summary',
                    'body' => 'Successor Malayalam body that must stay unpublished until publish.',
                ],
            );

        $publishedMl->refresh();
        $application->refresh();
        $successor = EditorialContent::query()
            ->where('profile_id', $profile->id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->where('version_number', 2)
            ->first();

        $this->assertNotNull($successor);
        $this->assertSame(EditorialContent::STATUS_DRAFT, $successor->status);
        $this->assertSame('Successor Malayalam body that must stay unpublished until publish.', $successor->body);
        $this->assertSame('Live Malayalam body.', $publishedMl->body);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $publishedMl->status);
        $this->assertSame($publishedMl->id, $application->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
    }

    public function test_approve_then_publish_uses_the_corrected_malayalam_version(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $first = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'First Malayalam.');
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $first->fresh());
        $application->refresh();

        $corrected = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Corrected Malayalam biography.');

        $component = Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application->fresh(),
                'pageClass' => ViewApplication::class,
            ]);

        $component
            ->callAction(TestAction::make('approve')->table($corrected))
            ->assertHasNoActionErrors();

        $corrected->refresh();
        $this->assertSame(EditorialContent::STATUS_APPROVED, $corrected->status);
        $this->assertSame($first->id, $application->fresh()->published_malayalam_editorial_content_id);

        $component
            ->callAction(TestAction::make('approveAndPublishMalayalam')->table(), [
                'editorial_content_id' => $corrected->id,
            ])
            ->assertHasNoActionErrors();

        $application->refresh();
        $english->refresh();
        $this->assertSame($corrected->id, $application->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->published_english_editorial_content_id);
        $this->assertSame('English title', $english->title);
        $this->assertSame('English published biography.', $english->body);
        $this->assertSame(EditorialContent::STATUS_APPROVED, $corrected->fresh()->status);
    }

    public function test_page_header_publish_defaults_to_latest_non_published_paired_version(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $live = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Live Malayalam.');
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $live->fresh());
        $corrected = $this->malayalam($profile, $english, 2, EditorialContent::STATUS_DRAFT, 'Corrected Malayalam for header publish.');

        $page = Livewire::actingAs($admin)
            ->test(ViewApplication::class, ['record' => $application->fresh()->getRouteKey()])
            ->assertActionVisible('publishMalayalam')
            ->mountAction('publishMalayalam');

        $page->assertActionDataSet([
            'editorial_content_id' => $corrected->id,
        ]);

        $page->callMountedAction();

        $this->assertSame($corrected->id, $application->fresh()->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);
    }

    public function test_publish_malayalam_still_enforces_pairing_and_language_safeguards(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $workflow = app(ApplicationWorkflowService::class);

        $unpaired = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'source_editorial_content_id' => null,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'Unpaired',
            'summary' => 'Unpaired',
            'body' => 'Unpaired Malayalam.',
            'created_by_id' => $admin->id,
        ]);

        try {
            $workflow->publishMalayalam($application->fresh(), $admin, $unpaired);
            $this->fail('Unpaired Malayalam must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Malayalam must be paired with the currently published English version.', $e->getMessage());
        }

        $this->assertNull($application->fresh()->published_malayalam_editorial_content_id);
        $this->assertSame($english->id, $application->fresh()->published_english_editorial_content_id);
    }

    public function test_cleared_malayalam_pointer_is_not_shown_as_published_after_english_replacement(): void
    {
        [$admin, $application, $profile, $english] = $this->seedPublishedEnglish();
        $oldMl = $this->malayalam($profile, $english, 1, EditorialContent::STATUS_DRAFT, 'Old published Malayalam.');
        app(ApplicationWorkflowService::class)->publishMalayalam($application->fresh(), $admin, $oldMl->fresh());

        EditorialContent::query()->create([
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
        app(ApplicationWorkflowService::class)->publish(
            $application->fresh(),
            $admin,
            false,
            'Replace the English biography offline.',
        );
        $application->refresh();

        $this->assertNull($application->published_malayalam_editorial_content_id);

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application->fresh(),
                'pageClass' => ViewApplication::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$oldMl])
            ->assertSeeText('Not published')
            ->assertDontSeeText('Paired with published EN');
    }

    public function test_ai_generation_still_creates_paired_malayalam_visible_in_the_tab(): void
    {
        [$admin, $application] = $this->seedApplicationWithProfile([
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'package_tier' => 'emerging',
            'source_method' => 'online_interview',
        ]);

        $run = app(EditorialGenerationService::class)->generateForApplication($application->fresh(), $admin);
        $this->assertTrue($run->isSuccessful());

        $malayalam = EditorialContent::query()
            ->where('profile_id', $application->fresh()->profile_id)
            ->where('language', EditorialContent::LANGUAGE_ML)
            ->first();
        $english = EditorialContent::query()
            ->where('profile_id', $application->fresh()->profile_id)
            ->where('language', EditorialContent::LANGUAGE_EN)
            ->first();

        $this->assertNotNull($malayalam);
        $this->assertNotNull($english);
        $this->assertSame($english->id, $malayalam->source_editorial_content_id);

        Livewire::actingAs($admin)
            ->test(MalayalamEditorialRelationManager::class, [
                'ownerRecord' => $application->fresh(),
                'pageClass' => ViewApplication::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$malayalam])
            ->assertCanNotSeeTableRecords([$english])
            ->assertActionVisible(TestAction::make('editLatestMalayalam')->table())
            ->assertSeeText('Edit');
    }

    /**
     * @return array{0: User, 1: Application}
     */
    private function seedApplicationWithProfile(array $applicationOverrides = []): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Malayalam Tab Subject',
            'display_name' => 'Malayalam Tab Subject',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application = Application::factory()->paid()->create(array_merge([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_IN_EDITORIAL_REVIEW,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
        ], $applicationOverrides));

        return [$admin, $application];
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile, 3: EditorialContent}
     */
    private function seedPublishedEnglish(): array
    {
        [$admin, $application] = $this->seedApplicationWithProfile();
        $profile = $application->profile;
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
            'Authorised offline publication for Malayalam tab tests.',
        );

        return [$admin, $published, $published->profile, $english->fresh()];
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

    private function seedOtherProfileMalayalam(User $admin): EditorialContent
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Other Profile',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Other English',
            'summary' => 'Other',
            'body' => 'Other English body.',
            'created_by_id' => $admin->id,
            'reviewed_by_id' => $admin->id,
        ]);

        return EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'source_editorial_content_id' => $english->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'Other Malayalam',
            'summary' => 'Other',
            'body' => 'Other Malayalam body qxotherml.',
            'created_by_id' => $admin->id,
        ]);
    }
}
