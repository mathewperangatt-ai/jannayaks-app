<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\CustomerEditorialWorkflowService;
use App\Services\ProfileMediaService;
use App\Services\PublicProfilePresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 2: the customer preview is the ACTUAL publication-ready profile
 * presentation — identical markup, typography, section order, content and
 * photographs — with preview notices and approval controls placed outside
 * the presentation. Approval covers the exact editorial versions AND the
 * presentation; a changed presentation requires renewed approval before
 * staff publication.
 */
class CustomerPreviewPublishedParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        ini_set('memory_limit', '512M');
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
        Storage::fake('private_uploads');
    }

    public function test_preview_renders_the_publication_ready_presentation(): void
    {
        [$admin, $member, $application, $english, $profile, $photo] = $this->releasedPreview();

        $content = $this->actingAs($member)
            ->get(route('applications.preview', $application))
            ->assertOk()
            ->getContent();

        // The shared publication presentation markup...
        $this->assertStringContainsString('class="jk-page', $content);
        $this->assertStringContainsString('jk-name', $content);
        $this->assertStringContainsString('id="biography"', $content);
        $this->assertStringContainsString('jk-bodytext', $content);
        $this->assertStringContainsString('ENGLISH BODY FIRST PARAGRAPH.', $content);
        $this->assertStringContainsString('English summary of the journey.', $content);
        // ...with the preview banner outside the presentation...
        $this->assertStringContainsString('Customer preview', $content);
        // ...photographs streaming through the owner-only preview route...
        $this->assertStringContainsString('src="'.route('applications.preview.photo', [$application, $photo->fresh()]).'"', $content);
        $this->assertNotIn('profiles.public.photo', $content);
        // ...and no public engagement rail inside the preview.
        $this->assertNotIn('Show appreciation', $content);
        $this->assertNotIn('Send message', $content);
        // Approval controls stay available.
        $this->assertStringContainsString('Approve this profile', $content);
    }

    public function test_preview_notices_and_controls_sit_outside_the_profile_presentation(): void
    {
        [, $member, $application] = $this->releasedPreview();

        $content = $this->actingAs($member)
            ->get(route('applications.preview', $application))
            ->assertOk()
            ->getContent();

        $bannerAt = strpos($content, 'Customer preview');
        $presentationAt = strpos($content, 'class="jk-page');
        $presentationEndAt = strpos((string) $content, 'Review and approval');

        $this->assertNotFalse($bannerAt);
        $this->assertNotFalse($presentationAt);
        $this->assertNotFalse($presentationEndAt);
        $this->assertTrue($bannerAt < $presentationAt, 'The preview notice precedes the profile presentation.');
        $this->assertTrue($presentationEndAt < $bannerAt + strlen($content) && $presentationEndAt > $presentationAt, 'Approval controls follow the profile presentation.');
    }

    public function test_published_page_shares_the_presentation_markup_with_the_preview(): void
    {
        [$admin, $member, $application, $english, $profile] = $this->releasedPreview();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
        $profile = $profile->fresh();
        $this->assertNotNull($profile->slug);

        $published = $this->get('/'.$profile->slug)->assertOk()->getContent();
        $preview = $this->actingAs($member)
            ->get(route('applications.preview', $application))
            ->assertOk()
            ->getContent();

        foreach (['class="jk-page', 'jk-name', 'id="biography"', 'jk-bodytext', 'ENGLISH BODY FIRST PARAGRAPH.'] as $marker) {
            $this->assertStringContainsString($marker, $published, 'Published page: '.$marker);
            $this->assertStringContainsString($marker, $preview, 'Preview: '.$marker);
        }

        // The engagement rail belongs to the published page only.
        $this->assertStringContainsString('Show appreciation', $published);
        $this->assertNotIn('Show appreciation', $preview);
    }

    public function test_preview_language_toggle_matches_the_published_toggle(): void
    {
        [$admin, $member, $application, $english] = $this->releasedPreview();

        $previewEn = $this->actingAs($member)
            ->get(route('applications.preview', $application))
            ->assertOk()->getContent();
        $previewMl = $this->actingAs($member)
            ->get(route('applications.preview', ['application' => $application, 'lang' => 'ml']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('ENGLISH BODY FIRST PARAGRAPH.', $previewEn);
        $this->assertStringContainsString('മലയാളം ജീവചരിത്രം തയ്യാറാക്കിയത്.', $previewMl);
        // The toggle itself uses the preview route, never the public route.
        $this->assertStringContainsString('lang="ml"', $previewEn);
        $this->assertNotIn('profiles.public', $previewEn);
    }

    public function test_approval_records_the_presentation_fingerprint_and_publish_succeeds(): void
    {
        [$admin, $member, $application, $english, $profile] = $this->releasedPreview();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $approval = \App\Models\EditorialCustomerApproval::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->firstOrFail();

        $expected = app(PublicProfilePresentationService::class)->presentationFingerprint(
            $profile->fresh(),
            $english,
            $approval->malayalam_editorial_content_id !== null
                ? EditorialContent::query()->find($approval->malayalam_editorial_content_id)
                : null,
        );
        $this->assertSame($expected, $approval->presentation_fingerprint, 'The approval pins the exact presentation.');

        $state = app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh());
        $this->assertSame(false, $state['presentation_current'], 'Unchanged presentation is reported as not-changed.');
        $this->assertTrue($state['ready']);

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_presentation_change_after_approval_requires_renewed_approval(): void
    {
        [$admin, $member, $application, $english, $profile] = $this->releasedPreview();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // A photograph added and approved AFTER the approval changes the
        // publication-ready presentation the customer signed off on.
        $editor = User::factory()->editor()->create(['email_verified_at' => now()]);
        $media = app(ProfileMediaService::class);
        $second = $media->uploadProfilePhoto(
            profile: $profile->fresh(),
            file: UploadedFile::fake()->image('second.jpg', 400, 500),
            actor: $editor,
        );
        $media->approve($second->fresh(), $admin);

        $state = app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh());
        $this->assertFalse($state['ready'], 'Publication is blocked while the presentation differs from the approval.');
        $this->assertStringContainsString('presentation changed after customer approval', $state['reason']);

        try {
            app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
            $this->fail('Publishing must refuse a changed presentation without renewed approval.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('presentation changed after customer approval', $e->getMessage());
        }

        // Renewed approval on a freshly released preview publishes normally.
        $workflow = app(CustomerEditorialWorkflowService::class);
        $workflow->releaseForCustomerPreview($application->fresh(), $admin);
        $this->actingAs($member)->post(route('applications.preview.approve', $application->fresh()), [
            'english_editorial_content_id' => $application->fresh()->preview_english_editorial_content_id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    public function test_approval_without_a_fingerprint_keeps_publishing_behaviour(): void
    {
        [$admin, $member, $application, $english] = $this->releasedPreview();

        $this->actingAs($member)->post(route('applications.preview.approve', $application), [
            'english_editorial_content_id' => $english->id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        // Approvals recorded before fingerprints existed carry null and are
        // never blocked by the presentation check.
        \App\Models\EditorialCustomerApproval::query()
            ->where('application_id', $application->id)
            ->whereNull('invalidated_at')
            ->update(['presentation_fingerprint' => null]);

        $state = app(ApplicationWorkflowService::class)->customerApprovalState($application->fresh());
        $this->assertNull($state['presentation_current']);
        $this->assertTrue($state['ready']);

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);
        $this->assertSame(Application::STATUS_PUBLISHED, $application->fresh()->status);
    }

    private function assertNotIn(string $needle, string $haystack): void
    {
        $this->assertStringNotContainsString($needle, $haystack);
    }

    /** @return array{0:User,1:User,2:Application,3:EditorialContent,4:Profile,5:\App\Models\MediaItem} */
    private function releasedPreview(): array
    {
        $admin = User::factory()->admin()->create(['email_verified_at' => now()]);
        $member = User::factory()->create(['email_verified_at' => now()]);

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Preview Parity Subject',
            'display_name' => 'Preview Parity Subject',
            'profession' => 'Social worker',
            'bio_headline' => 'Two decades of community service',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'version_number' => 1,
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $admin->id,
            'title' => 'Preview Parity Subject',
            'summary' => 'English summary of the journey.',
            'body' => "ENGLISH BODY FIRST PARAGRAPH.\n\nSecond paragraph of the publication-ready biography.",
        ]);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'version_number' => 1,
            'status' => EditorialContent::STATUS_APPROVED,
            'reviewed_by_id' => $admin->id,
            'source_editorial_content_id' => $english->id,
            'title' => 'മലയാളം തലക്കെട്ട്',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'body' => 'മലയാളം ജീവചരിത്രം തയ്യാറാക്കിയത്.',
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

        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application, $admin);

        $media = app(ProfileMediaService::class);
        $photo = $media->uploadProfilePhoto(
            profile: $profile->fresh(),
            file: UploadedFile::fake()->image('portrait.jpg', 400, 500),
            actor: $member,
        );
        $media->approve($photo->fresh(), $admin);

        return [$admin, $member, $application->fresh(), $english->fresh(), $profile->fresh(), $photo];
    }
}
