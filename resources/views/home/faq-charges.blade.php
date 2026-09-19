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
body{background:var(--off);color:var(--charcoal);font-family:var(--fb);-webkit-font-smoothing:antialiased;padding-top:38px;}
.construction-banner{background:var(--navy);color:#fff;text-align:center;font-size:13px;letter-spacing:2px;text-transform:uppercase;padding:10px;position:fixed;top:0;left:0;right:0;z-index:9999;}
nav{background:var(--white);border-bottom:2px solid var(--saffron);position:sticky;top:38px;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06);}
.nav-inner{display:flex;align-items:center;justify-content:space-between;height:64px;padding:0 22px;max-width:1400px;margin:0 auto;}
.nav-logo{display:flex;align-items:center;gap:10px;text-decoration:none;}
.nav-logo img{height:58px;width:auto;}
.nav-links{display:none;list-style:none;gap:28px;}
.nav-links a{font-size:13px;color:var(--gray);text-decoration:none;font-weight:500;}
.nav-links a:hover,.nav-links a[aria-current="page"]{color:var(--navy);}
.nav-right{display:flex;align-items:center;gap:10px;}
.nav-login{background:transparent;border:1.5px solid var(--navy);color:var(--navy);padding:7px 16px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;font-family:var(--fb);}
.nav-cta{background:var(--saffron);color:white;border:none;padding:8px 18px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;font-family:var(--fb);}
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
</style>
</head>
<body>
<div class="construction-banner">Under Construction</div>
<nav>
  <div class="nav-inner">
    <a class="nav-logo" href="{{ route('home') }}">
      <img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks">
    </a>
    <ul class="nav-links">
      <li><a href="{{ route('home') }}#search">Search</a></li>
      <li><a href="{{ route('home') }}#hiw">How It Works</a></li>
      <li><a href="{{ route('faq-charges') }}" aria-current="page">FAQ &amp; Charges</a></li>
      <li><a href="{{ route('gallery.index') }}">View Demo Profiles</a></li>
    </ul>
    <div class="nav-right">
      <a class="nav-login" href="{{ route('login') }}">Sign In</a>
      <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
    </div>
  </div>
</nav>

<main class="wrap">
  <span class="eyebrow">FAQ &amp; Charges / ചോദ്യങ്ങളും നിരക്കുകളും</span>
  <h1>Membership charges and common questions</h1>
  <p class="lede">
    Package amounts below are the authoritative charges used by the Jannayaks application
    (inclusive of GST at {{ number_format($gstPercent, 0) }}% where the package is GST-inclusive).
    Payment is completed through the application journey after you create a profile.
  </p>

  <div class="card gold">
    <div class="tier-name">Distinguished</div>
    <span class="tier-name-ml">പ്രശസ്തർ</span>
    <div class="price">{{ $distinguished['amount_incl_formatted'] }}</div>
    <div class="price-sub">
      Incl. GST · annual membership {{ $membership['amount_incl_formatted'] }} ·
      post-publication revision {{ $revision['amount_incl_formatted'] }}
    </div>
    <div class="who">
      Senior political leaders, state-level office-bearers, and the most established names in community and cultural leadership
      <span class="who-ml">മുതിർന്ന രാഷ്ട്രീയ നേതാക്കൾ, സംസ്ഥാനതല ഭാരവാഹികൾ, സാമൂഹിക-സാംസ്കാരിക രംഗത്തെ പ്രഗത്ഭർ</span>
    </div>
    <ul class="features">
      <li>Verified profile — Gold badge</li>
      <li>Artificial Intelligence–assisted biography — 700 words (EN + ML)</li>
      <li>Personal URL — jannayaks.in/slug</li>
      <li>Digital visiting card + QR code</li>
      <li>Posts — text, photos &amp; video links</li>
      <li>Featured listing — top of all directories</li>
      <li>Annual profile refresh</li>
      <li>Optional add-on: {{ $distinguishedAddon['label'] }} — {{ $distinguishedAddon['amount_incl_formatted'] }}</li>
    </ul>
  </div>

  <div class="card navy">
    <div class="tier-name">Accomplished</div>
    <span class="tier-name-ml">ജനസമ്മതർ</span>
    <div class="price">{{ $accomplished['amount_incl_formatted'] }}</div>
    <div class="price-sub">
      Incl. GST · annual membership {{ $membership['amount_incl_formatted'] }} ·
      post-publication revision {{ $revision['amount_incl_formatted'] }}
    </div>
    <div class="who">
      Elected and former representatives, and established leaders of organisations, institutions, and community bodies
      <span class="who-ml">തിരഞ്ഞെടുക്കപ്പെട്ടവരും മുൻ ജനപ്രതിനിധികളും, സംഘടനകളുടെയും സ്ഥാപനങ്ങളുടെയും നേതാക്കളും</span>
    </div>
    <ul class="features">
      <li>Verified profile — Verified badge</li>
      <li>Artificial Intelligence–assisted biography — 500 words (EN + ML)</li>
      <li>Personal URL — jannayaks.in/slug</li>
      <li>Digital visiting card + QR code</li>
      <li>Posts — text &amp; photos</li>
      <li>Priority listing above Emerging</li>
      <li>Annual profile refresh</li>
    </ul>
  </div>

  <div class="card">
    <div class="tier-name">Emerging</div>
    <span class="tier-name-ml">ജനകീയർ</span>
    <div class="price">{{ $emerging['amount_incl_formatted'] }}</div>
    <div class="price-sub">
      Incl. GST · annual membership {{ $membership['amount_incl_formatted'] }} ·
      post-publication revision {{ $revision['amount_incl_formatted'] }}
    </div>
    <div class="who">
      Local body members, active workers, and rising figures in community or cultural life
      <span class="who-ml">തദ്ദേശസ്വയംഭരണ സ്ഥാപന അംഗങ്ങൾ, സജീവ പ്രവർത്തകർ, സാമൂഹിക-സാംസ്കാരിക രംഗത്തെ പ്രവർത്തകർ</span>
    </div>
    <ul class="features">
      <li>Verified profile</li>
      <li>Artificial Intelligence–assisted biography — 300 words (EN + ML)</li>
      <li>Personal URL — jannayaks.in/slug</li>
      <li>Digital visiting card + QR code</li>
      <li>Posts — text only</li>
      <li>Standard listing in directories</li>
      <li>Annual profile refresh</li>
    </ul>
  </div>

  <div class="card">
    <div class="tier-name">In Memoriam</div>
    <span class="tier-name-ml">സ്മരണാഞ്ജലി</span>
    <div class="price">{{ $inMemoriam['amount_incl_formatted'] }}</div>
    <div class="price-sub">
      {{ $hostingYears }} years hosting · arranged offline with Jannayaks · see
      <a href="{{ route('in-memoriam.index') }}">/in-memoriam</a>
    </div>
    <div class="who">
      A dignified memorial page prepared with editorial care for the agreed hosting period. There is no online memorial application.
    </div>
  </div>

  <h2>Frequently asked questions</h2>

  <div class="faq">
    <strong>How do I create a profile?</strong>
    <p>Use Create Profile to begin the application journey. You sign in, choose a package, complete payment in the application, then continue with the interview and editorial process.</p>
  </div>
  <div class="faq">
    <strong>When is payment taken?</strong>
    <p>Package payment is collected through the application payment step (Razorpay). Amounts shown here are the same server-side prices used by the application.</p>
  </div>
  <div class="faq">
    <strong>What is annual membership?</strong>
    <p>After publication, membership renews on the annual cycle configured in the application. The current annual membership amount is {{ $membership['amount_incl_formatted'] }}.</p>
  </div>
  <div class="faq">
    <strong>What about profile revisions after publication?</strong>
    <p>Meaningful post-publication revisions use the configured revision charge of {{ $revision['amount_incl_formatted'] }}. Routine factual or typographical corrections follow the editorial process.</p>
  </div>
  <div class="faq">
    <strong>Is there an online In Memoriam checkout?</strong>
    <p>No. In Memoriam is arranged directly with Jannayaks. Details and contact information are on the In Memoriam service page.</p>
  </div>
  <div class="faq">
    <strong>Where can I see example profiles?</strong>
    <p><a href="{{ route('gallery.index') }}">View Demo Profiles</a> opens the public gallery of published profiles.</p>
  </div>

  <p class="back"><a href="{{ route('home') }}">← Back to homepage</a></p>
</main>
</body>
</html>
