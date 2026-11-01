<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileMediaService;
use App\Services\PublicProfilePresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public profile photograph layout: single-photo reuse must not cover-crop;
 * multi-photo gallery placement remains available for additional images.
 */
class PublicProfilePhotoLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_single_photograph_side_feature_uses_whole_image_contain(): void
    {
        [$profile] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '#class="jk-side-feature jk-portrait--whole"#',
            $html,
            'Single-photo biography side feature must use contain (jk-portrait--whole).'
        );
        $this->assertDoesNotMatchRegularExpression(
            '#class="jk-side-feature"(?! jk-portrait--whole)#',
            $html
        );
        $this->assertStringNotContainsString('id="gallery"', $html);
        $this->assertMatchesRegularExpression('#class="jk-portrait"(?! jk-portrait--whole)#', $html);
    }

    public function test_two_photographs_keep_cover_side_feature_and_show_gallery(): void
    {
        [$profile] = $this->publishWithPhotos([
            ['name' => 'portrait.jpg', 'width' => 400, 'height' => 600, 'primary' => true],
            ['name' => 'landscape.jpg', 'width' => 800, 'height' => 500, 'primary' => false],
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
        $ordered = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());
        $this->assertCount(2, $ordered);

        $this->assertMatchesRegularExpression('#class="jk-side-feature"(?! jk-portrait--whole)#', $html);
        $this->assertStringContainsString('id="gallery"', $html);
        $this->assertStringContainsString(
            'href="'.route('profiles.public.photo', [$profile, $ordered->skip(1)->first()]).'"',
            $html
        );
    }

    public function test_three_and_four_photographs_adapt_gallery_without_changing_side_feature_rule(): void
    {
        foreach ([3, 4] as $count) {
            $specs = [];
            for ($i = 0; $i < $count; $i++) {
                $portrait = $i % 2 === 0;
                $specs[] = [
                    'name' => ($portrait ? 'p' : 'l').$i.'.jpg',
                    'width' => $portrait ? 420 : 900,
                    'height' => $portrait ? 640 : 520,
                    'primary' => $i === 0,
                ];
            }

            [$profile] = $this->publishWithPhotos($specs);
            $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
            $ordered = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());
            $this->assertCount($count, $ordered);

            $this->assertMatchesRegularExpression(
                '#class="jk-side-feature"(?! jk-portrait--whole)#',
                $html,
                "Expected cover side feature when {$count} photos are present."
            );
            $this->assertStringContainsString('id="gallery"', $html);

            foreach ($ordered->skip(1) as $extra) {
                $this->assertStringContainsString(
                    'href="'.route('profiles.public.photo', [$profile, $extra]).'"',
                    $html
                );
            }
        }
    }

    public function test_demonstration_profiles_still_force_whole_portraits(): void
    {
        [$profile] = $this->publishWithPhotos([
            ['name' => 'demo.jpg', 'width' => 500, 'height' => 500, 'primary' => true],
        ], demoOwner: true);

        $html = $this->get(route('profiles.public', $profile->slug).'?lang=en')->assertOk()->getContent();

        $this->assertStringContainsString('jk-portrait jk-portrait--whole', $html);
        $this->assertStringContainsString('jk-side-feature jk-portrait--whole', $html);
    }

    /**
     * @param  list<array{name: string, width: int, height: int, primary: bool}>  $specs
     * @return array{0: Profile, 1: list<MediaItem>}
     */
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
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $profile->display_name,
            'body' => "First paragraph of the public biography.\n\nSecond paragraph continues the record.",
            'summary' => 'A short public summary.',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $media = app(ProfileMediaService::class);
        $photos = [];
        foreach ($specs as $index => $spec) {
            $uploaded = UploadedFile::fake()->image($spec['name'], $spec['width'], $spec['height']);
            $item = $media->uploadProfilePhoto($profile->fresh(), $uploaded, $editor);
            $media->approve($item, $reviewer);
            $photos[] = $item->fresh();
        }

        // Explicit primary + display order after all uploads (upload may reassign primary).
        foreach ($photos as $index => $item) {
            $item->forceFill([
                'is_primary' => (bool) $specs[$index]['primary'],
                'display_order' => $index + 1,
            ])->save();
        }
        // Ensure a single primary when specs mark one.
        $primary = collect($photos)->first(fn (MediaItem $item, int $i): bool => (bool) $specs[$i]['primary'])
            ?? $photos[0];
        MediaItem::query()
            ->where('mediable_type', $profile->getMorphClass())
            ->where('mediable_id', $profile->id)
            ->whereKeyNot($primary->id)
            ->update(['is_primary' => false]);
        $primary->forceFill(['is_primary' => true])->save();

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);

        return [$profile->fresh(), $photos];
    }
}
