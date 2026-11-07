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
        'K. Shafiq Rahman', 'R. Leelamma', 'C. Manoj Kumar', 'A. Mariamma',
        'Fr. Joseph Mathew', 'P. Rajeev Menon', 'S. Beena Kumari',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_seeder_publishes_fourteen_living_standard_profile_demos_with_slugs_ml_and_memberships(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $published = Profile::query()->where('status', 'published')->get();
        $this->assertSame(14, $published->count());
        $this->assertEqualsCanonicalizing(self::LIVING_NAMES, $published->pluck('display_name')->all());

        $expectedTiers = [
            'Fr. Joseph Mathew' => 'emerging', 'P. Rajeev Menon' => 'emerging', 'S. Beena Kumari' => 'emerging',
            'K. Shafiq Rahman' => 'accomplished', 'R. Leelamma' => 'accomplished',
            'C. Manoj Kumar' => 'accomplished', 'A. Mariamma' => 'accomplished',
        ];
        foreach ($published as $profile) {
            $this->assertSame($expectedTiers[$profile->full_name] ?? 'distinguished', $profile->application?->package_tier, $profile->full_name);
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
    }

    public function test_living_demos_carry_verbatim_corpus_text_and_supplied_portraits(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $living = require database_path('seeders/demo-profiles-living.php');

        $this->assertCount(14, $living);

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

    public function test_seeder_is_idempotent_and_keeps_integrity_snapshots_aligned(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $first = Profile::query()->where('status', 'published')->count();
        $keys = MediaItem::query()->orderBy('id')->pluck('storage_path_key', 'id')->all();

        $this->seed(DemoProfilesSeeder::class);
        $second = Profile::query()->where('status', 'published')->count();

        $this->assertSame(14, $first);
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

        // All restored demos appear in the gallery across its two pages
        // (12 per page), each clearly labelled DEMO.
        $galleryContent = $this->get('/gallery')->assertOk()->getContent()
            .$this->get('/gallery?page=2')->assertOk()->getContent();
        foreach (self::LIVING_NAMES as $name) {
            $this->assertStringContainsString($name, $galleryContent);
        }
        $this->assertSame(14, substr_count($galleryContent, 'class="demo-badge"'));
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--emerging'));
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--accomplished'));
        $this->assertSame(0, substr_count($galleryContent, 'tier-mark--distinguished'));
        foreach (['Recognised', 'Acclaimed', 'Distinguished', 'class="card-tier'] as $word) {
            $this->assertStringNotContainsString($word, $galleryContent, '/gallery must not render '.$word);
        }
        $this->assertStringNotContainsString('<p class="card-meta">Keralam</p>', $galleryContent);
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

        // The separate In Memoriam product is discontinued: memorial pages do not exist.
        $this->get('/in-memoriam/k.v.mathew')->assertNotFound();
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
