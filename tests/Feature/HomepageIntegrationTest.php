<?php

namespace Tests\Feature;

use App\Support\PricingAmounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_route_renders_integrated_homepage(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow', false);
        // Under Construction banner removed globally (footer pass 2).
        $response->assertDontSee('Under Construction', false);
        $response->assertSee('branding/favicon-32.png', false);
        $response->assertSee('How It Works', false);
        $response->assertSee('FAQ &amp; Charges', false);
        $response->assertSee('View Demo Profiles', false);
        $response->assertSee('Create Profile', false);
        $response->assertSee('Sign In', false);
        $response->assertSee('In Memoriam', false);
        // The Editorial Preparation block was removed by the final frontend pass.
        $response->assertDontSee('Editorial Preparation');
        $response->assertDontSee('human editors', false);
        $response->assertSee('href="'.route('faq-charges').'">Tariff Plans →</a>', false);
        $response->assertSee('href="'.route('demo-profiles.index').'">Demo Profiles →</a>', false);
        $response->assertDontSee('FAQ &amp; Charges →', false);
        $response->assertDontSee('View Demo Profiles <span lang="ml">', false);
        $response->assertSee('nav-menu-btn', false);
        $response->assertSee('Open menu', false);
        $response->assertSee('branding/jannayaks-logo.jpg', false);
        $html = $response->getContent();
        $malayalamHeading = strpos($html, 'വിവിധ മേഖലകളിലെ ജനനായകരുടെ ഡിജിറ്റൽ ഗാലറി.');
        $englishSubtitle = strpos($html, 'A digital gallery of leaders among the people.');
        $banner = strpos($html, 'class="hero-banner"');
        $search = strpos($html, 'id="search"');
        $this->assertNotFalse($malayalamHeading);
        $this->assertNotFalse($englishSubtitle);
        $this->assertNotFalse($banner);
        $this->assertNotFalse($search);
        $this->assertTrue($malayalamHeading < $englishSubtitle && $englishSubtitle < $banner && $banner < $search);
        $intro = strpos($html, 'class="intro-section"');
        $recommend = strpos($html, 'class="recommend-strip"');
        $howItWorks = strpos($html, 'id="hiw"');
        $memoriam = strpos($html, 'id="memoriam"');
        $this->assertNotFalse($intro);
        $this->assertNotFalse($recommend);
        $this->assertNotFalse($howItWorks);
        $this->assertNotFalse($memoriam);
        $this->assertTrue($banner < $intro && $intro < $recommend && $recommend < $howItWorks && $howItWorks < $search && $search < $memoriam);
        $malayalamBlock = strpos($html, 'class="intro-block intro-block-ml" lang="ml"');
        $englishBlock = strpos($html, 'class="intro-block intro-block-en" lang="en"');
        $this->assertNotFalse($malayalamBlock);
        $this->assertNotFalse($englishBlock);
        $this->assertTrue($intro < $malayalamBlock && $malayalamBlock < $englishBlock && $englishBlock < $recommend);
        $response->assertSeeInOrder([
            'എന്താണ് Jannayaks?',
            'എങ്ങനെയാണ് ഇത് സാധ്യമാകുന്നത്?',
            'ഓരോ പ്രൊഫൈലും പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് വ്യക്തിപരമായി പരിശോധിക്കുന്നു.',
            'What is Jannayaks?',
            'How does it work?',
            'Every profile is personally reviewed before publication.',
            'Tariff Plans →',
            'Demo Profiles →',
        ], false);
        $this->assertStringNotContainsStringIgnoringCase('every application', $html);
        $response->assertDontSee("A digital gallery of people's leaders.", false);
        $response->assertSee('images/jannayaks-hero.jpeg', false);
        $response->assertSee('width="1600"', false);
        $response->assertSee('height="697"', false);
        $response->assertSee('fetchpriority="high"', false);
        $response->assertDontSee('loading="lazy"', false);
        // Shared footer partial (footer pass 1) replaced the old logo block.
        $response->assertSee('jf-footer', false);
        $response->assertDontSee('brightness(0) invert(1)', false);
        $response->assertDontSee('Well-wishers', false);
        $response->assertDontSee('🙏', false);
        $response->assertDontSee('₹25,000 / 3 years', false);
        $response->assertDontSee('Three tiers. One platform.', false);
        $response->assertDontSee('goes live in both languages — permanently', false);
        $response->assertDontSee('Apolitical. Verified. Permanent.', false);
        $response->assertDontSee('href="#tiers"', false);
        $response->assertSee(route('gallery.index', absolute: false), false);
        $response->assertSee(route('faq-charges', absolute: false), false);
        $response->assertSee(route('login', absolute: false), false);
        $response->assertSee(route('apply', absolute: false), false);
        $response->assertSee(route('in-memoriam.index', absolute: false), false);
        $response->assertSee(route('search.index', absolute: false), false);
        // Footer: no phone numbers, no directory link, Google Translate offers EN.
        $response->assertDontSee('public_phone', false);
        $response->assertDontSee('>Directories<', false);
        $response->assertSee("setLang('en')", false);
        $response->assertSee("'en,ml,hi,ta,kn,te,bn,gu,mr'", false);
    }

    public function test_google_translate_control_renders_once_with_https_loader_on_public_pages(): void
    {
        $loader = '<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>';

        foreach ([route('home'), route('faq-charges'), route('search.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="translate-wrap"'), $url);
            $this->assertSame(1, substr_count($html, 'class="translate-btn"'), $url);
            $this->assertSame(1, substr_count($html, 'id="google_translate_element"'), $url);
            $this->assertSame(1, substr_count($html, 'translate_a/element.js'), $url);
            $this->assertSame(1, substr_count($html, $loader), $url);
            $this->assertSame(1, substr_count($html, 'function googleTranslateElementInit()'), $url);
            $this->assertStringNotContainsString('src="//translate.google.com', $html, $url);
            foreach (['en', 'ml', 'hi', 'ta'] as $lang) {
                $this->assertStringContainsString("setLang('{$lang}')", $html, $url);
            }
        }

        $public = $this->get(route('search.index'))->getContent();
        $this->assertStringContainsString('aria-label="Open menu">☰</button>', $public);
        $this->assertStringNotContainsString('aria-label="Open menu">?</button>', $public);
    }

    public function test_homepage_intro_links_resolve(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'href="'.route('faq-charges').'">Tariff Plans →</a>'));
        $this->assertSame(1, substr_count($html, 'href="'.route('demo-profiles.index').'">Demo Profiles →</a>'));
        $this->get(route('faq-charges'))->assertOk();
        $this->get(route('demo-profiles.index'))->assertOk();
    }

    public function test_faq_charges_uses_authoritative_pricing(): void
    {
        $emerging = PricingAmounts::forTier('emerging');
        $accomplished = PricingAmounts::forTier('accomplished');
        $distinguished = PricingAmounts::forTier('distinguished');
        $inMemoriam = PricingAmounts::forInMemoriam5yr();

        $response = $this->get(route('faq-charges'));

        $response->assertOk();
        $response->assertSee('noindex, nofollow', false);
        // Under Construction banner removed globally (footer pass 2).
        $response->assertDontSee('Under Construction', false);
        $response->assertSee($emerging['base_formatted'], false);
        $response->assertSee($accomplished['base_formatted'], false);
        $response->assertSee($distinguished['base_formatted'], false);
        $response->assertSee($inMemoriam['base_formatted'], false);
        $response->assertSee('3 years', false);
        $response->assertSee('പ്രഗൽഭർ', false);
        $response->assertDontSee('പ്രമുഖർ', false);
        $response->assertDontSee('പ്രശസ്തർ', false);
        $response->assertDontSee('₹29,500', false);
        $response->assertSee('+ GST', false);
        $response->assertSee(route('in-memoriam.index', absolute: false), false);
        $response->assertSee(route('gallery.index', absolute: false), false);
        // Final frontend pass removals: QR, directory claims, interview add-on.
        $response->assertDontSee('QR', false);
        $response->assertDontSee('visiting card', false);
        $response->assertDontSee('directories', false);
        $response->assertDontSee('Directories', false);
        $response->assertDontSee('Optional add-on', false);
        $response->assertSee('The charges shown below are exclusive of GST', false);
    }

    public function test_demo_links_open_the_demo_collection_and_gallery_stays_in_navigation(): void
    {
        foreach (['/', '/faq-charges', '/demo-profiles', '/no-such-page-anywhere'] as $path) {
            $content = $this->get($path)->getContent();

            $this->assertMatchesRegularExpression('#href="[^"]*/demo-profiles"[^>]*>View Demo Profiles#', $content, $path);
            $this->assertDoesNotMatchRegularExpression('#href="[^"]*/gallery"[^>]*>View Demo Profiles#', $content, $path);
            $this->assertMatchesRegularExpression('#href="[^"]*/gallery"[^>]*>Gallery</a>#', $content, $path);
        }

        $this->assertMatchesRegularExpression(
            '#href="[^"]*/demo-profiles"\s+aria-current="page"#',
            $this->get('/demo-profiles')->getContent()
        );
    }

    public function test_existing_key_routes_still_respond(): void
    {
        $this->get(route('gallery.index'))->assertOk();
        $this->get(route('search.index'))->assertOk();
        $this->get(route('in-memoriam.index'))
            ->assertOk()
            ->assertSee('₹24,000', false)
            ->assertSee('3 years', false)
            ->assertDontSee('₹29,500', false);
        $this->get(route('login'))->assertOk();
        $this->get(route('apply'))->assertOk();
    }
}
