<?php

namespace Tests\Feature;

use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Filament\Resources\Applications\RelationManagers\MalayalamEditorialRelationManager;
use App\Filament\Resources\Applications\RelationManagers\ProfileMediaRelationManager;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationPaymentStateService;
use App\Support\OnlineInterviewCatalog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Complete customer review journey: questionnaire → editorial preparation →
 * English/Malayalam approval → preview release → dashboard notification →
 * customer review of both language versions and approved photographs →
 * customer approval → admin approval visibility → normal publication.
 *
 * Photographs of an unpublished profile are served only to the application's
 * own owner through the preview photo route.
 */
class CustomerReviewJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public', 'jannayaks.ai.image_enhancement.enabled' => false]);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_customer_review_journey_from_submission_to_publication(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'source_method' => 'online_interview',
            'package_tier' => 'distinguished',
            'full_name' => 'Journey Customer',
            'broad_location' => 'Kozhikode',
        ]);
        app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $admin, 'Journey verification.');

        /* 1. Questionnaire submission (the earlier, separate stage). */
        $answers = [];
        foreach (OnlineInterviewCatalog::idsForTier('distinguished') as $questionId) {
            $answers[$questionId] = 'Answer for '.$questionId;
        }
        $this->actingAs($member)->get(route('online-interview.show', $application))->assertOk();
        $this->actingAs($member)->patchJson(route('online-interview.save', $application), ['answers' => $answers])->assertOk();
        $this->actingAs($member)->post(route('online-interview.submit', $application), [
            'questionnaire_confirmed' => '1',
            'accuracy_confirmed' => '1',
        ])->assertRedirect(route('applications.show', $application));
        $application->refresh();
        $this->assertTrue($application->isInterviewSubmitted());
        $this->assertSame(Application::STATUS_AWAITING_EDITORIAL_REVIEW, $application->status);

        /* Photograph: uploaded by the customer, approved by the admin. */
        $this->actingAs($member)->post(route('applications.media.store', $application), [
            'photo' => UploadedFile::fake()->image('portrait.jpg', 400, 500),
        ])->assertRedirect(route('applications.media', $application));
        $application->refresh();
        $photo = MediaItem::query()->where('mediable_id', $application->profile_id)->sole();
        Livewire::actingAs($admin)
            ->test(ProfileMediaRelationManager::class, ['ownerRecord' => $application, 'pageClass' => ViewApplication::class])
            ->callAction(TestAction::make('approve')->table($photo), ['review_note' => null]);
        $this->assertSame(MediaItem::REVIEW_APPROVED, $photo->fresh()->review_status);

        /* 2-3. Editorial preparation and admin approval of both versions. */
        $profile = $application->profile;
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'Journey Customer',
            'summary' => 'English summary of the journey.',
            'body' => 'ENGLISH PREPARED BIOGRAPHY TEXT.',
            'source_material' => '',
            'ai_generated' => true,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'മലയാളം തലക്കെട്ട്',
            'summary' => '',
            'body' => 'മലയാളം ജീവചരിത്രം തയ്യാറാക്കിയത്.',
            'source_material' => '',
            'ai_generated' => true,
            'source_editorial_content_id' => $english->id,
        ]);
        $application->forceFill(['status' => Application::STATUS_IN_EDITORIAL_REVIEW])->save();

        Livewire::actingAs($admin)->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('approveEnglishMaster');
        Livewire::actingAs($admin)->test(MalayalamEditorialRelationManager::class, ['ownerRecord' => $application, 'pageClass' => ViewApplication::class])
            ->callAction(TestAction::make('approve')->table($malayalam));

        Livewire::actingAs($admin)->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('releaseCustomerPreview')
            ->assertNotified('Customer preview released');

        $application->refresh();
        $this->assertSame(Application::STATUS_EDITORIAL_APPROVED, $application->status);
        $this->assertNotNull($application->customer_preview_released_at);

        /* 4. Dashboard announces the ready profile. */
        $dashboard = $this->actingAs($member)->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('Your profile is ready for your review', false)
            ->assertSee('Review your finished profile when ready, request included revisions if needed, then approve for publication.', false)
            ->getContent();
        $this->assertStringContainsString(
            'href="'.route('applications.preview', $application).'">Review your profile →</a>',
            $dashboard,
        );

        /* 5. The preview shows both language versions and approved photographs. */
        $preview = $this->actingAs($member)->get(route('applications.preview', $application))
            ->assertOk()->getContent();
        $this->assertStringContainsString('ENGLISH PREPARED BIOGRAPHY TEXT.', $preview);
        $this->assertStringContainsString('English summary of the journey.', $preview);
        $this->assertStringContainsString('മലയാളം ജീവചരിത്രം തയ്യാറാക്കിയത്.', $preview);
        $this->assertStringContainsString('മലയാളം തലക്കെട്ട്', $preview);
        $this->assertStringContainsString('Approve this profile', $preview);
        $this->assertStringContainsString('src="'.route('applications.preview.photo', [$application, $photo]).'"', $preview);
        $this->actingAs($member)->get(route('applications.preview.photo', [$application, $photo]))->assertOk();

        /* 6. Customer approves; admin sees it; normal publication succeeds. */
        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect(route('applications.preview', $application))->assertSessionHasNoErrors();

        Livewire::actingAs($admin)->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->assertSee('Customer approval received')
            ->assertSee('Ready — Publish uses this customer approval')
            ->assertActionEnabled('publishApplication')
            ->callAction('publishApplication')
            ->assertNotified('Profile published');

        $application->refresh();
        $this->assertSame(Application::STATUS_PUBLISHED, $application->status);
        $this->assertSame($english->id, (int) $application->published_english_editorial_content_id);
    }

    public function test_preview_shows_only_approved_photographs_served_to_the_owner(): void
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);
        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $application = Application::factory()->for($member)->create([
            'source_method' => 'online_interview',
            'package_tier' => 'distinguished',
        ]);
        app(ApplicationPaymentStateService::class)->recordStaffWaiver($application, $admin, 'QA.');
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Photo Preview',
            'display_name' => 'Photo Preview',
            'profession' => '',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $application->forceFill(['profile_id' => $profile->id])->save();

        $service = app(\App\Services\ProfileMediaService::class);
        $approved = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('approved.jpg', 400, 500), $member);
        $pending = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('pending.jpg', 400, 500), $member);
        $rejected = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('rejected.jpg', 400, 500), $member);
        $service->approve($approved, $admin);
        $service->reject($rejected, $admin, 'Not suitable.');

        // Release the preview (English master must be approved first).
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'status' => 'draft',
            'version_number' => 1,
            'title' => 'Photo Preview',
            'body' => 'Preview body.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => true,
        ]);
        Livewire::actingAs($admin)->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('approveEnglishMaster');
        Livewire::actingAs($admin)->test(ViewApplication::class, ['record' => $application->getRouteKey()])
            ->callAction('releaseCustomerPreview');
        $this->assertNotNull($application->fresh()->customer_preview_released_at);

        $preview = $this->actingAs($member)->get(route('applications.preview', $application))->assertOk()->getContent();
        $this->assertStringContainsString(
            'src="'.route('applications.preview.photo', [$application, $approved]).'"',
            $preview,
            'The approved photograph appears in the preview.',
        );
        foreach ([$pending, $rejected] as $hidden) {
            $this->assertStringNotContainsString(
                route('applications.preview.photo', [$application, $hidden]),
                $preview,
                'Pending and rejected photographs must not be referenced.',
            );
        }

        // The owner can stream the approved photograph; content is the real bytes.
        $photoResponse = $this->actingAs($member)->get(route('applications.preview.photo', [$application, $approved]));
        $photoResponse->assertOk();
        $this->assertStringContainsString('image/', (string) $photoResponse->headers->get('Content-Type'));

        // Another authenticated customer is refused the preview and gets 404 for the photo.
        $this->actingAs($stranger)->get(route('applications.preview', $application))->assertForbidden();
        $this->actingAs($stranger)->get(route('applications.preview.photo', [$application, $approved]))->assertNotFound();

        // Guests get 404 too (never a redirect that confirms existence).
        $this->get(route('applications.preview.photo', [$application, $approved]))->assertNotFound();

        // Pending and rejected photographs are refused even for the owner.
        foreach ([$pending, $rejected] as $hidden) {
            $this->actingAs($member)->get(route('applications.preview.photo', [$application, $hidden]))->assertNotFound();
        }

        // Manipulated identifiers are refused.
        $this->actingAs($stranger)->get(route('applications.preview.photo', [$application, $approved]))->assertNotFound();
        $this->actingAs($member)->get(route('applications.preview.photo', [$application, 999999]))->assertNotFound();

        // After publication the public route serves it as before, and the owner's
        // preview (which remains available after publication) still shows it.
        $application->forceFill(['status' => Application::STATUS_PUBLISHED])->save();
        $profile->forceFill(['status' => 'published', 'published_at' => now(), 'slug' => 'photo.preview', 'unpublished_at' => null])->save();
        $this->actingAs($member)->get(route('applications.preview.photo', [$application, $approved]))->assertOk();
        $this->actingAs($stranger)->get(route('applications.preview.photo', [$application, $approved]))->assertNotFound();
        $this->get(route('profiles.public.photo', [$profile, $approved->fresh()]))->assertOk();
    }
}
