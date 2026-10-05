<?php

namespace Tests\Feature;

use App\Models\EditorialContent;
use App\Models\InMemoriamGeography;
use App\Models\InMemoriamProfile;
use App\Models\MediaItem;
use App\Models\Profile;
use App\Models\ProfileIntegritySnapshot;
use App\Services\ProfileIntegrityService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Demo population (corrected demonstration corpus).
 *
 * Proves the demo seeder produces the full presentation set through the
 * real workflow: 10 living published profiles (3 Recognised / 4 Acclaimed /
 * 3 Distinguished) with verbatim bilingual corpus text and the supplied
 * watermarked portraits, both memorials with the "(Late)" display and no
 * unsupported dates or locations, personal slugs on personal tiers,
 * memberships started, integrity snapshots that match the attached
 * portraits, and demo-only presentation rules (no default-state location
 * line, uncropped portraits, demonstration notices, tier corner markers
 * instead of tier words).
 */
class DemoPopulationTest extends TestCase
{
    use RefreshDatabase;

    private const LIVING_BY_TIER = [
        'emerging' => ['Fr. Joseph Mathew', 'P. Rajeev Menon', 'S. Beena Kumari'],
        'accomplished' => ['K. Shafiq Rahman', 'R. Leelamma', 'C. Manoj Kumar', 'A. Mariamma'],
        'distinguished' => ['T. Gopalakrishnan', 'P. Sreedharan', 'V. Suresh Babu'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seeder_publishes_ten_living_demos_with_tiers_slugs_ml_and_memberships(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $published = Profile::query()->where('status', 'published')->get();
        $this->assertSame(10, $published->count());

        $namesByTier = [];
        foreach ($published as $profile) {
            $tier = $profile->application?->package_tier;
            $namesByTier[$tier][] = $profile->display_name;

            $this->assertNotNull($profile->slug, $profile->full_name);
            $this->assertNotNull($profile->published_at, $profile->full_name);
            $this->assertNotNull($profile->membership, $profile->full_name.' must have a membership');
            $this->assertStringEndsWith('@jannayaks.internal', (string) $profile->user?->email, $profile->full_name);

            // Bound approved EN + ML editorial for every demo; ML references its EN master.
            $enId = $profile->application?->published_english_editorial_content_id;
            $this->assertNotNull($enId, $profile->full_name);
            $mlId = $profile->application?->published_malayalam_editorial_content_id;
            $this->assertNotNull($mlId, $profile->full_name);
            $this->assertSame($enId, EditorialContent::query()->find($mlId)->source_editorial_content_id, $profile->full_name.' ML must reference its EN master');

            // Personal slugs on personal tiers; system slugs on Recognised.
            if ($tier === 'emerging') {
                $this->assertDoesNotMatchRegularExpression('/^[a-z]+\.[a-z]/', (string) $profile->slug, $profile->full_name);
            } else {
                $this->assertStringContainsString('.', (string) $profile->slug, $profile->full_name);
            }
        }

        foreach (self::LIVING_BY_TIER as $tier => $names) {
            $this->assertEqualsCanonicalizing($names, $namesByTier[$tier] ?? [], $tier);
        }
    }

    public function test_living_demos_carry_verbatim_corpus_text_and_supplied_portraits(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $living = require database_path('seeders/demo-profiles-living.php');

        foreach ($living as $entry) {
            $profile = Profile::query()->where('full_name', $entry['name'])->firstOrFail();
            $this->assertSame($entry['profession'], $profile->profession, $entry['name']);

            foreach (['en' => 'published_english_editorial_content_id', 'ml' => 'published_malayalam_editorial_content_id'] as $language => $binding) {
                $editorial = EditorialContent::query()->findOrFail($profile->application->{$binding});
                $this->assertSame($entry[$language]['body'], $editorial->body, $entry['name'].' '.$language);
                $this->assertSame($entry[$language]['summary'], $editorial->summary, $entry['name'].' '.$language);
                // Summary is the first paragraph; the body keeps the complete narrative.
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

        // The replacement trade-union portrait is stored at its supplied size.
        $shafiq = Profile::query()->where('full_name', 'K. Shafiq Rahman')->firstOrFail();
        $shafiqPortrait = MediaItem::query()->where('mediable_type', $shafiq->getMorphClass())->where('mediable_id', $shafiq->id)->firstOrFail();
        $this->assertSame([1536, 1024], [(int) $shafiqPortrait->width, (int) $shafiqPortrait->height]);
        $this->assertSame('k_shafiq_rahman.jpg', $living['k.shafiq.rahman']['portrait']);
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

        $this->assertSame(10, $first);
        $this->assertSame($first, $second, 're-seeding must not duplicate or unpublish demos');
        $this->assertSame($keys, MediaItem::query()->orderBy('id')->pluck('storage_path_key', 'id')->all(), 'unchanged portraits must not be rewritten');

        foreach (Profile::query()->where('status', 'published')->get() as $profile) {
            $result = app(ProfileIntegrityService::class)->verifyProfile($profile, 'demo-run', true);
            $this->assertSame(ProfileIntegrityService::CLASSIFICATION_HEALTHY, $result['classification'], $profile->full_name);
        }
    }

    public function test_reseeding_replaces_a_changed_portrait_and_refreshes_the_snapshot(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $profile = Profile::query()->where('full_name', 'K. Shafiq Rahman')->firstOrFail();
        $portrait = MediaItem::query()->where('mediable_type', $profile->getMorphClass())->where('mediable_id', $profile->id)->firstOrFail();
        $portrait->forceFill(['photo_sha256' => str_repeat('0', 64)])->save();
        $staleKey = $portrait->storage_path_key;

        $this->seed(DemoProfilesSeeder::class);

        $portrait->refresh();
        $this->assertNotSame($staleKey, $portrait->storage_path_key);
        $this->assertSame(hash_file('sha256', database_path('seeders/demo-media/k_shafiq_rahman.jpg')), $portrait->photo_sha256);

        $snapshot = ProfileIntegritySnapshot::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame($portrait->storage_path_key, $snapshot->primary_photo_key);
        $this->assertSame('demo_profiles_seeder.portrait_replaced', $snapshot->source_event);
        $this->assertSame(
            ProfileIntegrityService::CLASSIFICATION_HEALTHY,
            app(ProfileIntegrityService::class)->verifyProfile($profile->fresh(), 'demo-run', true)['classification'],
        );
    }

    public function test_living_demo_pages_render_full_bilingual_text_with_tier_marks_and_no_tier_words(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $entry = (require database_path('seeders/demo-profiles-living.php'))['k.shafiq.rahman'];
        $lastParagraph = fn (string $body): string => last(explode("\n\n", $body));

        $en = $this->get('/k.shafiq.rahman');
        $en->assertOk()
            ->assertSee('K. Shafiq Rahman', false)
            ->assertSee('Trade Union Leader and Workers’ Welfare Organiser', false)
            ->assertSee($entry['en']['summary'], false)
            ->assertSee($lastParagraph($entry['en']['body']), false)
            ->assertSee('Fictional demonstration profile', false)
            ->assertSee('tier-mark tier-mark--accomplished', false)
            ->assertSee('jk-portrait jk-portrait--whole', false);
        $this->assertNoTierWordsOrDefaultLocation($en->getContent(), '/k.shafiq.rahman');

        $ml = $this->get('/k.shafiq.rahman?lang=ml');
        $ml->assertOk()->assertSee($lastParagraph($entry['ml']['body']), false);
        $this->assertStringContainsString('<html lang="ml">', $ml->getContent());
        $this->assertNoTierWordsOrDefaultLocation($ml->getContent(), '/k.shafiq.rahman?lang=ml');

        // Recognised system slug renders with the Recognised marker.
        $slug = Profile::query()->where('full_name', 'Fr. Joseph Mathew')->value('slug');
        $joseph = $this->get('/'.$slug);
        $joseph->assertOk()->assertSee('Fr. Joseph Mathew', false)->assertSee('tier-mark tier-mark--emerging', false);
        $this->assertNoTierWordsOrDefaultLocation($joseph->getContent(), '/'.$slug);

        $distinguished = $this->get('/t.gopalakrishnan');
        $distinguished->assertOk()->assertSee('tier-mark tier-mark--distinguished', false);
        $this->assertNoTierWordsOrDefaultLocation($distinguished->getContent(), '/t.gopalakrishnan');

        // Gallery cards carry the markers instead of tier pills.
        $gallery = $this->get('/gallery');
        $gallery->assertOk();
        foreach (array_merge(...array_values(self::LIVING_BY_TIER)) as $name) {
            $gallery->assertSee($name, false);
        }
        $this->assertSame(3, substr_count($gallery->getContent(), 'tier-mark--emerging'));
        $this->assertSame(4, substr_count($gallery->getContent(), 'tier-mark--accomplished'));
        $this->assertSame(3, substr_count($gallery->getContent(), 'tier-mark--distinguished'));
        foreach (['Recognised', 'Acclaimed', 'Distinguished', 'class="card-tier'] as $word) {
            $this->assertStringNotContainsString($word, $gallery->getContent(), '/gallery must not render '.$word);
        }
        // The district filter label names the state; no card carries a location line.
        $this->assertStringNotContainsString('<p class="card-meta">Keralam</p>', $gallery->getContent());
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

        // A demo with a recorded place still shows its location line.
        $leelamma = Profile::query()->where('full_name', 'R. Leelamma')->firstOrFail();
        $leelamma->geography->forceFill(['locality_place' => 'Kumarakom'])->save();
        $this->get('/r.leelamma')->assertOk()->assertSee('Kumarakom, Keralam', false);

        // The same default-state geography is shown for a non-demo owner, and
        // the demo notice and uncropped-portrait treatment are withheld.
        $shafiq = Profile::query()->where('full_name', 'K. Shafiq Rahman')->firstOrFail();
        $shafiq->user->forceFill(['email' => 'shafiq.member@example.com'])->save();
        $this->get('/k.shafiq.rahman')
            ->assertOk()
            ->assertSee('<p class="jk-location">Keralam</p>', false)
            ->assertSee('tier-mark tier-mark--accomplished', false)
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
