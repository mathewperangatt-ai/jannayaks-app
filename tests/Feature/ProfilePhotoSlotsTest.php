<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\User;
use App\Services\ProfileMediaService;
use App\Services\PublicProfilePresentationService;
use App\Support\ProfileEntitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfilePhotoSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jannayaks.media.public_disk' => 'public']);
        Storage::fake('public');
    }

    public function test_standard_profile_shows_one_primary_and_three_additional_slots(): void
    {
        [$member, $application] = $this->makeApplication(standard: true);

        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('0 of 4 photographs uploaded; 4 slots available.', false)
            ->assertSee('One primary photograph and up to 3 additional photographs.', false)
            ->assertSee('Primary photograph', false)
            ->assertSee('Additional photograph 1', false)
            ->assertSee('Additional photograph 2', false)
            ->assertSee('Additional photograph 3', false)
            ->assertDontSee('Additional photograph 4', false)
            ->assertSee('Empty slot', false);

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('one.jpg', 400, 500),
            ])
            ->assertRedirect(route('applications.media', $application));

        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('1 of 4 photographs uploaded; 3 slots available.', false)
            ->assertSee('Pending review', false)
            ->assertSee('Primary photograph', false);

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photos' => [
                    UploadedFile::fake()->image('two.jpg', 400, 500),
                    UploadedFile::fake()->image('three.jpg', 400, 500),
                    UploadedFile::fake()->image('four.jpg', 400, 500),
                    UploadedFile::fake()->image('five.jpg', 400, 500),
                ],
            ])
            ->assertRedirect(route('applications.media', $application))
            ->assertSessionHasErrors('photo');

        $this->assertSame(4, app(ProfileMediaService::class)->profilePhotoCount($application->profile));
        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('4 of 4 photographs uploaded; 0 slots available.', false);

        $this->actingAs($member)
            ->post(route('applications.media.store', $application), [
                'photo' => UploadedFile::fake()->image('sixth.jpg', 400, 500),
            ])
            ->assertSessionHasErrors('photo');

        $this->assertSame(4, MediaItem::query()->where('mediable_id', $application->profile_id)->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)->count());
    }

    public function test_every_living_profile_has_the_same_four_photograph_limit(): void
    {
        foreach (['emerging', 'accomplished', 'distinguished'] as $tier) {
            [, $application] = $this->makeApplication(standard: false, tier: $tier);

            $this->assertSame(4, ProfileEntitlements::photoSlots($application));
            $this->assertSame(4, (int) config('jannayaks.tier_pricing.packages.'.$tier.'.photo_slots'));
        }

        [, $standard] = $this->makeApplication(standard: true);
        $this->assertSame(4, ProfileEntitlements::photoSlots($standard));
    }

    public function test_pending_and_rejected_photographs_stay_off_the_public_profile(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: true);
        $service = app(ProfileMediaService::class);
        $editor = User::factory()->editor()->create();

        $pending = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('pending.jpg', 400, 500), $member);
        $approved = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('approved.jpg', 400, 520), $member);
        $rejected = $service->uploadProfilePhoto($profile, UploadedFile::fake()->image('rejected.jpg', 400, 500), $member);
        $service->setPrimary($profile, $approved, $member);
        $service->approve($approved, $editor);
        $rejected->forceFill(['review_status' => MediaItem::REVIEW_REJECTED, 'is_primary' => false])->save();

        $photos = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());

        $this->assertCount(1, $photos);
        $this->assertSame($approved->id, $photos->first()->id);
        $this->assertTrue((bool) $photos->first()->is_primary);
        $this->assertNotContains($pending->id, $photos->pluck('id'));
        $this->assertNotContains($rejected->id, $photos->pluck('id'));
    }

    public function test_owner_preview_is_private_to_the_customer_photograph(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: true);
        $photo = app(ProfileMediaService::class)->uploadProfilePhoto(
            $profile,
            UploadedFile::fake()->image('owner.jpg', 400, 500),
            $member,
        );
        $other = User::factory()->create();

        $preview = $this->actingAs($member)
            ->get(route('applications.media.preview', [$application, $photo]))
            ->assertOk();
        $cache = (string) $preview->headers->get('cache-control');
        $this->assertStringContainsString('private', $cache);
        $this->assertStringContainsString('no-store', $cache);

        $this->actingAs($other)
            ->get(route('applications.media.preview', [$application, $photo]))
            ->assertForbidden();
    }

    public function test_public_profile_shows_only_eligible_photographs_from_zero_through_four(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: true);
        $profile->forceFill([
            'slug' => 'slot.public.tester',
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();

        $this->storePhoto($profile, $member, 'pending-secret-alt', MediaItem::REVIEW_PENDING, MediaItem::PRIVACY_PRIVATE);
        $this->storePhoto($profile, $member, 'rejected-secret-alt', MediaItem::REVIEW_REJECTED, MediaItem::PRIVACY_PRIVATE);
        $this->storePhoto($profile, $member, 'private-secret-alt', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PRIVATE);
        $this->storePhoto($profile, $member, 'enhancement-secret-alt', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, MediaItem::TYPE_PROFILE_PHOTO_ENHANCEMENT);

        foreach ([0, 1, 2, 3, 4] as $eligible) {
            if ($eligible > 0) {
                $this->storePhoto(
                    $profile,
                    $member,
                    'public-secret-alt-'.$eligible,
                    MediaItem::REVIEW_APPROVED,
                    MediaItem::PRIVACY_PUBLIC,
                    MediaItem::TYPE_PROFILE_PHOTO,
                    $eligible === 1,
                    $eligible,
                );
            }

            $response = $this->get('/slot.public.tester')->assertOk();
            $cache = (string) $response->headers->get('cache-control');
            $this->assertStringContainsString('public', $cache);
            $this->assertStringContainsString('max-age=60', $cache);
            $html = $response->getContent();
            $this->assertStringContainsString('rel="canonical"', $html);
            $this->assertStringContainsString('honey_bot', $html);
            $this->assertStringNotContainsString('Empty slot', $html);
            $this->assertStringNotContainsString('photo-placeholder', $html);
            $this->assertStringNotContainsString('Upload photograph', $html);
            $this->assertStringNotContainsString('pending-secret-alt', $html);
            $this->assertStringNotContainsString('rejected-secret-alt', $html);
            $this->assertStringNotContainsString('private-secret-alt', $html);
            $this->assertStringNotContainsString('enhancement-secret-alt', $html);
            $this->assertStringNotContainsString('src=""', $html);
            $this->assertSame(0, substr_count($html, 'class="jk-side-slot"'));
            $this->assertSame(0, substr_count($html, '<div class="jk-gallery-slot"'));

            $additional = max(0, $eligible - 1);
            $this->assertSame($additional, substr_count($html, 'loading="lazy"'));
            $this->assertSame($additional === 0 ? 0 : 1, substr_count($html, 'id="gallery"'));
            for ($position = 1; $position <= 3; $position++) {
                if ($position <= $additional) {
                    $this->assertStringContainsString('data-photo-position="additional-'.$position.'"', $html);
                } else {
                    $this->assertStringNotContainsString('data-photo-position="additional-'.$position.'"', $html);
                }
            }

            if ($eligible === 0) {
                $this->assertStringContainsString('jk-portrait-mono', $html);
                $this->assertSame(0, substr_count($html, 'class="jk-portrait"'));
            } else {
                $this->assertSame(1, substr_count($html, 'class="jk-portrait"'));
                $this->assertStringContainsString('public-secret-alt-1', $html);
                for ($extra = 2; $extra <= $eligible; $extra++) {
                    $this->assertStringContainsString('public-secret-alt-'.$extra, $html);
                }
            }
        }

        $this->assertSame($member->id, (int) $application->user_id);
    }

    public function test_a_retained_original_does_not_consume_a_second_customer_slot(): void
    {
        [$member, , $profile] = $this->makeApplication(standard: true);
        $original = $this->storePhoto($profile, $member, 'retained-original', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PRIVATE);
        $replacement = $this->storePhoto(
            $profile,
            $member,
            'accepted-replacement',
            MediaItem::REVIEW_APPROVED,
            MediaItem::PRIVACY_PUBLIC,
            primary: true,
            order: 2,
        );
        $replacement->forceFill(['enhanced_from_media_id' => $original->id])->save();
        $this->storePhoto($profile, $member, 'still-pending', MediaItem::REVIEW_PENDING, MediaItem::PRIVACY_PRIVATE, order: 3);

        $this->assertSame(2, app(ProfileMediaService::class)->profilePhotoCount($profile->fresh()));
    }

    public function test_magazine_layout_places_the_primary_and_only_real_additional_photographs(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: true);
        $profile->forceFill([
            'slug' => 'magazine.layout',
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Slot Tester',
            'summary' => 'English magazine summary.',
            'body' => "The opening English paragraph.\n\nThe second English paragraph.\n\nThe third English paragraph.\n\nThe fourth English paragraph.",
            'source_material' => '',
            'ai_generated' => false,
        ]);
        $malayalam = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'ml',
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'സ്ലോട്ട് ടെസ്റ്റർ',
            'summary' => 'മലയാളം സംഗ്രഹം.',
            'body' => "ആദ്യ മലയാളം ഖണ്ഡിക.\n\nരണ്ടാം മലയാളം ഖണ്ഡിക.\n\nമൂന്നാം മലയാളം ഖണ്ഡിക.\n\nനാലാം മലയാളം ഖണ്ഡിക.",
            'source_material' => '',
            'source_editorial_content_id' => $english->id,
            'ai_generated' => false,
        ]);
        $application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'published_english_editorial_content_id' => $english->id,
            'published_malayalam_editorial_content_id' => $malayalam->id,
        ])->save();

        $this->storePhoto($profile, $member, 'pending-secret-alt', MediaItem::REVIEW_PENDING, MediaItem::PRIVACY_PRIVATE, order: 9);

        $primary = null;
        foreach ([1, 2, 3, 4] as $count) {
            $photo = $this->storePhoto(
                $profile,
                $member,
                'magazine-alt-'.$count,
                MediaItem::REVIEW_APPROVED,
                MediaItem::PRIVACY_PUBLIC,
                primary: $count === 1,
                order: $count,
            );
            if ($count === 1) {
                $primary = $photo;
            }

            foreach (['en', 'ml'] as $language) {
                $html = $this->get('/magazine.layout?lang='.$language)->assertOk()->getContent();
                $this->assertStringContainsString('object-fit:cover', str_replace(' ', '', $html));
                $this->assertStringContainsString('object-fit:contain', str_replace(' ', '', $html));
                $this->assertStringContainsString('@media(max-width:720px)', str_replace(' ', '', $html));
                $this->assertStringNotContainsString('src=""', $html);
                $this->assertStringNotContainsString('pending-secret-alt', $html);
                $this->assertStringNotContainsString('Empty slot', $html);
                $this->assertSame(1, substr_count($html, 'class="jk-portrait"'));
                $this->assertSame(1, substr_count($html, 'magazine-alt-1'));
                $paragraphs = $language === 'ml'
                    ? ['ആദ്യ മലയാളം ഖണ്ഡിക.', 'രണ്ടാം മലയാളം ഖണ്ഡിക.', 'മൂന്നാം മലയാളം ഖണ്ഡിക.', 'നാലാം മലയാളം ഖണ്ഡിക.']
                    : ['The opening English paragraph.', 'The second English paragraph.', 'The third English paragraph.', 'The fourth English paragraph.'];
                $summary = $language === 'ml' ? 'മലയാളം സംഗ്രഹം.' : 'English magazine summary.';
                foreach ($paragraphs as $paragraph) {
                    $this->assertSame(1, substr_count($html, $paragraph), $language.' '.$count.' '.$paragraph);
                }
                $this->assertSame(1, substr_count($html, '<p class="jk-intro">'.$summary.'</p>'));
                $this->assertSame(1, substr_count($html, '<div class="jk-summary-quote"><p>'.$summary.'</p>'));
                $this->assertSame(1, substr_count($html, 'class="jk-summary-quote"'));
                $this->assertSame(0, substr_count($html, 'class="jk-side-slot"'));
                $this->assertSame(0, substr_count($html, '<div class="jk-gallery-slot"'));

                if ($language === 'ml') {
                    $this->assertStringContainsString('class="jk-page lang-ml"', $html);
                } else {
                    $this->assertStringNotContainsString('class="jk-page lang-ml"', $html);
                }

                if ($count === 1) {
                    $this->assertSame(0, substr_count($html, 'class="jk-side-feature"'));
                    $this->assertSame(0, substr_count($html, 'loading="lazy"'));
                    $this->assertSame(0, substr_count($html, 'id="gallery"'));
                    $this->assertStringNotContainsString('>Gallery</h2>', $html);
                    $this->assertStringNotContainsString('>ഗാലറി</h2>', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-1"[^>]*>\s*<p>'.preg_quote($paragraphs[1], '/').'/u', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-2"[^>]*>\s*<p>'.preg_quote($paragraphs[2], '/').'/u', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-3"[^>]*>\s*<p>'.preg_quote($paragraphs[3], '/').'/u', $html);
                } elseif ($count === 2) {
                    $this->assertSame(1, substr_count($html, 'class="jk-side-feature"'));
                    $this->assertSame(0, substr_count($html, 'loading="lazy"'));
                    $this->assertSame(0, substr_count($html, 'id="gallery"'));
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-1"[^>]*>\s*<img class="jk-side-feature/', $html);
                    $this->assertStringContainsString('magazine-alt-2', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-2"[^>]*>\s*<p>'.preg_quote($paragraphs[2], '/').'/u', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-3"[^>]*>\s*<p>'.preg_quote($paragraphs[3], '/').'/u', $html);
                } elseif ($count === 3) {
                    $this->assertSame(1, substr_count($html, 'id="gallery"'));
                    $this->assertStringContainsString($language === 'ml' ? '>ഗാലറി</h2>' : '>Gallery</h2>', $html);
                    $this->assertSame(1, substr_count($html, 'loading="lazy"'));
                    $this->assertStringContainsString('magazine-alt-2', $html);
                    $this->assertStringContainsString('magazine-alt-3', $html);
                    $this->assertMatchesRegularExpression('/data-photo-position="additional-3"[^>]*>\s*<p>'.preg_quote($paragraphs[3], '/').'/u', $html);
                } else {
                    $this->assertSame(1, substr_count($html, 'id="gallery"'));
                    $this->assertStringContainsString($language === 'ml' ? '>ഗാലറി</h2>' : '>Gallery</h2>', $html);
                    $this->assertSame(2, substr_count($html, 'loading="lazy"'));
                    $this->assertSame(0, substr_count($html, 'class="jk-position-copy"'));
                    $this->assertStringContainsString('magazine-alt-2', $html);
                    $this->assertStringContainsString('magazine-alt-3', $html);
                    $this->assertStringContainsString('magazine-alt-4', $html);
                    $this->assertLessThan(strpos($html, 'magazine-alt-3'), strpos($html, 'magazine-alt-2'));
                    $this->assertLessThan(strpos($html, 'magazine-alt-4'), strpos($html, 'magazine-alt-3'));
                }
            }
        }

        $this->assertNotNull($primary);
        $this->assertTrue((bool) $primary->is_primary);
    }

    public function test_a_short_story_reflows_instead_of_leaving_empty_photograph_frames(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: true);
        $profile->forceFill([
            'slug' => 'magazine.short',
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();
        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => 'en',
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Short story',
            'summary' => 'A distinct English summary.',
            'body' => 'Only one English paragraph is approved.',
            'source_material' => '',
            'ai_generated' => false,
        ]);
        $application->forceFill([
            'status' => Application::STATUS_PUBLISHED,
            'published_english_editorial_content_id' => $english->id,
        ])->save();
        $this->storePhoto($profile, $member, 'short-primary', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, primary: true, order: 1);

        $html = $this->get('/magazine.short')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Only one English paragraph is approved.'));
        $this->assertSame(1, substr_count($html, '<p class="jk-intro">A distinct English summary.</p>'));
        $this->assertSame(1, substr_count($html, '<div class="jk-summary-quote"><p>A distinct English summary.</p>'));
        $this->assertStringContainsString('jk-bio-grid jk-bio-grid--solo', $html);
        $this->assertSame(0, substr_count($html, 'data-photo-position="additional-'));
        $this->assertSame(0, substr_count($html, 'class="jk-side-slot"'));
        $this->assertSame(0, substr_count($html, 'id="gallery"'));
        $this->assertStringNotContainsString('class="jk-position-copy"', $html);
    }

    public function test_a_legacy_profile_with_five_approved_photographs_shows_four_and_keeps_every_record(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: false, tier: 'distinguished');
        $profile->forceFill(['slug' => 'legacy.five.photos'])->save();

        $kept = [
            $this->storePhoto($profile, $member, 'order-50', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 50),
            $this->storePhoto($profile, $member, 'order-10', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 10),
            $this->storePhoto($profile, $member, 'primary-kept', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, primary: true, order: 40),
            $this->storePhoto($profile, $member, 'order-20', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 20),
            $this->storePhoto($profile, $member, 'order-30', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 30),
        ];
        $ids = collect($kept)->pluck('id')->sort()->values()->all();

        try {
            app(ProfileMediaService::class)->uploadProfilePhoto(
                $profile->fresh(),
                UploadedFile::fake()->image('sixth-legacy.jpg', 400, 500),
                $member,
            );
            $this->fail('A sixth customer-visible photograph must be refused.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at most 4', $e->errors()['photo'][0]);
        }

        $this->assertSame($ids, MediaItem::query()->whereIn('id', $ids)->orderBy('id')->pluck('id')->all());
        $this->assertSame(5, MediaItem::query()->where('mediable_id', $profile->id)->where('media_type', MediaItem::TYPE_PROFILE_PHOTO)->count());

        $profile->forceFill([
            'slug' => 'legacy.five.photos',
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();

        $public = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());
        $this->assertSame(
            ['primary-kept', 'order-10', 'order-20', 'order-30'],
            $public->pluck('alt_text')->all(),
        );

        $html = $this->get('/legacy.five.photos')->assertOk()->getContent();
        foreach (['primary-kept', 'order-10', 'order-20', 'order-30'] as $alt) {
            $this->assertStringContainsString($alt, $html);
        }
        $this->assertStringNotContainsString('order-50', $html);
        $this->assertLessThan(strpos($html, 'order-10'), strpos($html, 'primary-kept'));
        $this->assertLessThan(strpos($html, 'order-20'), strpos($html, 'order-10'));
        $this->assertSame('primary-kept', MediaItem::query()->findOrFail($kept[2]->id)->alt_text);

        $this->actingAs($member)
            ->get(route('applications.media', $application))
            ->assertOk()
            ->assertSee('5 photographs are on file.', false)
            ->assertSee('order-50', false)
            ->assertDontSee('0 slots available', false);
    }

    public function test_a_legacy_profile_with_fewer_than_four_public_photographs_shows_only_the_eligible_ones(): void
    {
        [$member, $application, $profile] = $this->makeApplication(standard: false, tier: 'accomplished');
        $primary = $this->storePhoto($profile, $member, 'eligible-primary', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, primary: true, order: 1);
        $extra = $this->storePhoto($profile, $member, 'eligible-extra', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 2);
        $pending = $this->storePhoto($profile, $member, 'kept-pending', MediaItem::REVIEW_PENDING, MediaItem::PRIVACY_PRIVATE, order: 3);
        $private = $this->storePhoto($profile, $member, 'kept-private', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PRIVATE, order: 4);
        $rejected = $this->storePhoto($profile, $member, 'kept-rejected', MediaItem::REVIEW_REJECTED, MediaItem::PRIVACY_PUBLIC, order: 5);
        $ids = collect([$primary, $extra, $pending, $private, $rejected])->pluck('id')->sort()->values()->all();

        try {
            app(ProfileMediaService::class)->uploadProfilePhoto(
                $profile->fresh(),
                UploadedFile::fake()->image('over-limit.jpg', 400, 500),
                $member,
            );
            $this->fail('Pending and approved photographs already fill the four customer-visible places.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('at most 4', $e->errors()['photo'][0]);
        }
        $this->assertSame($ids, MediaItem::query()->whereIn('id', $ids)->orderBy('id')->pluck('id')->all());

        $profile->forceFill([
            'slug' => 'legacy.fewer.photos',
            'status' => 'published',
            'published_at' => now(),
            'unpublished_at' => null,
        ])->save();

        $public = app(PublicProfilePresentationService::class)->publicProfilePhotos($profile->fresh());
        $this->assertSame(['eligible-primary', 'eligible-extra'], $public->pluck('alt_text')->all());

        $html = $this->get('/legacy.fewer.photos')->assertOk()->getContent();
        $this->assertStringContainsString('eligible-primary', $html);
        $this->assertStringContainsString('eligible-extra', $html);
        $this->assertStringNotContainsString('kept-pending', $html);
        $this->assertStringNotContainsString('kept-private', $html);
        $this->assertStringNotContainsString('kept-rejected', $html);
        $this->assertStringContainsString('data-photo-position="additional-1"', $html);
        $this->assertStringNotContainsString('data-photo-position="additional-3"', $html);
        $this->assertSame(0, substr_count($html, 'class="jk-side-slot"'));
        $this->assertSame(0, substr_count($html, '<div class="jk-gallery-slot"'));
        $this->assertSame(1, substr_count($html, 'loading="lazy"'));

        [$openMember, , $openProfile] = $this->makeApplication(standard: false, tier: 'emerging');
        $keptPrimary = $this->storePhoto($openProfile, $openMember, 'open-primary', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, primary: true, order: 1);
        $keptExtra = $this->storePhoto($openProfile, $openMember, 'open-extra', MediaItem::REVIEW_APPROVED, MediaItem::PRIVACY_PUBLIC, order: 2);
        $keptRejected = [];
        foreach ([3, 4, 5] as $order) {
            $keptRejected[] = $this->storePhoto($openProfile, $openMember, 'open-rejected-'.$order, MediaItem::REVIEW_REJECTED, MediaItem::PRIVACY_PUBLIC, order: $order);
        }
        $this->assertSame(2, app(ProfileMediaService::class)->profilePhotoCount($openProfile->fresh()));
        $added = app(ProfileMediaService::class)->uploadProfilePhoto(
            $openProfile->fresh(),
            UploadedFile::fake()->image('third-eligible.jpg', 400, 500),
            $openMember,
            'added-third',
        );
        $this->assertSame(3, app(ProfileMediaService::class)->profilePhotoCount($openProfile->fresh()));
        $this->assertSame(['open-primary', 'open-extra'], app(PublicProfilePresentationService::class)->publicProfilePhotos($openProfile->fresh())->pluck('alt_text')->all());
        $this->assertTrue(MediaItem::query()->whereKey($keptPrimary->id)->exists());
        $this->assertTrue(MediaItem::query()->whereKey($keptExtra->id)->exists());
        foreach ($keptRejected as $row) {
            $this->assertTrue(MediaItem::query()->whereKey($row->id)->exists());
        }
        $this->assertSame('added-third', $added->alt_text);
        $this->assertSame('accomplished', $application->package_tier);
    }

    /**
     * @return array{0: User, 1: Application, 2: Profile}
     */
    private function makeApplication(bool $standard, string $tier = 'distinguished'): array
    {
        $member = User::factory()->create(['email_verified_at' => now()]);
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Slot Tester',
            'display_name' => 'Slot Tester',
            'profession' => 'Organiser',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $factory = Application::factory()->paid();
        if (! $standard) {
            $factory = $factory->historical($tier);
        }
        $application = $factory->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => 'Slot Tester',
            'package_tier' => $tier,
            'pricing_generation' => $standard ? Application::PRICING_GENERATION_STANDARD : null,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        return [$member, $application->fresh(), $profile->fresh()];
    }

    private function storePhoto(
        Profile $profile,
        User $owner,
        string $alt,
        string $review,
        string $privacy,
        string $type = MediaItem::TYPE_PROFILE_PHOTO,
        bool $primary = false,
        int $order = 1,
    ): MediaItem {
        return MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => $type,
            'storage_path_key' => 'tests/'.$alt.'.jpg',
            'disk' => 'public',
            'alt_text' => $alt,
            'display_order' => $order,
            'is_primary' => $primary,
            'uploaded_by_id' => $owner->id,
            'privacy' => $privacy,
            'review_status' => $review,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1200,
        ]);
    }
}
