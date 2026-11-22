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
use Tests\TestCase;

/**
 * Public profile photograph presentation (inline-only): photographs appear
 * INSIDE the biography article flow at natural paragraph breaks — never in
 * the header, a detached gallery, a grid or a sidebar. Every photograph
 * opens an enlarged view. Primary photograph leads; duplicates never render.
 */
class PublicProfilePhotoLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_single_photograph_renders_inline_without_header_or_gallery(): void
    {
        [$profile] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        // The single photograph appears inline inside the biography flow.
        $this->assertMatchesRegularExpression(
            '#<figure class="jk-inline-photo">.*?photo/'.$this->photoIds($profile)[0].'#s',
            $html,
        );
        // No header portrait, no mono fallback, no gallery, no sidebar photo.
        $this->assertStringNotContainsString('jk-portrait', $html);
        $this->assertStringNotContainsString('id="gallery"', $html);
        $this->assertStringNotContainsString('jk-side-feature', $html);
    }

    public function test_multiple_photographs_flow_inline_in_order_with_click_to_enlarge(): void
    {
        [$profile, $photos] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
            ['name' => 'landscape.jpg', 'width' => 800, 'height' => 500, 'primary' => false],
        ]);

        $ordered = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());
        $this->assertCount(2, $ordered);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        // Every photograph renders exactly once, in placement order
        // (primary first), each wrapped in an enlarged-view link.
        $positions = [];
        foreach ($ordered as $photo) {
            $src = route('profiles.public.photo', [$profile, $photo]);
            $this->assertSame(1, substr_count($html, 'src="'.$src.'"'), 'photo '.$photo->id.' must render exactly once.');
            $positions[] = strpos($html, $src);
            $this->assertStringContainsString(
                '<a href="'.$src.'" target="_blank" rel="noopener"',
                $html,
                'Inline photographs open an enlarged view.',
            );
        }
        $this->assertTrue($positions[0] < $positions[1], 'Primary photograph renders before the second.');
    }

    public function test_photographs_sit_at_natural_paragraph_breaks_inside_the_article(): void
    {
        [$profile, $photos] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
            ['name' => 'activity.jpg', 'width' => 800, 'height' => 500, 'primary' => false],
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        // Paragraph 1, then a photograph, then paragraph 2 — photographs live
        // between paragraphs, never inside a sentence or detached panel.
        $paragraph1Pos = strpos($html, 'First paragraph of the public biography.');
        $paragraph2Pos = strpos($html, 'Second paragraph continues the record.');
        $figure1Pos = strpos($html, '<figure class="jk-inline-photo">');

        $this->assertTrue($paragraph1Pos !== false && $paragraph2Pos !== false && $figure1Pos !== false);
        $this->assertTrue($paragraph1Pos < $figure1Pos && $figure1Pos < $paragraph2Pos);
    }

    public function test_no_photo_appears_in_the_header(): void
    {
        [$profile, $photos] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        // Slice from the rendered <body>: the <head> carries the og:image
        // meta tag legitimately, which is metadata — not a displayed photo.
        $bodyStart = strpos($html, '<body>');
        $headerEnd = strpos((string) $html, 'id="biography"');
        $headerHtml = substr((string) $html, (int) $bodyStart, (int) $headerEnd - (int) $bodyStart);

        $this->assertStringNotContainsString('jk-portrait', $headerHtml);
        $this->assertStringNotContainsString('photo/', $headerHtml);
    }

    public function test_demonstration_label_and_disclosure_still_render(): void
    {
        [$profile] = $this->publishWithPhotos([], demoOwner: true);

        $html = $this->get(route('profiles.public', $profile->slug).'?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('DEMO — Fictional Profile', $html);
        $this->assertStringContainsString('The photographs on this page are AI-generated illustrations', $html);
    }

    /**
     * Publish a profile with the given photographs through the real
     * workflow. Returns [profile, photos(with the application attached)].
     */
    private function photoIds($profile): array
    {
        return app(PublicProfilePresentationService::class)
            ->publicProfilePhotos($profile->fresh())
            ->pluck('id')
            ->all();
    }

    private function publishWithPhotos(array $specs, bool $demoOwner = false): array
    {
        $member = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'email' => $demoOwner
                ? 'demo.photo.layout.'.uniqid().'@jannayaks.internal'
                : 'member.photo.layout.'.uniqid().'@example.com',
        ]);
        $editor = User::factory()->editor()->create();
        $reviewer = User::factory()->editor()->create();
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'member_approved',
            'full_name' => 'Photo Layout Leader',
            'display_name' => 'Photo Layout Leader',
            'profession' => 'Organiser',
            'slug' => 'photo-layout-'.uniqid(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->historical('accomplished')->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => $profile->display_name,
            'body' => "First paragraph of the public biography.\n\nSecond paragraph continues the record.",
            'summary' => 'A short public summary.',
        ]);

        $workflow = app(ApplicationWorkflowService::class);
        $workflow->approveEnglishMaster($application->fresh(), $admin);
        app(CustomerEditorialWorkflowService::class)->releaseForCustomerPreview($application->fresh(), $admin);

        // Photographs upload BEFORE the customer approval: after approval the
        // member is locked out of photo changes by design.
        $media = app(ProfileMediaService::class);
        $photos = collect();
        foreach ($specs as $i => $spec) {
            $photo = $media->uploadProfilePhoto(
                profile: $profile->fresh(),
                file: UploadedFile::fake()->image($spec['name'], $spec['width'], $spec['height']),
                actor: $member,
                altText: 'Photo '.($i + 1).' of '.$profile->display_name,
                makePrimary: (bool) $spec['primary'],
            );
            $media->approve($photo->fresh(), $editor);
            $photos->push($photo->fresh());
        }

        $this->actingAs($member)->post(route('applications.preview.approve', $application->fresh()), [
            'english_editorial_content_id' => $application->fresh()->preview_english_editorial_content_id,
            'confirm_approval' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();
        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin);

        return [$profile->fresh(), $english->fresh(), $photos, $application->fresh()];
    }
}
