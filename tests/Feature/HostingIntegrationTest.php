<?php

namespace Tests\Feature;

use App\Hosting\HostingIntegrationAuthenticator;
use App\Models\Application;
use App\Models\EditorialContent;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileExternalLink;
use App\Models\ProfilePublicOffice;
use App\Models\User;
use App\Services\ApplicationWorkflowService;
use App\Services\ProfileUrlService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HostingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-hosting-integration-secret-do-not-use-in-production';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('hosting_integration.shared_secret', self::SECRET);
        Config::set('hosting_integration.timestamp_tolerance_seconds', 300);
        Config::set('hosting_integration.sitemap.default_per_page', 2);
        Config::set('hosting_integration.sitemap.max_per_page', 5);
        Storage::fake('public');
    }

    public function test_missing_signature_headers_return_401(): void
    {
        $this->getJson('/internal/hosting/sitemap-profiles')->assertUnauthorized();
    }

    public function test_invalid_signature_returns_401(): void
    {
        $this->getJson('/internal/hosting/sitemap-profiles', [
            HostingIntegrationAuthenticator::TIMESTAMP_HEADER => (string) time(),
            HostingIntegrationAuthenticator::SIGNATURE_HEADER => str_repeat('0', 64),
        ])->assertUnauthorized();
    }

    public function test_expired_signature_returns_401(): void
    {
        $timestamp = (string) (time() - 3600);
        $path = '/internal/hosting/sitemap-profiles';
        $signature = $this->sign('GET', $path, $timestamp, '');

        $this->getJson($path, [
            HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
            HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
        ])->assertUnauthorized();
    }

    public function test_valid_signature_reaches_handler(): void
    {
        $response = $this->signedGet('/internal/hosting/sitemap-profiles')
            ->assertOk()
            ->assertJsonPath('profiles', []);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_missing_integration_secret_fails_closed_with_503(): void
    {
        Config::set('hosting_integration.shared_secret', '');

        $timestamp = (string) time();
        $path = '/internal/hosting/sitemap-profiles';
        // Even a correctly formed signature cannot succeed without a server secret.
        $signature = hash_hmac('sha256', "GET\n{$path}\n{$timestamp}\n", 'irrelevant');

        $this->getJson($path, [
            HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
            HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
        ])->assertStatus(503);
    }

    public function test_eligible_iconsherald_profile_returns_allowlisted_payload(): void
    {
        [, $slug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Meera Lakshmi Pillai',
            'meera.lakshmi.pillai',
            returnProfile: true,
        );

        $profile->forceFill([
            'bio_headline' => 'Community organiser',
        ])->save();
        $profile->application->forceFill([
            'preferred_contact_email' => 'secret-owner@example.test',
            'preferred_contact_mobile' => '919876543210',
        ])->save();
        $profile = $profile->fresh(['application', 'media', 'externalLinks', 'publicOffices', 'user', 'geography']);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_ML,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => 'മീര',
            'body' => 'മലയാളം ജീവചരിത്രം.',
            'summary' => 'സംഗ്രഹം',
            'ai_generated' => false,
            'source_editorial_content_id' => $profile->application->published_english_editorial_content_id,
        ]);
        $profile->application->forceFill([
            'published_malayalam_editorial_content_id' => EditorialContent::query()
                ->where('profile_id', $profile->id)
                ->where('language', EditorialContent::LANGUAGE_ML)
                ->value('id'),
        ])->save();

        ProfilePublicOffice::query()->create([
            'profile_id' => $profile->id,
            'office_name' => 'Ward member',
            'where_location' => 'Kozhikode',
            'term_summary' => '2015–2020',
            'is_current' => false,
            'sort_order' => 1,
        ]);

        ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => 'Talk',
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'status' => ProfileExternalLink::STATUS_APPROVED,
            'is_publicly_active' => true,
            'submitted_by_user_id' => $profile->user_id,
        ]);
        ProfileExternalLink::query()->create([
            'profile_id' => $profile->id,
            'link_type' => ProfileExternalLink::TYPE_VIDEO,
            'label' => 'Pending',
            'url' => 'https://www.youtube.com/watch?v=pending',
            'status' => ProfileExternalLink::STATUS_PENDING_REVIEW,
            'is_publicly_active' => false,
            'submitted_by_user_id' => $profile->user_id,
        ]);

        $approvedPhoto = $this->attachPhoto($profile, 'photos/approved.jpg', approved: true, primary: true);
        $this->attachPhoto($profile, 'photos/pending.jpg', approved: false, primary: false);

        $response = $this->signedGet('/internal/hosting/profiles/'.$slug)->assertOk();
        $json = $response->json();

        $allowedKeys = [
            'slug',
            'canonical_url',
            'display_name',
            'profession',
            'headline',
            'location_label',
            'editorial',
            'offices',
            'videos',
            'photos',
            'published_at',
            'reference_code',
        ];
        foreach (array_keys($json) as $key) {
            $this->assertContains($key, $allowedKeys);
        }
        foreach ([
            'slug', 'canonical_url', 'display_name', 'profession', 'headline',
            'location_label', 'editorial', 'offices', 'videos', 'photos', 'published_at',
        ] as $required) {
            $this->assertArrayHasKey($required, $json);
        }

        $this->assertSame($slug, $json['slug']);
        $this->assertSame(ProfileUrlService::ICONSHERALD_PUBLIC_ORIGIN.'/'.$slug, $json['canonical_url']);
        $this->assertSame('Meera Lakshmi Pillai', $json['display_name']);
        $this->assertSame('Public servant', $json['profession']);
        $this->assertSame('Community organiser', $json['headline']);
        if (filled($profile->reference_code)) {
            $this->assertSame((string) $profile->reference_code, $json['reference_code']);
        } else {
            $this->assertArrayNotHasKey('reference_code', $json);
        }
        $this->assertSame('Meera Lakshmi Pillai', $json['editorial']['en']['title']);
        $this->assertSame('മീര', $json['editorial']['ml']['title']);
        $this->assertCount(1, $json['offices']);
        $this->assertSame('Ward member', $json['offices'][0]['office_name']);
        $this->assertCount(1, $json['videos']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $json['videos'][0]['url']);
        $this->assertCount(1, $json['photos']);
        $this->assertSame(route('profiles.public.photo', [$profile, $approvedPhoto]), $json['photos'][0]['url']);
        $this->assertTrue($json['photos'][0]['is_primary']);
        $this->assertArrayNotHasKey('id', $json);
        $this->assertArrayNotHasKey('storage_path_key', $json['photos'][0]);
        $this->assertArrayNotHasKey('disk', $json['photos'][0]);

        $encoded = $response->getContent();
        $this->assertStringNotContainsString('secret-owner@example.test', $encoded);
        $this->assertStringNotContainsString('919876543210', $encoded);
        $this->assertStringNotContainsString('photos/approved.jpg', $encoded);
        $this->assertStringNotContainsString('preferred_contact', $encoded);
        $this->assertStringNotContainsString('"payment', strtolower($encoded));
    }

    public function test_hosting_payload_orders_photos_primary_first_then_display_order_then_id(): void
    {
        [, , $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Ordered Photo Person',
            'ordered.photo.person',
            returnProfile: true,
        );

        // Created deliberately out of the intended order: a non-primary photo
        // first, the primary photo with a HIGHER display order, then a photo
        // tying on display order with a higher id.
        $secondaryLowId = $this->attachPhoto($profile, 'photos/order-secondary-5.jpg', approved: true, primary: false);
        $secondaryLowId->forceFill(['display_order' => 5])->save();

        $primaryHighOrder = $this->attachPhoto($profile, 'photos/order-primary-9.jpg', approved: true, primary: true);
        $primaryHighOrder->forceFill(['display_order' => 9])->save();

        $secondaryHighId = $this->attachPhoto($profile, 'photos/order-secondary-5b.jpg', approved: true, primary: false);
        $secondaryHighId->forceFill(['display_order' => 5])->save();

        // Excluded regardless of ordering: unapproved+private, and approved-but-private.
        $this->attachPhoto($profile, 'photos/order-pending.jpg', approved: false, primary: false);
        $approvedPrivate = $this->attachPhoto($profile, 'photos/order-private.jpg', approved: true, primary: false);
        $approvedPrivate->forceFill(['privacy' => MediaItem::PRIVACY_PRIVATE])->save();

        $profile = $profile->fresh(['application', 'media', 'externalLinks', 'publicOffices', 'user', 'geography']);
        $photos = $this->signedGet('/internal/hosting/profiles/ordered.photo.person')
            ->assertOk()
            ->json('photos');

        $this->assertCount(3, $photos, 'Only approved public photographs may appear.');
        $this->assertTrue($photos[0]['is_primary'], 'The primary photograph must come first.');

        $mediaIds = array_map(
            fn (array $photo): int => (int) preg_replace('#^.*?/photo/#', '', $photo['url']),
            $photos,
        );
        // Primary first; the two display-order-5 photos then resolve by id ascending.
        $this->assertSame(
            [$primaryHighOrder->id, $secondaryLowId->id, $secondaryHighId->id],
            $mediaIds,
            'Photos must order primary-first, then display_order asc, then id asc.',
        );
    }

    public function test_hosting_payload_orders_offices_by_sort_order_then_id_preserving_fields(): void
    {
        [, , $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Office Order Person',
            'office.order.person',
            returnProfile: true,
        );

        // Created out of order: sort_order 3 first, then two offices tying on
        // sort_order 1 whose order must resolve by id ascending. Optional
        // fields follow storage conventions (where_location is NOT NULL and
        // stored as an empty string when unset; term_summary is nullable).
        ProfilePublicOffice::query()->create([
            'profile_id' => $profile->id,
            'office_name' => 'Earlier district role',
            'where_location' => '',
            'term_summary' => null,
            'is_current' => false,
            'sort_order' => 3,
        ]);
        $current = ProfilePublicOffice::query()->create([
            'profile_id' => $profile->id,
            'office_name' => 'Current ward member',
            'where_location' => 'Kozhikode',
            'term_summary' => '2015–2020',
            'is_current' => true,
            'sort_order' => 1,
        ]);
        $tied = ProfilePublicOffice::query()->create([
            'profile_id' => $profile->id,
            'office_name' => 'Earlier tied role',
            'where_location' => '',
            'term_summary' => '2010–2014',
            'is_current' => false,
            'sort_order' => 1,
        ]);
        $this->assertGreaterThan($current->id, $tied->id, 'Test setup must create the tied office after the current one.');

        $offices = $this->signedGet('/internal/hosting/profiles/office.order.person')
            ->assertOk()
            ->json('offices');

        $this->assertSame([
            'Current ward member',
            'Earlier tied role',
            'Earlier district role',
        ], array_column($offices, 'office_name'), 'Offices must order sort_order asc, then id asc.');

        $this->assertTrue($offices[0]['is_current']);
        $this->assertSame('Kozhikode', $offices[0]['where_location']);
        $this->assertSame('2015–2020', $offices[0]['term_summary']);
        $this->assertFalse($offices[1]['is_current']);
        $this->assertNull($offices[1]['where_location']);
        $this->assertSame('2010–2014', $offices[1]['term_summary']);
        $this->assertNull($offices[2]['where_location']);
        $this->assertNull($offices[2]['term_summary']);
        $this->assertFalse($offices[2]['is_current']);
    }

    public function test_jannayaks_hosted_profile_returns_404(): void
    {
        [, $slug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Suresh Babu Menon',
            'suresh.babu.menon',
        );

        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertNotFound();
    }

    public function test_draft_inactive_suspended_withdrawn_and_erased_profiles_return_404(): void
    {
        $draft = Profile::query()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'under_editorial_review',
            'full_name' => 'Draft Person',
            'display_name' => 'Draft Person',
            'slug' => 'draft.person.hosting',
            'profession' => 'Organiser',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);
        Application::factory()->paid()->create([
            'user_id' => $draft->user_id,
            'profile_id' => $draft->id,
            'full_name' => 'Draft Person',
            'hosting_destination' => Application::HOSTING_DESTINATION_ICONSHERALD,
        ]);
        $this->signedGet('/internal/hosting/profiles/draft.person.hosting')->assertNotFound();

        [, $slug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Anitha Rajan Thomas',
            'anitha.rajan.thomas',
            returnProfile: true,
        );

        $profile->forceFill([
            'status' => 'inactive',
            'unpublished_at' => now(),
        ])->save();
        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertNotFound();

        $profile->forceFill([
            'status' => 'published',
            'unpublished_at' => null,
            'suspended_at' => now(),
        ])->save();
        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertNotFound();

        $profile->forceFill([
            'suspended_at' => null,
            'erasure_completed_at' => now(),
        ])->save();
        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertNotFound();
    }

    public function test_profile_becomes_unavailable_after_withdrawal(): void
    {
        [, $slug, $profile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Ravi Krishnan Nair',
            'ravi.krishnan.nair',
            returnProfile: true,
        );

        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertOk();

        $profile->forceFill([
            'status' => 'inactive',
            'unpublished_at' => now(),
        ])->save();

        $this->signedGet('/internal/hosting/profiles/'.$slug)->assertNotFound();
        $this->signedGet('/internal/hosting/sitemap-profiles')
            ->assertOk()
            ->assertJsonPath('profiles', []);
    }

    public function test_sitemap_lists_only_eligible_iconsherald_profiles_and_paginates(): void
    {
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Vijay Menon',
            'vijay.menon',
        );
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Lakshmi Devi One',
            'lakshmi.devi.one',
        );
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Lakshmi Devi Two',
            'lakshmi.devi.two',
        );
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Lakshmi Devi Three',
            'lakshmi.devi.three',
        );

        $page1 = $this->signedGet('/internal/hosting/sitemap-profiles?page=1&per_page=2')->assertOk();
        $this->assertCount(2, $page1->json('profiles'));
        $this->assertSame(3, $page1->json('pagination.total'));
        $this->assertSame(2, $page1->json('pagination.last_page'));

        foreach ($page1->json('profiles') as $entry) {
            $this->assertSame(['slug', 'canonical_url', 'published_at'], array_keys($entry));
            $this->assertStringStartsWith(ProfileUrlService::ICONSHERALD_PUBLIC_ORIGIN.'/', $entry['canonical_url']);
        }

        $page2 = $this->signedGet('/internal/hosting/sitemap-profiles?page=2&per_page=2')->assertOk();
        $this->assertCount(1, $page2->json('profiles'));

        $slugs = collect($page1->json('profiles'))
            ->merge($page2->json('profiles'))
            ->pluck('slug')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'lakshmi.devi.one',
            'lakshmi.devi.three',
            'lakshmi.devi.two',
        ], $slugs);
        $this->assertNotContains('vijay.menon', $slugs);
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->signedGet('/internal/hosting/profiles/no.such.person.exists')->assertNotFound();
    }

    public function test_per_page_above_maximum_is_clamped(): void
    {
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Clamp One',
            'clamp.one',
        );
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Clamp Two',
            'clamp.two',
        );
        $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Clamp Three',
            'clamp.three',
        );

        // max_per_page is 5 in setUp; requesting 50 must clamp.
        $response = $this->signedGet('/internal/hosting/sitemap-profiles?page=1&per_page=50')->assertOk();
        $this->assertSame(5, $response->json('pagination.per_page'));
        $this->assertLessThanOrEqual(5, count($response->json('profiles')));
    }

    public function test_query_string_tampering_returns_401(): void
    {
        $timestamp = (string) time();
        $signedPath = '/internal/hosting/sitemap-profiles?page=1&per_page=2';
        $signature = $this->sign('GET', $signedPath, $timestamp, '');

        $this->call(
            'GET',
            '/internal/hosting/sitemap-profiles?page=2&per_page=2',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
                HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
            ]),
            ''
        )->assertUnauthorized();
    }

    public function test_reordered_query_string_is_verified_against_raw_transmitted_bytes(): void
    {
        // Existing normal request shape continues to validate.
        $this->signedGet('/internal/hosting/sitemap-profiles?page=1&per_page=2')->assertOk();

        // A reordered query is valid when signed exactly as transmitted — the
        // verifier compares raw QUERY_STRING bytes and must not re-sort them.
        $this->signedGet('/internal/hosting/sitemap-profiles?per_page=2&page=1')->assertOk();
    }

    public function test_reordered_query_signed_with_different_order_is_rejected(): void
    {
        $timestamp = (string) time();
        $signedPath = '/internal/hosting/sitemap-profiles?page=1&per_page=2';
        $signature = $this->sign('GET', $signedPath, $timestamp, '');

        $this->call(
            'GET',
            '/internal/hosting/sitemap-profiles?per_page=2&page=1',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
                HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
            ]),
            ''
        )->assertUnauthorized();
    }

    public function test_unsigned_non_empty_get_body_is_rejected(): void
    {
        $path = '/internal/hosting/sitemap-profiles';
        $timestamp = (string) time();
        // Signature computed for empty body; request carries a non-empty body.
        $signature = $this->sign('GET', $path, $timestamp, '');

        $this->call(
            'GET',
            $path,
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
                HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
                HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
            ]),
            '{"probe":true}'
        )->assertUnauthorized();
    }

    public function test_demo_iconsherald_profile_excluded_from_read_and_sitemap(): void
    {
        [, $demoSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Demo Gallery Leader',
            'demo.gallery.leader',
            email: 'demo.gallery.leader'.SitemapService::DEMO_EMAIL_DOMAIN,
        );
        [, $realSlug] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Genuine Hosting Leader',
            'genuine.hosting.leader',
        );

        $this->assertStringEndsWith(SitemapService::DEMO_EMAIL_DOMAIN, 'demo.gallery.leader'.SitemapService::DEMO_EMAIL_DOMAIN);

        $this->signedGet('/internal/hosting/profiles/'.$demoSlug)->assertNotFound();
        $sitemap = $this->signedGet('/internal/hosting/sitemap-profiles')->assertOk();
        $slugs = collect($sitemap->json('profiles'))->pluck('slug')->all();
        $this->assertNotContains($demoSlug, $slugs);
        $this->assertContains($realSlug, $slugs);
    }

    public function test_rate_limiting_applies_to_invalid_authentication(): void
    {
        Config::set('hosting_integration.rate_limit_per_minute', 3);
        RateLimiter::clear('hosting-integration:127.0.0.1');

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/internal/hosting/sitemap-profiles')->assertUnauthorized();
        }

        $this->getJson('/internal/hosting/sitemap-profiles')->assertStatus(429);
    }

    public function test_iconsherald_photo_receives_cross_origin_corp_and_jannayaks_keeps_same_origin(): void
    {
        [, , $ihProfile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_ICONSHERALD,
            'Photo Ih Host',
            'photo.ih.host',
            returnProfile: true,
        );
        [, , $jnProfile] = $this->publishWithHosting(
            Application::HOSTING_DESTINATION_JANNAYAKS,
            'Photo Jn Host',
            'photo.jn.host',
            returnProfile: true,
        );

        $ihPhoto = $this->attachPhoto($ihProfile, 'photos/ih-approved.jpg', approved: true, primary: true);
        $jnPhoto = $this->attachPhoto($jnProfile, 'photos/jn-approved.jpg', approved: true, primary: true);
        $pending = $this->attachPhoto($ihProfile, 'photos/ih-pending.jpg', approved: false, primary: false);

        $ihPhotoResponse = $this->get(route('profiles.public.photo', [$ihProfile, $ihPhoto]))
            ->assertOk()
            ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin');
        $this->assertStringContainsString('max-age=300', (string) $ihPhotoResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', (string) $ihPhotoResponse->headers->get('Cache-Control'));

        $this->get(route('profiles.public.photo', [$jnProfile, $jnPhoto]))
            ->assertOk()
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');

        $this->get(route('profiles.public.photo', [$ihProfile, $pending]))->assertNotFound();

        $ihProfile->forceFill([
            'status' => 'inactive',
            'unpublished_at' => now(),
        ])->save();
        $this->get(route('profiles.public.photo', [$ihProfile, $ihPhoto]))->assertNotFound();

        // Global CORP policy on ordinary pages remains same-origin.
        $this->get('/')->assertOk()->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->get('/gallery')->assertOk()->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    /**
     * Signed GET with an empty raw body (getJson encodes [] and would break HMAC).
     *
     * @return \Illuminate\Testing\TestResponse
     */
    private function signedGet(string $uri)
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: $uri;
        $query = parse_url($uri, PHP_URL_QUERY);
        $pathWithQuery = $query ? $path.'?'.$query : $path;
        $timestamp = (string) time();
        $signature = $this->sign('GET', $pathWithQuery, $timestamp, '');

        return $this->call(
            'GET',
            $uri,
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                HostingIntegrationAuthenticator::TIMESTAMP_HEADER => $timestamp,
                HostingIntegrationAuthenticator::SIGNATURE_HEADER => $signature,
            ]),
            ''
        );
    }

    private function sign(string $method, string $pathWithQuery, string $timestamp, string $body): string
    {
        $auth = app(HostingIntegrationAuthenticator::class);

        return $auth->computeSignature(
            $auth->stringToSign($method, $pathWithQuery, $timestamp, $body),
            self::SECRET,
        );
    }

    private function attachPhoto(Profile $profile, string $path, bool $approved, bool $primary): MediaItem
    {
        Storage::disk('public')->put($path, 'fake-image-bytes');

        return MediaItem::query()->create([
            'mediable_type' => $profile->getMorphClass(),
            'mediable_id' => $profile->id,
            'media_type' => MediaItem::TYPE_PROFILE_PHOTO,
            'storage_path_key' => $path,
            'disk' => 'public',
            'caption' => null,
            'alt_text' => 'Portrait',
            'display_order' => $primary ? 1 : 2,
            'is_primary' => $primary,
            'privacy' => $approved ? MediaItem::PRIVACY_PUBLIC : MediaItem::PRIVACY_PRIVATE,
            'review_status' => $approved ? MediaItem::REVIEW_APPROVED : MediaItem::REVIEW_PENDING,
            'mime_type' => 'image/jpeg',
        ]);
    }

    /**
     * @return array{0: string, 1: string}|array{0: string, 1: string, 2: Profile}
     */
    private function publishWithHosting(
        string $destination,
        string $fullName,
        string $slug,
        bool $returnProfile = false,
        ?string $email = null,
    ): array {
        $member = User::factory()->create([
            'email' => $email ?? (strtolower(str_replace(' ', '.', $fullName)).'@example.test'),
        ]);
        $admin = User::factory()->admin()->create();

        $profile = Profile::query()->create([
            'user_id' => $member->id,
            'status' => 'under_editorial_review',
            'full_name' => $fullName,
            'display_name' => $fullName,
            'profession' => 'Public servant',
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $application = Application::factory()->paid()->create([
            'user_id' => $member->id,
            'profile_id' => $profile->id,
            'full_name' => $fullName,
            'preferred_display_name' => $fullName,
            'package_tier' => 'accomplished',
            'source_method' => 'admin_test_demo',
            'broad_location' => 'Kozhikode',
            'status' => Application::STATUS_AWAITING_EDITORIAL_REVIEW,
            'hosting_destination' => $destination,
            'preferred_contact_email' => $member->email,
            'preferred_contact_mobile' => '919999999999',
        ]);

        EditorialContent::query()->create([
            'profile_id' => $profile->id,
            'language' => EditorialContent::LANGUAGE_EN,
            'status' => EditorialContent::STATUS_APPROVED,
            'version_number' => 1,
            'title' => $fullName,
            'body' => 'Approved English biography.',
            'summary' => 'English summary',
            'ai_generated' => false,
        ]);

        $published = app(ApplicationWorkflowService::class)->publish(
            $application->fresh(),
            $admin,
            false,
            'Authorised offline publication for this test.',
        );

        $profile = Profile::query()->findOrFail($published->profile_id);
        app(ProfileUrlService::class)->selectPersonalSlug(
            $profile,
            $admin,
            $slug,
            'accomplished',
        );
        $profile = $profile->fresh(['application']);

        if ($returnProfile) {
            return [$fullName, $slug, $profile];
        }

        return [$fullName, $slug];
    }
}
