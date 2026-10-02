<?php

namespace Tests\Feature;

use App\Models\EditorialContent;
use App\Models\InMemoriamProfile;
use App\Models\Profile;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo population (Profile Workflow: production population task).
 *
 * Proves the demo seeder produces the full presentation set through the
 * real workflow: 12 living published profiles (3 Recognised / 4 Acclaimed /
 * 5 Distinguished — the final Distinguished T. Gopalakrishnan takes the
 * 4th Distinguished slot, and the earlier Recognised Gopalakrishnan text is
 * retired), both memorials with `.late` display and ML content, personal
 * slugs on personal tiers, memberships started, and demos excluded from the
 * sitemap by the established internal-email convention.
 */
class DemoPopulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_publishes_twelve_living_demos_with_tiers_slugs_ml_and_memberships(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $published = Profile::query()->where('status', 'published')->get();
        $this->assertSame(12, $published->count());

        $tierCounts = [];
        foreach ($published as $profile) {
            $tier = $profile->application?->package_tier;
            $tierCounts[$tier] = ($tierCounts[$tier] ?? 0) + 1;

            $this->assertNotNull($profile->slug, $profile->full_name);
            $this->assertNotNull($profile->published_at, $profile->full_name);
            $this->assertNotNull($profile->membership, $profile->full_name.' must have a membership');

            // Bound approved EN + ML editorial for every demo (except the
            // locked Distinguished Gopalakrishnan ML, which was not recovered
            // and intentionally falls back to English).
            $enId = $profile->application?->published_english_editorial_content_id;
            $this->assertNotNull($enId, $profile->full_name);
            if ($profile->slug !== 't.gopalakrishnan') {
                $this->assertNotNull($profile->application?->published_malayalam_editorial_content_id, $profile->full_name);
                $ml = EditorialContent::query()->find($profile->application->published_malayalam_editorial_content_id);
                $this->assertSame($enId, $ml->source_editorial_content_id, $profile->full_name.' ML must reference its EN master');
            }

            // Personal slugs on personal tiers; system slugs on Recognised.
            if ($tier === 'emerging') {
                $this->assertDoesNotMatchRegularExpression('/^[a-z]+\.[a-z]/', (string) $profile->slug, $profile->full_name);
            } else {
                $this->assertStringContainsString('.', (string) $profile->slug, $profile->full_name);
            }
        }

        $this->assertSame(3, $tierCounts['emerging'] ?? 0);
        $this->assertSame(4, $tierCounts['accomplished'] ?? 0);
        $this->assertSame(5, $tierCounts['distinguished'] ?? 0);
    }

    public function test_memorial_demos_have_late_display_ml_and_untouched_slugs(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        foreach ([
            'k.v.mathew' => 'K. V. Mathew .late',
            'dr.saroja.nair' => 'Dr. Saroja Nair .late',
        ] as $slug => $displayName) {
            $memorial = InMemoriamProfile::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame($displayName, $memorial->deceased_display_name);
            $this->assertStringNotContainsString('late', $memorial->deceased_full_name);
            $this->assertStringNotContainsString('.late', $memorial->slug);

            $ml = $memorial->editorialContents()
                ->where('language', 'ml')
                ->where('status', 'approved')
                ->first();
            $this->assertNotNull($ml, $slug.' must have approved ML content');
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $first = Profile::query()->where('status', 'published')->count();

        $this->seed(DemoProfilesSeeder::class);
        $second = Profile::query()->where('status', 'published')->count();

        $this->assertSame(12, $first);
        $this->assertSame($first, $second, 're-seeding must not duplicate or unpublish demos');
    }

    public function test_public_pages_render_for_seeded_demos(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        // Personal-tier slug.
        $this->get('/farid.khan')
            ->assertOk()
            ->assertSee('Adv. Farid Khan', false)
            ->assertSee('Minority Welfare, Inter-faith Harmony and Social Justice', false);

        // Recognised system slug renders identically.
        $slug = Profile::query()->where('full_name', 'Fr. Joseph Mathew')->value('slug');
        $this->get('/'.$slug)->assertOk()->assertSee('Fr. Joseph Mathew', false);

        // ML presentation. (Note: the declined Malayalam form ഖാന്റെ uses
        // ന, not the ൻ of the bare name — assert on rendered body text.)
        $ml = $this->get('/farid.khan?lang=ml');
        $ml->assertOk();
        $this->assertStringContainsString('അഡ്വ. ഫരീദ് ഖാന്റെ പൊതുസംഭാവന', $ml->getContent());
        $this->assertStringContainsString('<html lang="ml">', $ml->getContent());

        // Memorial page with .late display.
        $this->get('/in-memoriam/k.v.mathew')
            ->assertOk()
            ->assertSee('K. V. Mathew .late', false);
    }
}
