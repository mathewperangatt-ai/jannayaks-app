<?php

namespace Tests\Feature;

use App\Models\EditorialContent;
use App\Models\InMemoriamGeography;
use App\Models\InMemoriamProfile;
use App\Models\MediaItem;
use App\Models\Membership;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Models\User;
use App\Services\ProfileIntegrityService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Demo population (corrected demonstration corpus).
 *
 * Proves the demo seeder produces the presentation set through the real
 * workflow: 7 full-length living published profiles (Standard Profile
 * examples) with verbatim bilingual corpus text and the supplied
 * watermarked portraits, both memorials with the "(Late)" display and no
 * unsupported dates or locations, personal slugs, memberships started,
 * integrity snapshots that match the attached portraits, and demo-only
 * presentation rules (no default-state location line, uncropped portraits,
 * demonstration notices).
 */
class DemoPopulationTest extends TestCase
{
    use RefreshDatabase;

    private const LIVING_NAMES = [
        'T. Gopalakrishnan', 'P. Sreedharan', 'V. Suresh Babu',
        'Arjun Menon', 'Nanditha Das', 'Fahim Yousuf', 'Meera Krishnan',
    ];

    private const RETIRED_SHORT_DEMO_NAMES = [
        'Fr. Joseph Mathew', 'P. Rajeev Menon', 'S. Beena Kumari',
        'K. Shafiq Rahman', 'R. Leelamma', 'C. Manoj Kumar', 'A. Mariamma',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seeder_publishes_seven_living_standard_profile_demos_with_slugs_ml_and_memberships(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $published = Profile::query()->where('status', 'published')->get();
        $this->assertSame(7, $published->count());
        $this->assertEqualsCanonicalizing(self::LIVING_NAMES, $published->pluck('display_name')->all());

        foreach ($published as $profile) {
            $this->assertSame('distinguished', $profile->application?->package_tier, $profile->full_name);
            $this->assertNotNull($profile->slug, $profile->full_name);
            $this->assertStringContainsString('.', (string) $profile->slug, $profile->full_name);
            $this->assertNotNull($profile->published_at, $profile->full_name);
            $this->assertNotNull($profile->membership, $profile->full_name.' must have a membership');
            $this->assertStringEndsWith('@jannayaks.internal', (string) $profile->user?->email, $profile->full_name);

            $enId = $profile->application?->published_english_editorial_content_id;
            $this->assertNotNull($enId, $profile->full_name);
            $mlId = $profile->application?->published_malayalam_editorial_content_id;
            $this->assertNotNull($mlId, $profile->full_name);
            $this->assertSame($enId, EditorialContent::query()->find($mlId)->source_editorial_content_id, $profile->full_name.' ML must reference its EN master');
        }

        foreach (self::RETIRED_SHORT_DEMO_NAMES as $name) {
            $this->assertFalse(
                Profile::query()->where('full_name', $name)->where('status', 'published')->exists(),
                $name.' must not remain a published living demo',
            );
        }
    }

    public function test_living_demos_carry_verbatim_corpus_text_and_supplied_portraits(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $living = require database_path('seeders/demo-profiles-living.php');

        $this->assertCount(7, $living);

        foreach ($living as $entry) {
            $profile = Profile::query()->where('full_name', $entry['name'])->firstOrFail();
            $this->assertSame($entry['profession'], $profile->profession, $entry['name']);

            foreach (['en' => 'published_english_editorial_content_id', 'ml' => 'published_malayalam_editorial_content_id'] as $language => $binding) {
                $editorial = EditorialContent::query()->findOrFail($profile->application->{$binding});
                $this->assertSame($entry[$language]['body'], $editorial->body, $entry['name'].' '.$language);
                $this->assertSame($entry[$language]['summary'], $editorial->summary, $entry['name'].' '.$language);
                $this->assertStringStartsWith($editorial->summary."\n\n", $editorial->body, $entry['name'].' '.$language);
                $this->assertStringNotContainsString('**', $editorial->body, $entry['name'].' '.$language);
                $this->assertStringNotContainsString('---', $editorial->body, $entry['name'].' '.$language);
            }

            $portrait = MediaItem::query()
                ->where('mediable_type', $profile->getMorphClass())
                ->where('mediable_id', $profile->id)
                ->where('is_primary', true)
                ->firstOrFail();
            $this->assertTrue($portrait->isApprovedForPublicDisplay(), $entry['name']);
            $this->assertSame(hash_file('sha256', database_path('seeders/demo-media/'.$entry['portrait'])), $portrait->photo_sha256, $entry['name']);
            Storage::disk('public')->assertExists($portrait->storage_path_key);
        }
    }

    public function test_memorial_demos_use_late_display_corpus_text_and_no_dates_or_location(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $memorials = require database_path('seeders/demo-profiles-memorial.php');

        $this->assertSame(['k.v.mathew', 'dr.saroja.nair'], array_keys($memorials));

        foreach ([
            'k.v.mathew' => ['K. V. Mathew', 'K. V. Mathew (Late)'],
            'dr.saroja.nair' => ['Dr. Saroja Nair', 'Dr. Saroja Nair (Late)'],
        ] as $slug => [$fullName, $displayName]) {
            $memorial = InMemoriamProfile::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame($displayName, $memorial->deceased_display_name);
            $this->assertSame($fullName, $memorial->deceased_full_name);
            $this->assertNull($memorial->deceased_date_of_birth, $slug);
            $this->assertNull($memorial->deceased_date_of_death, $slug);
            $this->assertFalse(InMemoriamGeography::query()->where('in_memoriam_profile_id', $memorial->id)->exists(), $slug);
            $this->assertSame(0, $memorial->publicOffices()->count(), $slug);

            foreach (['en', 'ml'] as $language) {
                $editorial = $memorial->editorialContents()->where('language', $language)->where('status', 'approved')->firstOrFail();
                $this->assertSame($memorials[$slug][$language]['body'], $editorial->body, $slug.' '.$language);
                $this->assertSame($memorials[$slug][$language]['summary'], $editorial->summary, $slug.' '.$language);
                $this->assertStringEndsWith('(Late)', $editorial->title, $slug.' '.$language);
                $this->assertStringNotContainsString('.late', $editorial->title.$editorial->body, $slug.' '.$language);
            }

            $portrait = MediaItem::query()
                ->where('mediable_type', $memorial->getMorphClass())
                ->where('mediable_id', $memorial->id)
                ->where('is_primary', true)
                ->firstOrFail();
            $this->assertSame(hash_file('sha256', database_path('seeders/demo-media/'.$memorials[$slug]['portrait'])), $portrait->photo_sha256, $slug);
        }
    }

    public function test_seeder_is_idempotent_and_keeps_integrity_snapshots_aligned(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $first = Profile::query()->where('status', 'published')->count();
        $keys = MediaItem::query()->orderBy('id')->pluck('storage_path_key', 'id')->all();

        $this->seed(DemoProfilesSeeder::class);
        $second = Profile::query()->where('status', 'published')->count();

        $this->assertSame(7, $first);
        $this->assertSame($first, $second, 're-seeding must not duplicate or unpublish demos');
        $this->assertSame($keys, MediaItem::query()->orderBy('id')->pluck('storage_path_key', 'id')->all(), 'unchanged portraits must not be rewritten');

        foreach (Profile::query()->where('status', 'published')->get() as $profile) {
            $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'demo-run', true);
            $this->assertSame(ProfileIntegrityService::CLASSIFICATION_HEALTHY, $result['classification'], $profile->full_name);
        }
    }

    public function test_reseeding_unpublishes_retired_short_profile_demos_only(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $owner = User::query()->updateOrCreate(
            ['email' => 'demo.fr.joseph.mathew@jannayaks.internal'],
            ['name' => 'Fr. Joseph Mathew', 'password' => bcrypt('secret'), 'email_verified_at' => now()],
        );
        $retired = Profile::query()->create([
            'user_id' => $owner->id,
            'status' => 'published',
            'full_name' => 'Fr. Joseph Mathew',
            'display_name' => 'Fr. Joseph Mathew',
            'profession' => 'Retired short demo',
            'slug' => 'josephmat',
            'published_at' => now(),
            'display_phone_consent' => false,
            'display_email_consent' => false,
        ]);

        $this->seed(DemoProfilesSeeder::class);

        $retired->refresh();
        $this->assertSame('inactive', $retired->status);
        $this->assertNotNull($retired->unpublished_at);
        $this->assertSame(7, Profile::query()->where('status', 'published')->whereNull('unpublished_at')->count());
        $this->assertTrue(Profile::query()->where('full_name', 'T. Gopalakrishnan')->where('status', 'published')->exists());
        $this->get(route('demo-profiles.index'))->assertOk()->assertDontSee('Fr. Joseph Mathew', false);
    }

    public function test_reseeding_replaces_a_changed_portrait_and_refreshes_the_snapshot(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $profile = Profile::query()->where('full_name', 'T. Gopalakrishnan')->firstOrFail();
        $portrait = MediaItem::query()->where('mediable_type', $profile->getMorphClass())->where('mediable_id', $profile->id)->firstOrFail();
        $portrait->forceFill(['photo_sha256' => str_repeat('0', 64)])->save();
        $staleKey = $portrait->storage_path_key;

        $this->seed(DemoProfilesSeeder::class);

        $portrait->refresh();
        $this->assertNotSame($staleKey, $portrait->storage_path_key);
        $this->assertSame(hash_file('sha256', database_path('seeders/demo-media/t_gopalakrishnan.jpg')), $portrait->photo_sha256);

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame($portrait->storage_path_key, $snapshot->primary_photo_key);
        $this->assertSame('demo_profiles_seeder.portrait_replaced', $snapshot->source_event);
        $this->assertSame(
            ProfileIntegrityService::CLASSIFICATION_HEALTHY,
            app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'demo-run', true)['classification'],
        );
    }

    public function test_reseeding_republishes_revised_text_only_for_listed_demos_and_keeps_integrity_healthy(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $living = require database_path('seeders/demo-profiles-living.php');

        $simulateEarlierSeed = function (string $fullName): Profile {
            $profile = Profile::query()->where('full_name', $fullName)->firstOrFail();
            EditorialContent::query()
                ->whereKey([
                    $profile->application->published_english_editorial_content_id,
                    $profile->application->published_malayalam_editorial_content_id,
                ])
                ->update(['body' => 'Earlier demonstration text.']);
            app(ProfileIntegrityService::class)->refreshSnapshot($profile, 'test.earlier_seed');

            return $profile;
        };
        $revised = $simulateEarlierSeed('T. Gopalakrishnan');
        $unlisted = $simulateEarlierSeed('Arjun Menon');
        $previousEnglishId = $revised->application->published_english_editorial_content_id;
        $previousMalayalamId = $revised->application->published_malayalam_editorial_content_id;
        $slug = $revised->slug;

        $this->seed(DemoProfilesSeeder::class);

        $application = $revised->application->fresh();
        $english = EditorialContent::query()->findOrFail($application->published_english_editorial_content_id);
        $malayalam = EditorialContent::query()->findOrFail($application->published_malayalam_editorial_content_id);
        $this->assertSame(2, $english->version_number);
        $this->assertSame($living['t.gopalakrishnan']['en']['body'], $english->body);
        $this->assertSame($living['t.gopalakrishnan']['ml']['body'], $malayalam->body);
        $this->assertSame($english->id, $malayalam->source_editorial_content_id);

        foreach ([$previousEnglishId, $previousMalayalamId] as $previousId) {
            $previous = EditorialContent::query()->findOrFail($previousId);
            $this->assertSame(EditorialContent::STATUS_ARCHIVED, $previous->status);
            $this->assertSame('Earlier demonstration text.', $previous->body);
        }

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $revised->id)->firstOrFail();
        $this->assertSame($english->id, $snapshot->english_editorial_content_id);
        $this->assertSame('application.malayalam_published', $snapshot->source_event);
        $this->assertSame($slug, $revised->fresh()->slug);
        $this->assertSame(1, Membership::query()->where('profile_id', $revised->id)->count());
        $this->get('/t.gopalakrishnan?lang=en')->assertOk()
            ->assertSee(last(explode("\n\n", $living['t.gopalakrishnan']['en']['body'])), false);

        $unlistedEnglish = EditorialContent::query()->findOrFail($unlisted->application->fresh()->published_english_editorial_content_id);
        $this->assertSame(1, $unlistedEnglish->version_number);
        $this->assertSame('Earlier demonstration text.', $unlistedEnglish->body);

        foreach ([$revised, $unlisted] as $profile) {
            $this->assertSame(
                ProfileIntegrityService::CLASSIFICATION_HEALTHY,
                app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'demo-run', true)['classification'],
                $profile->full_name,
            );
        }

        $versionCount = EditorialContent::query()->where('profile_id', $revised->id)->count();
        $this->seed(DemoProfilesSeeder::class);
        $this->assertSame($versionCount, EditorialContent::query()->where('profile_id', $revised->id)->count());
        $this->assertSame($english->id, $revised->application->fresh()->published_english_editorial_content_id);
    }

    public function test_living_demo_pages_render_full_bilingual_text_with_no_tier_words(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $entry = (require database_path('seeders/demo-profiles-living.php'))['t.gopalakrishnan'];
        $lastParagraph = fn (string $body): string => last(explode("\n\n", $body));

        $en = $this->get('/t.gopalakrishnan?lang=en');
        $en->assertOk()
            ->assertSee('T. Gopalakrishnan', false)
            ->assertSee('Social Educator and Community Leader', false)
            ->assertSee($entry['en']['summary'], false)
            ->assertSee($lastParagraph($entry['en']['body']), false)
            ->assertSee('Fictional demonstration profile', false)
            ->assertDontSee('tier-mark tier-mark--distinguished', false)
            ->assertSee('jk-portrait jk-portrait--whole', false);
        $this->assertNoTierWordsOrDefaultLocation($en->getContent(), '/t.gopalakrishnan?lang=en');

        $ml = $this->get('/t.gopalakrishnan?lang=ml');
        $ml->assertOk()->assertSee($lastParagraph($entry['ml']['body']), false);
        $this->assertStringContainsString('<html lang="ml">', $ml->getContent());
        $this->assertNoTierWordsOrDefaultLocation($ml->getContent(), '/t.gopalakrishnan?lang=ml');

        foreach (['t.gopalakrishnan', 'arjun.menon', 'nanditha.das', 'fahim.yousuf', 'meera.krishnan'] as $slug) {
            $page = $this->get('/'.$slug.'?lang=en');
            $page->assertOk()->assertDontSee('tier-mark tier-mark--distinguished', false);
            $this->assertNoTierWordsOrDefaultLocation($page->getContent(), '/'.$slug);
        }

        $galleryContent = $this->get('/gallery')->assertOk()->getContent();
        foreach (self::LIVING_NAMES as $name) {
            $this->assertStringContainsString($name, $galleryContent);
        }
        foreach (self::RETIRED_SHORT_DEMO_NAMES as $name) {
            $this->assertStringNotContainsString($name, $galleryContent);
        }
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--emerging'));
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--accomplished'));
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--distinguished'));
        foreach (['Recognised', 'Acclaimed', 'Distinguished', 'class="card-tier'] as $word) {
            $this->assertStringNotContainsString($word, $galleryContent, '/gallery must not render '.$word);
        }
        $this->assertStringNotContainsString('<p class="card-meta">Keralam</p>', $galleryContent);
    }

    public function test_memorial_demo_pages_show_late_name_notice_marker_and_whole_portrait(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $memorials = require database_path('seeders/demo-profiles-memorial.php');

        foreach (['k.v.mathew' => 'K. V. Mathew (Late)', 'dr.saroja.nair' => 'Dr. Saroja Nair (Late)'] as $slug => $displayName) {
            $en = $this->get('/in-memoriam/'.$slug);
            $en->assertOk()
                ->assertSee($displayName, false)
                ->assertSee(last(explode("\n\n", $memorials[$slug]['en']['body'])), false)
                ->assertSee('Fictional demonstration profile', false)
                ->assertSee('tier-mark tier-mark--in_memoriam', false)
                ->assertSee('im-portrait im-portrait--whole', false)
                ->assertDontSee('class="im-years"', false);
            $this->assertStringNotContainsString('.late', $en->getContent(), $slug);
            $this->assertStringNotContainsString('Kerala', $en->getContent(), $slug);

            $ml = $this->get('/in-memoriam/'.$slug.'?lang=ml');
            $ml->assertOk()
                ->assertSee($memorials[$slug]['ml']['title'], false)
                ->assertSee(last(explode("\n\n", $memorials[$slug]['ml']['body'])), false);
            $this->assertStringNotContainsString('.late', $ml->getContent(), $slug);
        }
    }

    public function test_demo_presentation_rules_do_not_apply_to_non_demo_owners(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $arjun = Profile::query()->where('full_name', 'Arjun Menon')->firstOrFail();
        $arjun->geography->forceFill(['locality_place' => 'Kumarakom'])->save();
        $this->get('/arjun.menon')->assertOk()->assertSee('Kumarakom, Keralam', false);

        $gopal = Profile::query()->where('full_name', 'T. Gopalakrishnan')->firstOrFail();
        $gopal->user->forceFill(['email' => 'gopalakrishnan.member@example.com'])->save();
        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('<p class="jk-location">Keralam</p>', false)
            ->assertDontSee('tier-mark tier-mark--distinguished', false)
            ->assertDontSee('Fictional demonstration profile', false)
            ->assertDontSee('jk-portrait jk-portrait--whole', false);

        $memorial = InMemoriamProfile::query()->where('slug', 'k.v.mathew')->firstOrFail();
        $memorial->forceFill(['commissioner_contact_email' => 'family@example.com'])->save();
        $this->get('/in-memoriam/k.v.mathew')
            ->assertOk()
            ->assertDontSee('Fictional demonstration profile', false)
            ->assertDontSee('im-portrait im-portrait--whole', false);
    }

    public function test_demo_identities_are_absent_from_the_sitemap(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $sitemap = $this->get('/sitemap.xml');
        $sitemap->assertOk();
        foreach (Profile::query()->pluck('slug') as $slug) {
            $this->assertStringNotContainsString('/'.$slug.'<', $sitemap->getContent(), $slug);
        }
        $this->assertStringNotContainsString('k.v.mathew', $sitemap->getContent());
        $this->assertStringNotContainsString('dr.saroja.nair', $sitemap->getContent());
    }

    private function assertNoTierWordsOrDefaultLocation(string $content, string $path): void
    {
        foreach (['Recognised', 'Acclaimed', 'Distinguished', 'RECOGNISED', 'ACCLAIMED', 'DISTINGUISHED', 'Keralam', 'KERALAM'] as $word) {
            $this->assertStringNotContainsString($word, $content, $path.' must not render '.$word);
        }
    }
}
