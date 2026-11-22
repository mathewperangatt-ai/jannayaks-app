<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
<link rel="icon" href="{{ asset('branding/favicon.ico') }}">
<title>FAQ &amp; Charges — Jannayaks™</title>
@php($schemaJson = app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->webPageGraph(route('faq-charges'), 'FAQ & Charges — Jannayaks')))
@include('partials.schema-ld', ['json' => $schemaJson])
@include('partials.seo-meta', [
    'title' => 'FAQ &amp; Charges — Jannayaks™',
    'description' => 'Jannayaks Profile charges, renewal, and answers to common questions.',
    'canonical' => route('faq-charges'),
])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#0F1F3D;--saffron:#C65102;--white:#FFFFFF;--off:#FAF6EE;
  --gray:#6B7280;--light:#F3F4F6;--charcoal:#1F2937;--border:#E5E7EB;
  --gold:#B8860B;--goldbg:#FFFBEB;--greenbg:#F0FDF4;--green:#1A6B3C;
  --fd:'Cormorant Garamond',Georgia,serif;--fb:'DM Sans',sans-serif;--fm:'Noto Serif Malayalam',serif;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
body{background:var(--off);color:var(--charcoal);font-family:var(--fb);-webkit-font-smoothing:antialiased;}
nav{background:var(--white);border-bottom:2px solid var(--saffron);position:sticky;top:0;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06);}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:64px;padding:0 22px;max-width:1400px;margin:0 auto;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo img{height:58px;width:auto;}
.nav-links{display:none;list-style:none;gap:28px;}
.nav-links a{font-size:13px;color:var(--gray);text-decoration:none;font-weight:500;}
.nav-links a:hover,.nav-links a[aria-current="page"]{color:var(--navy);}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-login{background:transparent;border:1.5px solid var(--navy);color:var(--navy);padding:7px 16px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;font-family:var(--fb);}
.nav-cta{background:var(--navy);color:white;border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;font-family:var(--fb);}
.translate-wrap{position:relative;}
.translate-btn{background:transparent;border:1px solid var(--border);color:var(--gray);padding:6px 12px;border-radius:4px;font-size:12px;font-family:var(--fb);cursor:pointer;display:flex;align-items:center;gap:5px;transition:all .2s;min-height:40px;}
.translate-btn:hover{border-color:var(--navy);color:var(--navy);}
.translate-dropdown{display:none;position:absolute;top:calc(100% + 6px);right:0;background:white;border:1px solid var(--border);border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.1);min-width:160px;z-index:200;}
.translate-dropdown.open{display:block;}
.translate-dropdown a{display:block;padding:12px 16px;font-size:13px;color:var(--charcoal);text-decoration:none;transition:background .15s;min-height:44px;}
.translate-dropdown a:hover{background:var(--light);}
.translate-dropdown a:first-child{border-radius:8px 8px 0 0;}
.translate-dropdown a:last-child{border-radius:0 0 8px 8px;}
.wrap{max-width:900px;margin:0 auto;padding:48px 22px 72px;}
.eyebrow{font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--saffron);font-weight:600;margin-bottom:10px;display:block;}
h1{font-family:var(--fd);font-size:clamp(28px,5vw,44px);color:var(--navy);line-height:1.2;margin-bottom:10px;}
.lede{font-size:15px;color:var(--gray);line-height:1.7;margin-bottom:36px;max-width:40rem;}
.card{background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:24px;margin-bottom:16px;}
.card.gold{border-color:var(--gold);background:linear-gradient(135deg,var(--goldbg) 0%,white 55%);}
.card.navy{border-color:var(--navy);background:linear-gradient(135deg,#F0F2FF 0%,white 55%);}
.tier-name{font-family:var(--fd);font-size:24px;font-weight:700;color:var(--navy);margin-bottom:2px;}
.tier-name-ml{font-family:var(--fm);font-size:15px;color:var(--gray);display:block;margin-bottom:10px;}
.price{font-family:var(--fd);font-size:30px;font-weight:700;color:var(--saffron);line-height:1;}
.price-sub{font-size:12px;color:var(--gray);margin:4px 0 12px;}
.price-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:10px 0 4px;}
.price-cell{border:1px solid var(--border);border-radius:8px;background:#fff;padding:14px 16px;}
.price-region{font-size:10px;font-weight:600;letter-spacing:.18em;text-transform:uppercase;color:var(--gray);margin:0 0 6px;}
.price-cell .price{font-size:30px;}
.price-note{font-size:12px;color:var(--gray);margin:6px 0 0;}
.renewal-hd{font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--navy);margin:16px 0 8px;}
@media(max-width:640px){.price-grid{grid-template-columns:1fr;}}
.who{font-size:13px;color:var(--gray);line-height:1.65;margin-bottom:14px;}
.who-ml{font-family:var(--fm);font-size:12px;color:var(--gray);display:block;margin-top:4px;line-height:1.65;}
.features{display:flex;flex-direction:column;gap:7px;margin:0;padding:0;list-style:none;}
.features li{font-size:12px;color:var(--gray);line-height:1.5;padding-left:1.1rem;position:relative;}
.features li::before{content:'✓';position:absolute;left:0;color:var(--green);}
h2{font-family:var(--fd);font-size:22px;color:var(--navy);margin:36px 0 14px;}
.faq{border-top:1px solid var(--border);padding:16px 0;}
.faq strong{display:block;font-size:14px;color:var(--navy);margin-bottom:6px;}
.faq p{font-size:13px;color:var(--gray);line-height:1.7;}
.back{margin-top:28px;font-size:13px;}
.back a{color:var(--navy);font-weight:600;}
@media(min-width:900px){.nav-links{display:flex;}}
@media(max-width:599px){.nav-logo img{height:36px;}.nav-login{padding:6px 10px;}.nav-cta{padding:8px 10px;}}
@media(max-width:899px){
  .nav-inner{height:auto;min-height:56px;padding:8px 14px;flex-wrap:wrap;gap:8px;}
  .nav-links{display:flex;order:3;width:100%;justify-content:center;gap:18px;padding:4px 0 2px;border-top:1px solid var(--border);}
  .nav-links a{display:inline-flex;align-items:center;min-height:40px;font-size:14px;color:var(--navy);}
  .translate-label{display:none;}
  .translate-btn{padding:8px 10px;min-width:44px;justify-content:center;}
}
</style>
@include('partials.ga4')
</head>
<body>
<nav>
  <div class="nav-inner">
    <a class="nav-logo" href="{{ route('home') }}">
      <img src="{{ asset('branding/jannayaks-logo.png') . '?v=2' }}" alt="Jannayaks">
    </a>
    <ul class="nav-links">
      <li><a href="{{ route('home') }}">Home</a></li>
      <li><a href="{{ route('gallery.index') }}">Gallery</a></li>
      <li><a href="{{ route('home') }}#hiw">How It Works</a></li>
      <li><a href="{{ route('home') }}#pricing">Pricing</a></li>
      <li><a href="{{ route('faq-charges') }}" aria-current="page">FAQ &amp; Charges</a></li>
    </ul>
    <div class="nav-right">
      @include('partials.translate-control')
      <a class="nav-login" href="{{ route('login') }}">Sign In</a>
      <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <span class="eyebrow">FAQ &amp; Charges / ചോദ്യങ്ങളും നിരക്കുകളും</span>
  <h1>Charges and common questions</h1>
  <p class="lede">
    One Profile. The Indian prices below are GST-inclusive — the amount shown is the amount payable at checkout.
    International prices are shown in US dollars for reference; online payment remains in Indian rupees.
    There is no discounted comparison price.
  </p>

  <div class="card">
    <div class="tier-name">Jannayaks Profile</div>
    <div class="price-grid">
        <div class="price-cell">
            <div class="price-region">India</div>
            <div class="price">{{ $profile['amount_incl_formatted'] }}</div>
            <div class="price-note">GST-inclusive</div>
        </div>
        <div class="price-cell">
            <div class="price-region">International</div>
            <div class="price">US${{ number_format((float) config('jannayaks.tier_pricing.international.profile_usd', 50), 0) }}</div>
            <div class="price-note">For applicants outside India</div>
        </div>
    </div>
    <div class="renewal-hd">Annual renewal — from the second year</div>
    <div class="price-grid">
        <div class="price-cell">
            <div class="price-region">India</div>
            <div class="price">{{ $renewal['amount_incl_formatted'] }}</div>
            <div class="price-note">GST-inclusive</div>
        </div>
        <div class="price-cell">
            <div class="price-region">International</div>
            <div class="price">US${{ number_format((float) config('jannayaks.tier_pricing.international.renewal_usd', 15), 0) }}</div>
            <div class="price-note">Per year</div>
        </div>
    </div>
    <ul class="features">
      <li>Profile preparation, editorial presentation, publication and first-year hosting</li>
      <li>Up to three photographs, and your approval before publication</li>
      <li>Typographical and factual corrections are free</li>
      <li>The same Online Interview for every applicant</li>
    </ul>
  </div>

  <h2>Frequently asked questions</h2>

  <div class="faq">
    <strong>Who can apply?</strong>
    <p>Jannayaks accepts profile submissions directly from individuals. You must be at least 18 years old and able to enter into a binding contract under Indian law.</p>
  </div>
  <div class="faq">
    <strong>What kinds of achievements can be featured?</strong>
    <p>Achievements and contributions from any field of work or public life can be featured. Profiles are prepared from the information and material you submit about yourself.</p>
  </div>
  <div class="faq">
    <strong>What does the Profile include?</strong>
    <p>The Profile includes profile preparation and the first year of publication, with up to three photographs. Videos and video links are not included.</p>
  </div>
  <div class="faq">
    <strong>How does the Online Interview work?</strong>
    <p>After your application, you complete the Online Interview at your own pace, telling us about yourself, your work and your achievements, and upload source material.</p>
  </div>
  <div class="faq">
    <strong>How is my profile prepared and approved?</strong>
    <p>Profiles are prepared first using artificial intelligence tools and are then checked by human editors before they are shown to you for approval. You review the prepared profile and approve it before publication.</p>
  </div>
  <div class="faq">
    <strong>How is my profile published?</strong>
    <p>Your approved profile is published on Jannayaks with its own shareable link. Publication is subject to editorial acceptance.</p>
  </div>
  <div class="faq">
    <strong>What does the Profile cost?</strong>
    <p>Jannayaks Profile: ₹5,000 in India (GST-inclusive) or US$50 international. It includes profile preparation, editorial presentation, publication and first-year hosting. Annual renewal from the second year is ₹1,500 in India (GST-inclusive) or US$15 international. Typographical and factual corrections are free.</p>
  </div>
  <div class="faq">
    <strong>What if Jannayaks declines my application?</strong>
    <p>All applications are subject to editorial acceptance. If Jannayaks rejects an application before publication, the full amount paid, including applicable taxes, will be refunded.</p>
  </div>
  <div class="faq">
    <strong>When am I eligible for a refund?</strong>
    <p>Before your profile is submitted for approval, a full refund applies. From submission for approval until seven days after submission, a 60% refund applies. After you approve the profile, or after the seven-day period ends, no refund applies. Full details are on the <a href="{{ route('legal.refund') }}">Refund and Cancellation Policy</a> page.</p>
  </div>
  <div class="faq">
    <strong>Can I recommend someone else?</strong>
    <p>Know someone whose achievements deserve recognition? Recommend them for Jannayaks through the Recommend Someone form. A recommendation is not an application submitted by the recommended person; a profile is created only if they themselves apply and consent.</p>
  </div>
  <div class="faq">
    <strong>How do I create a profile?</strong>
    <p>Use Create Profile, sign in with your username and password, or sign in with Google, and begin your profile application. Payment is based on the Profile price shown above.</p>
  </div>
  <div class="faq">
    <strong>When is payment taken?</strong>
    <p>The Profile fee is collected in the application before the interview and uploads. The amount is calculated on the server.</p>
  </div>
  <div class="faq">
    <strong>Is a direct personal interview available?</strong>
    <p>Jannayaks primarily creates profiles from information submitted through its Online Interview and other approved source material. If a direct personal interview is specifically required, we may be able to arrange this through an external professional service provider at an additional cost.</p>
  </div>
  <div class="faq">
    <strong>Are photography or reel production services available?</strong>
    <p>Jannayaks does not itself provide photography or reel production. If required, we can try to arrange suitable external agencies for professional photography and/or reel production at an additional cost, subject to availability.</p>
  </div>
  <div class="faq">
    <strong>What is annual hosting renewal?</strong>
    <p>After the first publication period, annual renewal is ₹1,500 in India (GST-inclusive) or US$15 international for every profile. This renewal keeps the profile and its permanent URL published and is separate from the initial Profile fee.</p>
  </div>
  <div class="faq">
    <strong>What about profile revisions after publication?</strong>
    <p>Typographical and factual corrections are free — submit them through your customer panel and the editorial team reviews them. Substantial rewriting is assessed individually: Jannayaks quotes the work before it begins, and the published profile stays unchanged until you approve a reviewed update.</p>
  </div>
  <div class="faq">
    <strong>Where can I see example profiles?</strong>
    <p><a href="{{ route('demo-profiles.index') }}">View Demo Profiles</a> opens a collection of fictional demonstration profiles showing how finished pages read. Published profiles appear in the <a href="{{ route('gallery.index') }}">Gallery</a>.</p>
  </div>
  <div class="faq">
    <strong>How is my personal profile URL created?</strong>
    <p>
      Your preferred name is used to create your personalised Jannayaks profile URL, subject to availability and URL formatting requirements.
      For example:
      https://jannayaks.in/arun.kumar
      or https://jannayaks.in/arun.kumar12
      A short number is added only if the name-based address is already taken.
      Your profile address remains permanent while your annual hosting renewal is active.
    </p>
  </div>
  <div class="faq">
    <strong>Can I change my profile URL after publication?</strong>
    <p>Your profile URL is generated using the preferred name you provide during the application process, subject to availability and formatting requirements. Once your profile is published, its URL cannot be changed. Please choose your preferred name carefully before submitting your application.</p>
    <p lang="ml">അപേക്ഷാ സമയത്ത് നിങ്ങൾ നൽകുന്ന ഇഷ്ടപ്പെട്ട പേര് ഉപയോഗിച്ചാണ്, ലഭ്യതയ്ക്കും ഫോർമാറ്റിനും വിധേയമായി, നിങ്ങളുടെ പ്രൊഫൈൽ URL സൃഷ്ടിക്കുന്നത്. പ്രൊഫൈൽ പ്രസിദ്ധീകരിച്ചുകഴിഞ്ഞാൽ അതിന്റെ URL മാറ്റാൻ കഴിയില്ല. അതിനാൽ അപേക്ഷ സമർപ്പിക്കുന്നതിന് മുമ്പ് ഇഷ്ടപ്പെട്ട പേര് ശ്രദ്ധാപൂർവം തിരഞ്ഞെടുക്കുക.</p>
  </div>

  <p class="back"><a href="{{ route('home') }}">← Back to homepage</a></p>
</main>

@include('partials.public-footer')
@include('partials.google-translate')
</body>
</html>
