<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $htmlLang ?? 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/favicon-180.png') }}">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}">
    <title>@yield('title', config('app.name', 'Jannayaks'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --navy:#0F1F3D;--saffron:#C65102;--white:#FFFFFF;--off:#F8F6F2;
            --gray:#6B7280;--light:#F3F4F6;--charcoal:#1F2937;--border:#E5E7EB;
            --gold:#B8860B;--green:#1A6B3C;
            --brand:var(--saffron);--brand-dark:#a84400;
            --ink:var(--charcoal);--ink-soft:var(--gray);--paper:var(--off);--paper-alt:#EEF0F8;--line:var(--border);
            --serif:'Cormorant Garamond',Georgia,serif;
            --sans:'DM Sans',sans-serif;
            --mal:'Noto Serif Malayalam',serif;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:var(--off);color:var(--charcoal);font-family:var(--sans);line-height:1.6;-webkit-font-smoothing:antialiased}
        body{padding-top:38px}
        a{color:var(--navy);text-decoration-thickness:1px;text-underline-offset:3px}
        a:hover{color:var(--saffron)}
        :focus-visible{outline:3px solid rgba(198,81,2,.35);outline-offset:2px;border-radius:6px}
        .construction-banner{background:var(--navy);color:#fff;text-align:center;font-size:13px;letter-spacing:2px;text-transform:uppercase;padding:10px;position:fixed;top:0;left:0;right:0;z-index:9999}
        .site-nav{background:#fff;border-bottom:2px solid var(--saffron);position:sticky;top:38px;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06)}
        .nav-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;height:72px;padding:0 22px;max-width:1400px;margin:0 auto}
        .nav-logo{display:flex;align-items:center;text-decoration:none}
        .nav-logo img{height:58px;width:auto;display:block}
        .nav-links{display:none;list-style:none;gap:22px;margin:0;padding:0}
        .nav-links a{font-size:13px;color:var(--gray);text-decoration:none;font-weight:500}
        .nav-links a:hover,.nav-links a[aria-current="page"]{color:var(--navy)}
        .nav-right{display:flex;align-items:center;gap:10px}
        .nav-login{border:1.5px solid var(--navy);color:var(--navy);padding:7px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none}
        .nav-cta{background:var(--saffron);color:#fff;padding:8px 16px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none}
        .nav-cta:hover{background:var(--brand-dark);color:#fff}
        .site{max-width:1100px;margin:0 auto;padding:28px 22px 56px}
        .eyebrow{font-size:10px;letter-spacing:3px;text-transform:uppercase;color:var(--saffron);font-weight:600;margin:0 0 10px}
        .lede{font-size:15px;color:var(--gray);margin:0 0 22px;max-width:42rem;line-height:1.7}
        .search-panel{background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:18px;margin-bottom:28px;box-shadow:0 4px 24px rgba(0,0,0,.04)}
        .search-panel label{display:block;font-size:13px;font-weight:700;margin-bottom:8px;color:var(--navy)}
        .search-row{display:flex;gap:10px;flex-wrap:wrap}
        .search-row input[type=search],.search-row select{flex:1;min-width:180px;min-height:46px;padding:10px 14px;border:1.5px solid var(--border);border-radius:8px;background:#fff;font:inherit}
        .search-row button{min-height:46px;padding:10px 18px;border:0;border-radius:8px;background:var(--navy);color:#fff;font-weight:600;cursor:pointer}
        .search-row button:hover{background:var(--saffron)}
        .filters{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
        .filters select{min-height:40px;padding:8px 12px;border:1.5px solid var(--border);border-radius:8px;background:#fff;font:inherit;font-size:14px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
        .card-link{display:block;color:inherit;text-decoration:none;background:#fff;border:1.5px solid var(--border);border-radius:10px;overflow:hidden;transition:border-color .15s ease,box-shadow .15s ease}
        .card-link:hover{border-color:var(--navy);box-shadow:0 4px 20px rgba(15,31,61,.08)}
        .card-photo{aspect-ratio:4/3;background:linear-gradient(145deg,#EEF0F8,#F8F6F2);display:flex;align-items:center;justify-content:center;overflow:hidden}
        .card-photo img{width:100%;height:100%;object-fit:cover}
        .card-photo .placeholder{font-size:28px;font-weight:700;color:#b7aea1;font-family:var(--serif)}
        .card-body{padding:16px 16px 18px}
        .card-body h2{margin:0 0 6px;font-size:18px;line-height:1.25;font-family:var(--serif);font-weight:700;color:var(--navy)}
        .card-meta{margin:0;font-size:14px;color:var(--gray)}
        .pager{margin-top:28px;display:flex;justify-content:center}
        .empty{padding:36px 8px;color:var(--gray);text-align:center}
        .site-footer{background:var(--navy);color:rgba(255,255,255,.55);padding:36px 22px 24px;margin-top:24px}
        .site-footer-inner{max-width:1100px;margin:0 auto;display:flex;flex-wrap:wrap;gap:24px;justify-content:space-between;align-items:flex-start}
        .site-footer img{height:48px;width:auto;filter:brightness(0) invert(1);opacity:.85}
        .site-footer a{color:rgba(255,255,255,.55);text-decoration:none;font-size:13px}
        .site-footer a:hover{color:var(--saffron)}
        .site-footer nav{display:flex;flex-wrap:wrap;gap:14px}
        .site-footer .copy{font-size:11px;color:rgba(255,255,255,.3);margin-top:20px;width:100%}
        @media (min-width:900px){.nav-links{display:flex}}
        @media (max-width:640px){
            .site{padding:18px 14px 48px}
            .nav-inner{height:64px}
            .nav-logo img{height:48px}
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="construction-banner">Under Construction</div>
    <header class="site-nav">
        <div class="nav-inner">
            <a class="nav-logo" href="{{ route('home') }}">
                <img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks.in">
            </a>
            <ul class="nav-links">
                <li><a href="{{ route('home') }}#search">Search</a></li>
                <li><a href="{{ route('home') }}#hiw">How It Works</a></li>
                <li><a href="{{ route('faq-charges') }}" @if(($nav ?? '') === 'faq') aria-current="page" @endif>FAQ &amp; Charges</a></li>
                <li><a href="{{ route('gallery.index') }}" @if(($nav ?? '') === 'gallery') aria-current="page" @endif>View Demo Profiles</a></li>
                <li><a href="{{ route('in-memoriam.index') }}" @if(($nav ?? '') === 'in-memoriam') aria-current="page" @endif>In Memoriam</a></li>
            </ul>
            <div class="nav-right">
                <a class="nav-login" href="{{ route('login') }}">Sign In</a>
                <a class="nav-cta" href="{{ route('apply') }}">Create Profile</a>
            </div>
        </div>
    </header>
    <div class="site">
        <main id="main">
            @yield('content')
        </main>
    </div>
    <footer class="site-footer">
        <div class="site-footer-inner">
            <a href="{{ route('home') }}"><img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks.in"></a>
            <nav aria-label="Footer">
                <a href="{{ route('gallery.index') }}">Gallery</a>
                <a href="{{ route('search.index') }}">Search</a>
                <a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a>
                <a href="{{ route('in-memoriam.index') }}">In Memoriam</a>
                <a href="mailto:{{ config('jannayaks.contact.public_email') }}">Contact</a>
            </nav>
            <div class="copy">{{ config('jannayaks.contact.legal_address') }} · © {{ date('Y') }} Jannayaks™ · Aurex Network. Apolitical. Verified.</div>
        </div>
    </footer>
</body>
</html>
