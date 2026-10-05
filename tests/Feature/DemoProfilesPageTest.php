<?php

namespace Tests\Feature;

use App\Models\InMemoriamProfile;
use App\Models\Profile;
use App\Services\ProfileUrlService;
use Database\Seeders\DemoProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoProfilesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_listing_links_every_demo_profile_and_memorial_with_tier_marks(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $response = $this->get(route('demo-profiles.index'))->assertOk();
        $content = $response->getContent();

        $living = Profile::query()->with('user')->get();
        $this->assertCount(10, $living);
        foreach ($living as $profile) {
            $response->assertSee('href="'.route('profiles.public', $profile->slug).'"', false);
            $response->assertSee($profile->full_name, false);
        }
        foreach (['k.v.mathew' => 'K. V. Mathew (Late)', 'dr.saroja.nair' => 'Dr. Saroja Nair (Late)'] as $slug => $name) {
            $response->assertSee('href="'.route('in-memoriam.show', $slug).'"', false);
            $response->assertSee($name, false);
        }

        $this->assertSame(3, substr_count($content, 'tier-mark tier-mark--emerging'));
        $this->assertSame(4, substr_count($content, 'tier-mark tier-mark--accomplished'));
        $this->assertSame(3, substr_count($content, 'tier-mark tier-mark--distinguished'));
        $this->assertSame(2, substr_count($content, 'tier-mark tier-mark--in_memoriam'));
        $this->assertStringNotContainsString('class="card-tier', $content);
        foreach (['Recognised', 'Acclaimed', 'Distinguished'] as $tierWord) {
            $this->assertStringNotContainsString($tierWord, $content);
        }

        $response->assertSee('These are fictional demonstration profiles.', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_malayalam_listing_links_to_malayalam_pages(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $profile = Profile::query()->where('full_name', 'K. Shafiq Rahman')->firstOrFail();

        $this->get(route('demo-profiles.index', ['lang' => 'ml']))
            ->assertOk()
            ->assertSee('<html lang="ml">', false)
            ->assertSee('ഡെമോ പ്രൊഫൈലുകൾ', false)
            ->assertSee('href="'.route('profiles.public', ['slug' => $profile->slug, 'lang' => 'ml']).'"', false)
            ->assertSee('href="'.route('in-memoriam.show', ['slug' => 'k.v.mathew', 'lang' => 'ml']).'"', false);
    }

    public function test_records_outside_the_demo_domain_are_not_listed(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $profile = Profile::query()->where('full_name', 'K. Shafiq Rahman')->firstOrFail();
        $profile->user->forceFill(['email' => 'shafiq.member@example.com'])->save();
        InMemoriamProfile::query()->where('slug', 'k.v.mathew')->firstOrFail()
            ->forceFill(['commissioner_contact_email' => 'family@example.com'])->save();

        $this->get(route('demo-profiles.index'))
            ->assertOk()
            ->assertDontSee('K. Shafiq Rahman', false)
            ->assertDontSee('K. V. Mathew', false)
            ->assertSee('R. Leelamma', false)
            ->assertSee('Dr. Saroja Nair (Late)', false);
    }

    public function test_demo_profile_pages_link_back_to_the_demo_collection_and_real_profiles_to_the_gallery(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('<a href="'.route('demo-profiles.index').'">Demo Profiles</a>', false)
            ->assertSee('<a href="'.route('demo-profiles.index').'" style="font:600 12px var(--jk-ui);letter-spacing:.08em;color:var(--jk-blue)">← Back to Demo Profiles</a>', false)
            ->assertDontSee('href="'.route('gallery.index').'">Demo Profiles</a>', false);

        Profile::query()->where('slug', 't.gopalakrishnan')->firstOrFail()
            ->user->forceFill(['email' => 'gopalakrishnan.member@example.com'])->save();

        $this->get('/t.gopalakrishnan')
            ->assertOk()
            ->assertSee('<a href="'.route('gallery.index').'">Demo Profiles</a>', false)
            ->assertSee('<a href="'.route('gallery.index').'" style="font:600 12px var(--jk-ui);letter-spacing:.08em;color:var(--jk-blue)">← Back to Demo Profiles</a>', false)
            ->assertDontSee('href="'.route('demo-profiles.index').'">Demo Profiles</a>', false);
    }

    public function test_main_menu_marks_demo_or_gallery_as_current_by_page_type(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $demoCurrent = '#href="[^"]*/demo-profiles"\s+aria-current="page"#';
        $galleryCurrent = '#href="[^"]*/gallery"\s+aria-current="page"#';

        foreach (['/demo-profiles', '/t.gopalakrishnan'] as $path) {
            $content = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression($demoCurrent, $content, $path);
            $this->assertDoesNotMatchRegularExpression($galleryCurrent, $content, $path);
        }

        Profile::query()->where('slug', 'r.leelamma')->firstOrFail()
            ->user->forceFill(['email' => 'leelamma.member@example.com'])->save();

        foreach (['/gallery', '/r.leelamma'] as $path) {
            $content = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression($galleryCurrent, $content, $path);
            $this->assertDoesNotMatchRegularExpression($demoCurrent, $content, $path);
        }
    }

    public function test_demo_profile_opens_in_malayalam_unless_english_is_requested(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $entry = (require database_path('seeders/demo-profiles-living.php'))['k.shafiq.rahman'];

        foreach (['/k.shafiq.rahman' => 'ml', '/k.shafiq.rahman?lang=ml' => 'ml', '/k.shafiq.rahman?lang=en' => 'en'] as $path => $language) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="'.$language.'">', $content, $path);
            $this->assertStringContainsString(last(explode("\n\n", $entry[$language]['body'])), $content, $path);
            $this->assertMatchesRegularExpression('#lang="'.$language.'"\s+aria-current="true"\s*>#', $content, $path);
        }
    }

    public function test_language_switcher_names_both_languages_in_full(): void
    {
        $this->seed(DemoProfilesSeeder::class);

        $this->get('/k.shafiq.rahman')
            ->assertOk()
            ->assertSee('>English</a>', false)
            ->assertSee('>മലയാളം</a>', false)
            ->assertDontSee('>EN</a>', false)
            ->assertDontSee('>ML</a>', false);
    }

    public function test_real_profile_still_opens_in_english_by_default(): void
    {
        $this->seed(DemoProfilesSeeder::class);
        $entry = (require database_path('seeders/demo-profiles-living.php'))['k.shafiq.rahman'];
        Profile::query()->where('slug', 'k.shafiq.rahman')->firstOrFail()
            ->user->forceFill(['email' => 'shafiq.member@example.com'])->save();

        foreach (['/k.shafiq.rahman' => 'en', '/k.shafiq.rahman?lang=en' => 'en', '/k.shafiq.rahman?lang=ml' => 'ml'] as $path => $language) {
            $content = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('<html lang="'.$language.'">', $content, $path);
            $this->assertStringContainsString(last(explode("\n\n", $entry[$language]['body'])), $content, $path);
        }
    }

    public function test_listing_path_is_reserved_from_member_profile_urls(): void
    {
        $this->assertTrue(app(ProfileUrlService::class)->isReserved('demo-profiles'));
    }
}
