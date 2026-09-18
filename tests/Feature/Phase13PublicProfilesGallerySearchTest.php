<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\EditorialClaimTrace;
use App\Models\EditorialContent;
use App\Models\GeoDistrict;
use App\Models\GeoLocalBody;
use App\Models\GeoState;
use App\Models\GeoWard;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileGeography;
use App\Models\ProfilePublicOffice;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileQrCodeService;
use App\Services\ProfileUrlService;
use App\Services\PublicProfileSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class Phase13PublicProfilesGallerySearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('https://www.jannayaks.in');
        URL::forceScheme('https');
    }

    public function test_published_profile_is_publicly_accessible_via_canonical_url(): void
    {
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'Mohanlal',
            'profession' => 'Artist',
            'english_body' => 'A documented public life in cinema and culture.',
        ]);

        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertSee('Mohanlal', false)
            ->assertSee('Artist', false)
            ->assertSee('A documented public life in cinema and culture.', false)
            ->assertDontSee('generation_run', false)
            ->assertDontSee('claimTraces', false);
    }

    public function test_unpublished_profile_is_not_public(): void
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Hidden Leader',
            'display_name' => 'Hidden Leader',
            'profession' => 'Organiser',
            'slug' => 'hidden-leader',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->get('/p/hidden-leader')->assertNotFound();
        $this->get('/gallery')->assertOk()->assertDontSee('Hidden Leader', false);
        $this->get('/search?q=Hidden')->assertOk()->assertDontSee('Hidden Leader', false);
    }

    public function test_customer_preview_and_ai_draft_are_not_public(): void
    {
        $member = User::factory()->create();
        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Draft Person',
            'display_name' => 'Draft Person',
            'profession' => '',
            'slug' => 'draft-person',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_DRAFT,
            'version_number' => 1,
            'title' => 'Secret draft',
            'body' => 'AI draft body must stay private.',
            'summary' => '',
            'source_material' => 'prompt secret',
            'ai_generated' => true,
        ]);

        $englishApproved = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 2,
            'title' => 'Preview only',
            'body' => 'Preview body must stay private until published.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
        ]);
        $application->forceFill([
            'status' => Application::STATUS_EDITORIAL_APPROVED,
            'customer_preview_released_at' => now(),
            'preview_english_editorial_content_id' => $englishApproved->id,
        ])->save();

        $this->get('/p/draft-person')->assertNotFound();
        $this->get(route('applications.preview', $application))->assertRedirect(); // guest
        $this->get('/search?q=Secret')->assertOk()->assertDontSee('Secret draft', false);
        $this->get('/search?q=Preview')->assertOk()->assertDontSee('Preview only', false);
        $this->get('/gallery')->assertOk()->assertDontSee('Draft Person', false);
        $this->get('/gallery')->assertDontSee('Preview body must stay private', false);
    }

    public function test_historical_slug_redirects_and_cannot_expose_another_profile(): void
    {
        [$profile, $member] = $this->makePublishedProfile(['display_name' => 'Profile A']);
        $old = (string) $profile->slug;
        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $member, 'profile-a-public', 'accomplished');

        $this->get('/p/'.$old)->assertRedirect('/p/profile-a-public');
        $this->get('/p/profile-a-public')->assertOk()->assertSee('Profile A', false);

        [$other] = $this->makePublishedProfile(['display_name' => 'Profile B', 'slug_seed' => 'other']);
        $this->get('/p/'.$old)->assertRedirect('/p/profile-a-public');
        $this->assertNotSame('profile-a-public', $other->fresh()->slug);
    }

    public function test_public_profile_shows_approved_english_and_malayalam_without_internals(): void
    {
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'Bilingual Leader',
            'english_body' => 'English approved narrative about public service.',
            'malayalam_body' => 'മലയാളം അംഗീകൃത ജീവചരിത്രം.',
            'with_internals' => true,
        ]);

        $en = $this->get(route('profiles.public', ['slug' => $profile->slug, 'lang' => 'en']))
            ->assertOk();
        $en->assertSee('English approved narrative about public service.', false);
        $en->assertDontSee('INTERNAL_NOTE_SECRET', false);
        $en->assertDontSee('claim-secret', false);
        $en->assertDontSee('openai', false);
        $en->assertDontSee('payment', false);

        $ml = $this->get(route('profiles.public', ['slug' => $profile->slug, 'lang' => 'ml']))
            ->assertOk();
        $ml->assertSee('മലയാളം അംഗീകൃത ജീവചരിത്രം.', false);
        $ml->assertSee('Bilingual Leader', false);
    }

    public function test_private_contact_verification_and_account_data_are_not_exposed(): void
    {
        $member = User::factory()->create([
            'email' => 'private-member@example.com',
            'name' => 'Account Holder',
        ]);
        [$profile] = $this->makePublishedProfile([
            'user' => $member,
            'display_name' => 'Public Name Only',
            'contact_email' => 'hidden-contact@example.com',
            'contact_mobile' => '919999999999',
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('private-member@example.com', $html);
        $this->assertStringNotContainsString('hidden-contact@example.com', $html);
        $this->assertStringNotContainsString('919999999999', $html);
        $this->assertStringNotContainsString('Account Holder', $html);
        $this->assertStringNotContainsString('EPIC', $html);
    }

    public function test_gallery_contains_only_published_profiles_in_deterministic_order(): void
    {
        [$older] = $this->makePublishedProfile(['display_name' => 'Older Published', 'published_at' => now()->subDay()]);
        [$newer] = $this->makePublishedProfile(['display_name' => 'Newer Published', 'published_at' => now()]);
        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'full_name' => 'Unpublished',
            'display_name' => 'Unpublished',
            'profession' => '',
            'slug' => 'unpublished-gal',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $response = $this->get(route('gallery.index'))->assertOk();
        $response->assertSee('Newer Published', false);
        $response->assertSee('Older Published', false);
        $response->assertDontSee('Unpublished', false);

        $posNewer = strpos($response->getContent(), 'Newer Published');
        $posOlder = strpos($response->getContent(), 'Older Published');
        $this->assertNotFalse($posNewer);
        $this->assertNotFalse($posOlder);
        $this->assertLessThan($posOlder, $posNewer);
        $this->assertSame($newer->id > 0, true);
        $this->assertSame($older->id > 0, true);
    }

    public function test_primary_text_search_finds_by_name_profession_location_and_multi_word(): void
    {
        $this->seedGeo();
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'Mohanlal',
            'profession' => 'social worker',
            'locality' => 'Wyoming',
            'state_region' => 'Kerala',
            'district_name' => 'Thiruvananthapuram',
            'local_body_name' => 'Neyyattinkara',
            'ward_name' => 'Kattakkada',
            'english_body' => 'Community leader serving Neyyattinkara.',
            'office_name' => 'Community organiser',
        ]);

        $this->get('/search?q=Mohanlal')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=social%20worker')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=Wyoming')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=Kattakkada')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=Thiruvananthapuram')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=doctor%20Kerala')->assertOk()->assertDontSee('Mohanlal', false);
        $this->get('/search?q=social%20worker%20Kattakkada')->assertOk()->assertSee('Mohanlal', false);
        $this->get('/search?q=artist%20Trivandrum')->assertOk()->assertDontSee('relevance', false);

        // Unpublished must never appear.
        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Wyoming Secret',
            'display_name' => 'Wyoming Secret',
            'profession' => 'social worker',
            'slug' => 'wyoming-secret',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $this->get('/search?q=Wyoming')->assertOk()->assertDontSee('Wyoming Secret', false);
        $this->assertSame($profile->slug !== '', true);
    }

    public function test_search_does_not_require_profession_taxonomy_or_expose_scores(): void
    {
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'Free Text Leader',
            'profession' => 'community leader',
        ]);

        $html = $this->get('/search?q=community%20leader')->assertOk()->getContent();
        $this->assertStringContainsString('Free Text Leader', $html);
        $this->assertStringNotContainsString('relevance score', $html);
        $this->assertStringNotContainsString('importance score', $html);
        $this->assertStringNotContainsString('Select a profession', $html);
        $this->assertStringContainsString((string) $profile->display_name, $html);
    }

    public function test_xss_and_profile_id_bypass_are_blocked(): void
    {
        [$profile] = $this->makePublishedProfile([
            'display_name' => '<script>alert(1)</script>Safe Name',
            'profession' => '<img src=x onerror=alert(1)>',
            'english_body' => 'Body with <script>alert("xss")</script> content.',
        ]);

        $html = $this->get(route('profiles.public', $profile->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert("xss")</script>', $html);

        $this->get('/profiles/'.$profile->id)->assertNotFound();
        $this->get('/p/'.$profile->id)->assertNotFound();
    }

    public function test_language_switch_keeps_same_profile_identity_and_qr_canonical(): void
    {
        [$profile, $member] = $this->makePublishedProfile([
            'display_name' => 'Same Person',
            'malayalam_body' => 'ഒരേ വ്യക്തി',
            'tier' => 'accomplished',
        ]);

        app(ProfileUrlService::class)->selectPersonalSlug($profile->fresh(), $member, 'same-person', 'accomplished');
        $profile = $profile->fresh();

        $this->get(route('profiles.public', ['slug' => 'same-person', 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Same Person', false);
        $this->get(route('profiles.public', ['slug' => 'same-person', 'lang' => 'ml']))
            ->assertOk()
            ->assertSee('Same Person', false)
            ->assertSee('ഒരേ വ്യക്തി', false);

        $payload = app(ProfileQrCodeService::class)->encodedPayload($profile);
        $this->assertSame('https://www.jannayaks.in/p/same-person', $payload);
    }

    public function test_profile_without_photograph_remains_presentable(): void
    {
        [$profile] = $this->makePublishedProfile([
            'display_name' => 'No Photo Leader',
            'with_photo' => false,
        ]);

        $this->get(route('profiles.public', $profile->slug))
            ->assertOk()
            ->assertSee('No Photo Leader', false);
        $this->get(route('gallery.index'))
            ->assertOk()
            ->assertSee('No Photo Leader', false);
    }

    public function test_pagination_does_not_leak_unpublished_records(): void
    {
        for ($i = 0; $i < 13; $i++) {
            $this->makePublishedProfile([
                'display_name' => 'Published '.$i,
                'published_at' => now()->subMinutes($i),
            ]);
        }
        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'full_name' => 'Leak Candidate',
            'display_name' => 'Leak Candidate',
            'profession' => '',
            'slug' => 'leak-candidate',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->get('/gallery?page=1')->assertOk()->assertDontSee('Leak Candidate', false);
        $this->get('/gallery?page=2')->assertOk()->assertDontSee('Leak Candidate', false);
        $this->get('/search?q=Leak')->assertOk()->assertDontSee('Leak Candidate', false);
    }

    public function test_public_profile_photo_route_enforces_ownership_publication_and_media_rules(): void
    {
        Storage::fake('public');

        [$profileA] = $this->makePublishedProfile([
            'display_name' => 'Photo Owner A',
            'with_photo' => false,
        ]);
        [$profileB] = $this->makePublishedProfile([
            'display_name' => 'Photo Owner B',
            'with_photo' => false,
        ]);

        $publicPhotoA = $this->attachProfileMedia($profileA, [
            'media_type' => 'profile_photo',
            'privacy' => 'public',
            'path' => 'photos/a-public.jpg',
        ]);
        $publicPhotoB = $this->attachProfileMedia($profileB, [
            'media_type' => 'profile_photo',
            'privacy' => 'public',
            'path' => 'photos/b-public.jpg',
        ]);
        $privatePhotoA = $this->attachProfileMedia($profileA, [
            'media_type' => 'profile_photo',
            'privacy' => 'private',
            'path' => 'photos/a-private.jpg',
        ]);
        $galleryImageA = $this->attachProfileMedia($profileA, [
            'media_type' => 'gallery_image',
            'privacy' => 'public',
            'path' => 'photos/a-gallery.jpg',
        ]);

        // a. Own public profile_photo on published profile succeeds.
        $this->get(route('profiles.public.photo', [$profileA, $publicPhotoA]))->assertOk();

        // b. Cross-profile media ID manipulation is denied.
        $this->get(route('profiles.public.photo', [$profileA, $publicPhotoB]))->assertNotFound();
        $this->get(route('profiles.public.photo', [$profileB, $publicPhotoA]))->assertNotFound();

        // c. Unpublished profile media is denied.
        $unpublished = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Unpublished Photo',
            'display_name' => 'Unpublished Photo',
            'profession' => '',
            'slug' => 'unpublished-photo',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        $unpublishedPhoto = $this->attachProfileMedia($unpublished, [
            'media_type' => 'profile_photo',
            'privacy' => 'public',
            'path' => 'photos/unpublished.jpg',
        ]);
        $this->get(route('profiles.public.photo', [$unpublished, $unpublishedPhoto]))->assertNotFound();

        // d. Private media on published profile is denied.
        $this->get(route('profiles.public.photo', [$profileA, $privatePhotoA]))->assertNotFound();

        // e. Non-profile-photo media is denied.
        $this->get(route('profiles.public.photo', [$profileA, $galleryImageA]))->assertNotFound();
    }

    public function test_unpublished_profiles_are_not_discoverable_by_profession_location_office_or_editorial(): void
    {
        $uniqueProfession = 'XylophoneArchivist'.uniqid();
        $uniqueLocality = 'ZzyzxHollow'.uniqid();
        $uniqueOffice = 'CouncilOfObscurity'.uniqid();
        $uniqueEditorial = 'ObscureEditorialPhrase'.uniqid();

        $unpublished = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Unpublished Channel Leak',
            'display_name' => 'Unpublished Channel Leak',
            'profession' => $uniqueProfession,
            'slug' => 'unpublished-channel-leak',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        ProfileGeography::query()->create([
            'profile_id' => $unpublished->id,
            'country_code' => 'IN',
            'state_region_name' => 'Kerala',
            'locality_place' => $uniqueLocality,
        ]);

        ProfilePublicOffice::query()->create([
            'profile_id' => $unpublished->id,
            'office_name' => $uniqueOffice,
            'where_location' => 'Hidden Ward',
            'term_summary' => 'Current',
            'is_current' => true,
            'sort_order' => 1,
        ]);

        EditorialContent::query()->create([
            'profile_id' => $unpublished->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'Hidden title',
            'body' => 'Body containing '.$uniqueEditorial.' for leakage checks.',
            'summary' => '',
            'source_material' => '',
            'ai_generated' => false,
        ]);

        foreach ([$uniqueProfession, $uniqueLocality, $uniqueOffice, $uniqueEditorial] as $term) {
            $this->get('/search?q='.urlencode($term))
                ->assertOk()
                ->assertDontSee('Unpublished Channel Leak', false)
                ->assertDontSee('unpublished-channel-leak', false)
                ->assertSee('No published profiles matched that search.', false);
        }
    }

    public function test_paginator_totals_exclude_unpublished_profiles(): void
    {
        $search = app(PublicProfileSearchService::class);

        $beforeGallery = $search->gallery()->total();
        $beforeSearch = $search->search('PaginatorProbe'.uniqid())->total();

        [$published] = $this->makePublishedProfile([
            'display_name' => 'Paginator Published',
            'profession' => 'PaginatorProbeUniqueProfession',
        ]);

        Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
            'full_name' => 'Paginator Unpublished',
            'display_name' => 'Paginator Unpublished',
            'profession' => 'PaginatorProbeUniqueProfession',
            'slug' => 'paginator-unpublished',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $gallery = $search->gallery();
        $this->assertSame($beforeGallery + 1, $gallery->total());
        $this->assertFalse(
            collect($gallery->items())->contains(fn (Profile $p): bool => $p->display_name === 'Paginator Unpublished')
        );

        $matched = $search->search('PaginatorProbeUniqueProfession');
        $this->assertSame(1, $matched->total());
        $this->assertSame($published->id, $matched->items()[0]->id);
        $this->assertSame(0, $beforeSearch);
    }

    public function test_place_of_birth_is_not_part_of_public_profile_search_service(): void
    {
        $source = file_get_contents(app_path('Services/PublicProfileSearchService.php'));
        $this->assertIsString($source);
        $this->assertStringNotContainsString('place_of_birth', $source);
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{0: Profile, 1: User, 2: Application}
     */
    private function makePublishedProfile(array $opts = []): array
    {
        $member = $opts['user'] ?? User::factory()->create();
        $admin = User::factory()->admin()->create();
        $tier = $opts['tier'] ?? 'accomplished';

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $opts['display_name'] ?? 'Leader '.uniqid(),
            'display_name' => $opts['display_name'] ?? 'Leader '.uniqid(),
            'profession' => $opts['profession'] ?? 'Public servant',
            'bio_headline' => $opts['headline'] ?? null,
            'place_of_birth' => $opts['place_of_birth'] ?? null,
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $profile->full_name,
            'preferred_display_name' => $profile->display_name,
            'preferred_contact_email' => $opts['contact_email'] ?? null,
            'preferred_contact_mobile' => $opts['contact_mobile'] ?? null,
            'package_tier' => $tier,
            'source_method' => 'admin_test_demo',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
        ]);

        $english = EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $profile->display_name,
            'body' => $opts['english_body'] ?? 'Approved English biography.',
            'summary' => $opts['english_summary'] ?? '',
            'source_material' => ! empty($opts['with_internals']) ? 'INTERNAL_NOTE_SECRET prompt' : '',
            'ai_generated' => true,
            'review_comment' => ! empty($opts['with_internals']) ? 'INTERNAL_NOTE_SECRET' : null,
        ]);

        if (! empty($opts['malayalam_body'])) {
            EditorialContent::query()->create([
                'profile_id' => $profile->id,
                'source_editorial_content_id' => $english->id,
                'language' => EditorialContent::LANGUAGE_ML,
                'status' => EditorialContent::STATUS_APPROVED,
                'version_number' => 1,
                'title' => $profile->display_name,
                'body' => $opts['malayalam_body'],
                'summary' => '',
                'source_material' => '',
                'ai_generated' => true,
            ]);
        }

        if (! empty($opts['with_internals'])) {
            EditorialClaimTrace::query()->create([
                'editorial_content_id' => $english->id,
                'claim_excerpt' => 'claim-secret',
                'sort_order' => 1,
                'mapped_to_source' => false,
            ]);
        }

        if (isset($opts['locality']) || isset($opts['district_name'])) {
            $district = null;
            if (! empty($opts['district_name'])) {
                $district = GeoDistrict::query()->where('name', $opts['district_name'])->first();
            }
            $localBody = null;
            $ward = null;
            if ($district && ! empty($opts['local_body_name'])) {
                $localBody = GeoLocalBody::query()->firstOrCreate(
                    ['district_id' => $district->id, 'name' => $opts['local_body_name']],
                    ['body_code' => 'LB'.random_int(1000, 9999), 'type' => 'municipality', 'ward_count' => 1],
                );
            }
            if ($localBody && ! empty($opts['ward_name'])) {
                $ward = GeoWard::query()->firstOrCreate(
                    ['local_body_id' => $localBody->id, 'name' => $opts['ward_name']],
                    ['ward_code' => 'W'.random_int(1000, 9999)],
                );
            }
            ProfileGeography::query()->create([
                'profile_id' => $profile->id,
                'country_code' => 'IN',
                'state_region_name' => $opts['state_region'] ?? 'Kerala',
                'district_id' => $district?->id,
                'local_body_id' => $localBody?->id,
                'ward_id' => $ward?->id,
                'locality_place' => $opts['locality'] ?? null,
            ]);
        }

        if (! empty($opts['office_name'])) {
            ProfilePublicOffice::query()->create([
                'profile_id' => $profile->id,
                'office_name' => $opts['office_name'],
                'where_location' => (string) ($opts['office_location'] ?? 'Local community'),
                'term_summary' => (string) ($opts['term_summary'] ?? 'Current'),
                'is_current' => true,
                'sort_order' => 1,
            ]);
        }

        if (($opts['with_photo'] ?? false) === true) {
            Storage::fake('public');
            $path = 'photos/profile-'.$profile->id.'-'.uniqid().'.jpg';
            Storage::disk('public')->put($path, 'fake-image');
            MediaItem::query()->create([
                'mediable_type' => $profile->getMorphClass(),
                'mediable_id' => $profile->id,
                'media_type' => 'profile_photo',
                'storage_path_key' => $path,
                'disk' => 'public',
                'caption' => null,
                'alt_text' => 'Portrait',
                'display_order' => 1,
                'privacy' => 'public',
                'mime_type' => 'image/jpeg',
            ]);
        }

        $application->forceFill([
            'customer_approved_at' => now(),
            'customer_approved_english_editorial_content_id' => $english->id,
            'customer_approved_by_user_id' => $member->id,
            'status' => Application::STATUS_AWAITING_PUBLICATION,
        ])->save();

        $published = app(ApplicationWorkflowService::class)->publish($application->fresh(), $admin, true);
        $profile = Profile::query()->findOrFail($published->profile_id);

        if (isset($opts['published_at'])) {
            $profile->forceFill(['published_at' => $opts['published_at']])->save();
        }

        return [$profile->fresh(), $member, $published->fresh()];
    }

    /**
     * @param  array{media_type: string, privacy: string, path: string, disk?: string}  $attrs
     */
    private function attachProfileMedia(Profile $profile, array $attrs): MediaItem
    {
        $disk = $attrs['disk'] ?? 'public';
        Storage::disk($disk)->put($attrs['path'], 'fake-image-bytes');

        return MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => $attrs['media_type'],
            'storage_path_key' => $attrs['path'],
            'disk' => $disk,
            'caption' => null,
            'alt_text' => 'Test media',
            'display_order' => 1,
            'privacy' => $attrs['privacy'],
            'mime_type' => 'image/jpeg',
        ]);
    }

    private function seedGeo(): void
    {
        $state = GeoState::query()->where('name', 'Kerala')->first();
        if (! $state) {
            $state = GeoState::query()->create([
                'code' => 'KL',
                'name' => 'Kerala',
                'country_name' => 'India',
            ]);
        }

        GeoDistrict::query()->firstOrCreate(
            ['state_code' => $state->code, 'name' => 'Thiruvananthapuram'],
            ['source_code' => 'TVM'],
        );
    }
}
