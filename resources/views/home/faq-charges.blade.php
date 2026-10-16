<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
<link rel="icon" href="{{ asset('branding/favicon.ico') }}">
<title>FAQ &amp; Charges — Jannayaks™</title>
@php($schemaJson = app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->webPageGraph(route('faq-charges'), 'FAQ & Charges — Jannayaks')))
@include('partials.schema-ld', ['json' => $schemaJson])
@include('partials.seo-meta', [
    'title' => 'FAQ &amp; Charges — Jannayaks™',
    'description' => 'Jannayaks profile charges, renewal, edits, refunds, and answers to common questions.',
    'canonical' => route('faq-charges'),
])
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --navy:#0F1F3D;--saffron:#C65102;--white:#FFFFFF;--off:#F8F6F2;
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
.nav-cta{background:var(--saffron);color:white;border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;font-family:var(--fb);}
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
@media(max-width:899px){
  .translate-label{display:none;}
  .translate-btn{padding:8px 10px;min-width:44px;justify-content:center;}
}
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <a class="nav-logo" href="{{ route('home') }}">
      <img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks">
    </a>
    <ul class="nav-links">
      <li><a href="{{ route('home') }}#search">Search</a></li>
      <li><a href="{{ route('home') }}#hiw">How It Works</a></li>
      <li><a href="{{ route('faq-charges') }}" aria-current="page">FAQ &amp; Charges</a></li>
      <li><a href="{{ route('gallery.index') }}">Gallery</a></li>
      <li><a href="{{ route('demo-profiles.index') }}">View Demo Profiles</a></li>
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
    One standard profile. India prices are in rupees. The launch price is shown with the ordinary price crossed out while that launch price is in effect. GST treatment for invoices still needs professional confirmation; the figures below are the prices the application charges.
  </p>

  <div class="card">
    <div class="tier-name">Jannayaks profile</div>
    <div class="price">
      @if($profile['compare_at_base_formatted'])
        <s style="color:var(--gray);font-size:22px;margin-right:8px">{{ $profile['compare_at_base_formatted'] }}</s>
      @endif
      {{ $profile['base_formatted'] }} <span class="price-sub" style="display:inline">+ GST</span>
    </div>
    <div class="price-sub">Includes profile creation and the first year of publication and hosting. Launch price until the owner withdraws it. No automatic expiry.</div>
    <ul class="features">
      <li>Personal URL you can share</li>
      <li>One primary photograph and three documentary photographs</li>
      <li>Questionnaire, AI-assisted draft, human review, your approval, then publication</li>
      <li>Two consolidated edit requests each year after publication</li>
      <li>Annual renewal {{ $renewal['amount_incl_formatted'] }}, inclusive of applicable GST</li>
      <li>Full rewrite {{ $rewrite['base_formatted'] }} + GST</li>
    </ul>
  </div>

  <div class="card">
    <div class="tier-name">In Memoriam</div>
    <span class="tier-name-ml">ഓർമ്മയ്ക്കായി</span>
    <div class="price">Starting at {{ $inMemoriam['base_formatted'] }}</div>
    <div class="price-sub">For three years, with GST additional. Arranged by contact. There is no online memorial application.</div>
  </div>

  <h2>Frequently asked questions</h2>

  <div class="faq">
    <strong>Can I have a Jannayaks profile if I do not speak Malayalam?</strong>
    <p>Yes. Jannayaks welcomes people irrespective of their preferred language. Profiles are currently prepared in English, with Malayalam available for those who need it. Every profile has its own personal URL that you can share with others.</p>
  </div>
  <div class="faq">
    <strong>How is a profile prepared?</strong>
    <p>You answer the questionnaire and may provide source material. Jannayaks prepares an AI-assisted editorial draft. A member of staff reviews it. The completed profile is then released to your dashboard for your review and approval. It is published only after that approval and after payment is settled. The website’s translation control changes the interface language. It does not mean a profile is professionally prepared in every language.</p>
  </div>
  <div class="faq">
    <strong>How do I create a profile?</strong>
    <p>Use Create Profile, sign in with your username and password or with Google, and continue to payment. There is no package to choose and no SMS one-time password.</p>
  </div>
  <div class="faq">
    <strong>When is payment taken?</strong>
    <p>The profile fee is collected in the application before the interview and uploads. The amount is calculated on the server. International charges in US dollars are not available yet, because billing country, gateway currency and tax treatment are not confirmed. Customers outside India should write to us before paying.</p>
  </div>
  <div class="faq">
    <strong>What does renewal cost?</strong>
    <p>After the first year, renewal is {{ $renewal['amount_incl_formatted'] }}, inclusive of applicable GST. The first payment already includes the first year.</p>
  </div>
  <div class="faq">
    <strong>Can I edit my profile after it is published?</strong>
    <p>You can submit two consolidated edit requests in each year counted from publication. Jannayaks reviews each request, and you approve the result before it replaces anything. The published profile stays as it is until that approval. A substantial restructuring or replacement is a full rewrite at {{ $rewrite['base_formatted'] }} plus GST. You cannot publish changes yourself.</p>
  </div>
  <div class="faq">
    <strong>When can I get a refund?</strong>
    <p>You can receive a full refund of the fee you paid before the completed profile is delivered to your dashboard. After that delivery there is no routine refund. A refund recorded by Jannayaks is a credit note until our team initiates it with the payment gateway. See the <a href="{{ route('legal.refund') }}">refund page</a>.</p>
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
    <strong>Is there an online In Memoriam checkout?</strong>
    <p>No. In Memoriam is arranged directly with Jannayaks. Details and contact information are on the In Memoriam service page.</p>
  </div>
  <div class="faq">
    <strong>Where can I see example profiles?</strong>
    <p><a href="{{ route('demo-profiles.index') }}">View Demo Profiles</a> opens a collection of fictional demonstration profiles showing how finished pages read. Published profiles appear in the <a href="{{ route('gallery.index') }}">Gallery</a>.</p>
  </div>

  <p class="back"><a href="{{ route('home') }}">← Back to homepage</a></p>
</main>

@include('partials.public-footer')
@include('partials.google-translate')
</body>
</html>
