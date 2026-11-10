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
        $response->assertDontSee('noindex, nofollow', false);
        // TEMPORARY launch contact block: tel and WhatsApp links render with
        // the configured business number.
        $response->assertSee('tel:+919495949399', false);
        $response->assertSee('https://wa.me/919495949399', false);
        $response->assertSee('94 95 94 93 99', false);
        // Under Construction banner removed globally (footer pass 2).
        $response->assertDontSee('Under Construction', false);
        $response->assertSee('branding/favicon-32.png', false);
        $response->assertSee('How It Works', false);
        $response->assertSee('FAQ &amp; Charges', false);
        $response->assertSee('View Demo Profiles', false);
        $response->assertSee('Create Profile', false);
        $response->assertSee('Sign In', false);
        // In Memoriam section removed from the public homepage.
        $response->assertDontSee('In Memoriam', false);
        $response->assertSee('Choose a name-based address', false);
        $response->assertSee('arun.kumar', false);
        $response->assertSee('arun.kumar12', false);
        $response->assertDontSee('jj_nemom', false);
        $response->assertDontSee('Create Your Own Handle', false);
        // The approved pre-launch homepage presents editorial preparation
        // transparently (Pre-Launch Refinement Parts 4 and 6).
        $response->assertSee('Editorial Preparation');
        $response->assertSee('Prepared With Editorial Care', false);
        // Standard pricing in plain editorial text — no founding-member offer.
        $response->assertSee('Jannayaks Profile: ₹4,800 + applicable GST', false);
        $response->assertSee('Annual hosting renewal from the second year: ₹1,800 + applicable GST.', false);
        $response->assertDontSee('Founding', false);
        $response->assertDontSee('₹4,000', false);
        // Emotional message within Why Jannayaks.
        $response->assertSee('Your achievements deserve to be remembered — not just by the world, but by you.', false);
        $response->assertSee('href="'.route('faq-charges').'"', false);
        $response->assertSee('nav-menu-btn', false);
        $response->assertSee('Open menu', false);
        $response->assertSee('branding/jannayaks-logo.jpg', false);
        $html = $response->getContent();
        // Anchor to hero markup, not the headline text — the approved wording
        // also appears in <head> meta and structured data.
        $englishHeading = strpos($html, 'class="hero-en-primary"');
        $malayalamSubtitle = strpos($html, 'class="hero-ml-secondary"');
        $primaryCta = strpos($html, 'class="hero-cta hero-cta-primary"');
        $secondaryCta = strpos($html, 'class="hero-cta hero-cta-secondary"');
        $banner = strpos($html, 'class="hero-banner"');
        $search = strpos($html, 'id="search"');
        $this->assertNotFalse($englishHeading);
        $this->assertNotFalse($malayalamSubtitle);
        $response->assertSee('<h1 class="hero-en-primary">A Digital Gallery of Notable People Across All Fields.</h1>', false);
        $response->assertSee('കർമ്മപഥങ്ങളിൽ ശ്രദ്ധേയരായ വ്യക്തിത്വങ്ങളുടെ ഡിജിറ്റൽ ഗാലറി.', false);
        // The hero artwork is unchanged.
        $response->assertSee('images/jannayaks-hero-2-source.jpeg', false);
        $this->assertNotFalse($primaryCta);
        $this->assertNotFalse($secondaryCta);
        $this->assertNotFalse($banner);
        $this->assertNotFalse($search);
        $this->assertTrue($englishHeading < $malayalamSubtitle && $malayalamSubtitle < $primaryCta && $primaryCta < $secondaryCta && $secondaryCta < $banner && $banner < $search);
        $intro = strpos($html, 'class="intro-section"');
        $demoSection = strpos($html, 'id="sample-profiles"');
        $why = strpos($html, 'id="why"');
        $recommend = strpos($html, 'id="recommend"');
        $howItWorks = strpos($html, 'id="hiw"');
        $pricing = strpos($html, 'id="pricing"');
        $care = strpos($html, 'id="editorial-care"');
        $finalCta = strpos($html, 'id="create"');
        $this->assertNotFalse($demoSection);
        $this->assertNotFalse($why);
        $this->assertNotFalse($recommend);
        $this->assertNotFalse($howItWorks);
        $this->assertNotFalse($pricing);
        $this->assertNotFalse($care);
        $this->assertNotFalse($finalCta);
        // In Memoriam section removed from the public homepage.
        $this->assertFalse(strpos($html, 'id="memoriam') !== false);
        $urlSection = strpos($html, '<section class="url-section">');
        $this->assertTrue($banner < $intro && $intro < $demoSection && $demoSection < $why && $why < $search && $search < $howItWorks && $howItWorks < $pricing && $pricing < $care && $care < $recommend && $recommend < $finalCta, 'hero → What is Jannayaks → Demo Profiles → Why → … in order');
        $this->assertTrue($search < $urlSection && $urlSection < $howItWorks, 'search and the URL explanation are separate sections, in order');
        // The intro is fully bilingual: English first, Malayalam immediately
        // below, with no language toggle.
        $this->assertSame(0, substr_count($html, 'intro-tabs'));
        $this->assertSame(0, substr_count($html, 'switchIntroLang'));
        $leadEn = strpos($html, 'class="intro-lead-en"');
        $leadMl = strpos($html, 'class="intro-lead-ml"');
        $this->assertNotFalse($leadEn);
        $this->assertNotFalse($leadMl);
        $this->assertTrue($intro < $leadEn && $leadEn < $leadMl && $leadMl < $recommend);
        $response->assertSeeInOrder([
            'What is Jannayaks?',
            'എന്താണ് Jannayaks?',
            "Every life's work deserves to be remembered, recognised and documented.",
            'ഓരോരുത്തരുടെയും ജീവിതപ്രയത്നം ഓർമ്മിക്കപ്പെടണം, അംഗീകരിക്കപ്പെടണം, രേഖപ്പെടുത്തപ്പെടണം.',
            'Achievements are more than milestones. They represent years of dedication, professional accomplishments, personal journeys and contributions to society.',
            'നേട്ടങ്ങൾ വെറും നാഴികക്കല്ലുകളല്ല. വർഷങ്ങളുടെ അധ്വാനവും, തൊഴിൽരംഗത്തെ മികവും, ജീവിതയാത്രകളും, സമൂഹത്തിനുള്ള സംഭാവനകളുമാണ് അവ.',
            'Jannayaks™ gives these achievements a lasting digital presence.',
            'ആ നേട്ടങ്ങൾക്ക് Jannayaks™ ദീർഘകാലം നിലനിൽക്കുന്ന ഒരു ഡിജിറ്റൽ ഇടം നൽകുന്നു.',
            'Recognition',
            'അംഗീകാരം',
            'Give your achievements the acknowledgement they deserve.',
            'നിങ്ങളുടെ അധ്വാനത്തിന് അർഹമായ ശ്രദ്ധ ലഭിക്കട്ടെ.',
            'Identity',
            'നിങ്ങളുടെ കഥ',
            'Document your professional journey, experiences and accomplishments in one place, at jannayaks.in/your.name*',
            '*Name-based address, subject to availability.',
            'നിങ്ങളുടെ തൊഴിൽജീവിതവും അനുഭവങ്ങളും നേട്ടങ്ങളും ഒരിടത്ത്, jannayaks.in/your.name എന്ന നിങ്ങളുടെ സ്വന്തം വിലാസത്തിൽ*',
            '*പേരിനനുസരിച്ചുള്ള വിലാസം, ലഭ്യതയ്ക്ക് വിധേയം.',
            'Credibility',
            'വിശ്വാസ്യത',
            'A professionally prepared profile, built from your own details and published only with your approval.',
            'നിങ്ങളുടെ സ്വന്തം വിവരങ്ങളിൽ നിന്ന് പ്രൊഫഷണലായി തയ്യാറാക്കി, നിങ്ങളുടെ അംഗീകാരത്തോടെ മാത്രം പ്രസിദ്ധീകരിക്കുന്ന പ്രൊഫൈൽ.',
            'Family Esteem',
            'കുടുംബത്തിന്റെ അഭിമാനം',
            'Preserve a record of your achievements that your family can cherish and share.',
            'മക്കൾക്കും പേരക്കുട്ടികൾക്കും അഭിമാനത്തോടെ വായിക്കാവുന്ന നിങ്ങളുടെ ജീവിതകഥ.',
            'Lasting Record',
            'നിലനിൽക്കുന്ന രേഖ',
            'Unlike a social media post, your profile stays online for as long as your annual hosting continues.',
            'സോഷ്യൽ മീഡിയ പോസ്റ്റ് പോലെ മാഞ്ഞുപോകാതെ, നിങ്ങളുടെ സ്വന്തം ഇടത്ത്; വാർഷിക ഹോസ്റ്റിംഗ് തുടരുന്നിടത്തോളം.',
            'Make your family proud, yourself too.',
            'നിങ്ങളുടെ കുടുംബം അഭിമാനിക്കട്ടെ, നിങ്ങളും.',
            'ചോദ്യങ്ങളുണ്ടോ? Questions?',
        ], false);
        // The retired intro paragraphs must not linger.
        $response->assertDontSee('Jannayaks is a digital gallery that documents the contributions and achievements of individuals.', false);
        $response->assertDontSee('Whichever field you are in, have a record of yourself which you can be proud of.', false);
        $response->assertDontSee('An English-only profile is also available for those who prefer it.', false);
        $response->assertDontSee('ഓരോ പ്രൊഫൈലും പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് വ്യക്തിപരമായി പരിശോധിക്കുന്നു.', false);
        $response->assertSee('Tell us about yourself, your work and your achievements.', false);
        $response->assertDontSee('start the standard profile', false);
        $response->assertDontSee('Sign in with Google or WhatsApp', false);
        $this->assertStringNotContainsStringIgnoringCase('every application', $html);
        $response->assertDontSee("A digital gallery of people's leaders.", false);
        $response->assertSee('images/jannayaks-hero-2.webp', false);
        $response->assertSee('images/jannayaks-hero-2-800.webp', false);
        $response->assertDontSee('images/jannayaks-hero.jpeg', false);
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
        // In Memoriam links are removed from public navigation.
        $response->assertDontSee('/in-memoriam', false);
        $response->assertSee(route('search.index', absolute: false), false);
        // Footer: no phone numbers, no directory link.
        // Google Translate control is temporarily omitted from the homepage.
        $response->assertDontSee('public_phone', false);
        $response->assertDontSee('>Directories<', false);
        $response->assertDontSee('class="translate-btn"', false);
        $response->assertDontSee('class="translate-wrap"', false);
        $response->assertDontSee("setLang('en')", false);
        $response->assertDontSee('google_translate_element', false);
    }

    public function test_homepage_temporarily_hides_google_translate_control(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(0, substr_count($html, 'class="translate-wrap"'));
        $this->assertSame(0, substr_count($html, 'class="translate-btn"'));
        $this->assertSame(0, substr_count($html, 'id="google_translate_element"'));
        $this->assertSame(0, substr_count($html, 'translate_a/element.js'));
        $this->assertStringNotContainsString('aria-label="Translate"', $html);
    }

    public function test_google_translate_control_renders_once_with_https_loader_on_public_pages(): void
    {
        $loader = '<script src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>';

        foreach ([route('faq-charges'), route('search.index')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'class="translate-wrap"'), $url);
            $this->assertSame(1, substr_count($html, 'class="translate-btn"'), $url);
            $this->assertSame(1, substr_count($html, 'id="google_translate_element"'), $url);
            $this->assertSame(1, substr_count($html, 'translate_a/element.js'), $url);
            $this->assertSame(1, substr_count($html, $loader), $url);
            $this->assertSame(1, substr_count($html, 'function googleTranslateElementInit()'), $url);
            $this->assertStringNotContainsString('src="//translate.google.com', $html, $url);
            // Only English and Malayalam are offered as customer-facing languages
            // (Pre-Launch Refinement Part 1); the translate backend is preserved.
            foreach (['en', 'ml'] as $lang) {
                $this->assertStringContainsString("setLang('{$lang}')", $html, $url);
            }
            foreach (['hi', 'ta'] as $lang) {
                $this->assertStringNotContainsString("setLang('{$lang}')", $html, $url);
            }
        }

        $public = $this->get(route('search.index'))->getContent();
        $this->assertStringContainsString('aria-label="Open menu">☰</button>', $public);
        $this->assertStringNotContainsString('aria-label="Open menu">?</button>', $public);
    }

    public function test_homepage_intro_links_resolve(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // The pricing section links to FAQ & Charges; the sample-profiles
        // section links to the demo collection (Pre-Launch Refinement Part 4).
        $this->assertSame(1, substr_count($html, 'href="'.route('faq-charges').'">See FAQ &amp; Charges →</a>'));
        $this->assertSame(1, substr_count($html, 'href="'.route('demo-profiles.index').'">View the complete Demo Profiles collection →</a>'));
        $this->get(route('faq-charges'))->assertOk();
        $this->get(route('demo-profiles.index'))->assertOk();
    }

    public function test_demo_section_keeps_heading_and_collection_link_without_individual_cards(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $start = strpos($html, 'id="sample-profiles"');
        $this->assertNotFalse($start);
        $section = substr($html, $start, strpos($html, '</section>', $start) - $start);

        $this->assertStringContainsString('See What Your Profile Could Look Like', $section);
        $this->assertStringContainsString('നിങ്ങളുടെ പ്രൊഫൈൽ എങ്ങനെയായിരിക്കുമെന്ന് കാണൂ', $section);
        $this->assertStringContainsString('വ്യക്തികളുടെ നേട്ടങ്ങളും ജീവിതകഥകളും Jannayaks എങ്ങനെ അവതരിപ്പിക്കുന്നുവെന്ന് അറിയാൻ മാതൃകാ പ്രൊഫൈലുകൾ കാണൂ.', $section);
        $this->assertStringContainsString('href="'.route('demo-profiles.index').'">View the complete Demo Profiles collection →</a>', $section);
        $this->assertStringNotContainsString('section-eyebrow', $section, 'The duplicate eyebrow is removed from the Demo Profiles section.');

        $this->assertStringNotContainsString('class="demo-card"', $section);
        $this->assertStringNotContainsString('class="demo-grid"', $section);
        $this->assertStringNotContainsString('<img', $section);
        $this->assertStringNotContainsString('Explore sample profiles to see how Jannayaks presents individual achievements and life stories.', $section);

        $this->assertStringContainsString('id="search"', $html, 'The separate Search section stays.');
    }

    public function test_faq_charges_uses_authoritative_pricing(): void
    {
        $profile = PricingAmounts::forStandardProfile();
        $renewal = PricingAmounts::forTierRenewal(PricingAmounts::STANDARD_INTERNAL_TIER);
        $rewrite = PricingAmounts::forRevisionUpdate();
        // In Memoriam card removed from the charges page.

        $response = $this->get(route('faq-charges'));

        $response->assertOk();
        $response->assertDontSee('noindex, nofollow', false);
        $response->assertDontSee('Under Construction', false);
        $response->assertSee($profile['base_formatted'], false);
        $response->assertSee($renewal['base_formatted'], false);
        $response->assertSee($rewrite['base_formatted'], false);
        $response->assertDontSee('In Memoriam', false);
        $response->assertDontSee('3 years hosting', false);
        $response->assertDontSee('പ്രഗൽഭർ', false);
        $response->assertDontSee('₹6,000', false);
        $response->assertDontSee('₹2,400', false);
        $response->assertSee('+ GST', false);
        // In Memoriam links are removed from public navigation.
        $response->assertDontSee('/in-memoriam', false);
        $response->assertSee(route('gallery.index', absolute: false), false);
        $response->assertDontSee('QR', false);
        $response->assertDontSee('visiting card', false);
        $response->assertDontSee('directories', false);
        $response->assertDontSee('Directories', false);
        $response->assertDontSee('Optional add-on', false);
        $response->assertSee('One Profile', false);
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
        // In Memoriam public presentation is disabled; the landing 404s.
        $this->get('/in-memoriam')->assertNotFound();
        $this->get(route('login'))->assertOk();
        $this->get(route('apply'))->assertOk();
    }
}
