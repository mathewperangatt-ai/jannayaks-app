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
    {{-- SEO-1: page metadata via sections; indexing directives unchanged above. --}}
    @include('partials.seo-meta', [
        'title' => trim((string) $__env->yieldContent('title')) ?: (string) config('app.name', 'Jannayaks'),
        // Inline @section values arrive escaped by Blade; decode once here so
        // the partial escapes exactly once at output.
        'description' => htmlspecialchars_decode((string) $__env->yieldContent('seoDescription'), ENT_QUOTES),
        'canonical' => (string) $__env->yieldContent('seoCanonical'),
        'image' => (string) $__env->yieldContent('seoImage'),
        'type' => (string) $__env->yieldContent('seoType'),
        'locale' => str_starts_with($htmlLang ?? 'en', 'ml') ? 'ml_IN' : 'en_IN',
    ])
    {{-- SEO-4: structured data, pre-encoded per page via the schemaJson section. --}}
    @include('partials.schema-ld', ['json' => htmlspecialchars_decode((string) $__env->yieldContent('schemaJson'), ENT_QUOTES)])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,400&family=DM+Sans:wght@300;400;500;600&family=Noto+Serif+Malayalam:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            --navy:#214d68;--saffron:#C0762E;--white:#FFFFFF;--off:#f3f8f0;
            --gray:#6f8075;--light:#eaf2e7;--charcoal:#1f2924;--border:#d3ddd1;
            --gold:#B8860B;--green:#1A6B3C;
            --brand:var(--saffron);--brand-dark:#9d5e22;
            --ink:var(--charcoal);--ink-soft:var(--gray);--paper:var(--off);--paper-alt:var(--light);--line:var(--border);
            --serif:'Fraunces','Cormorant Garamond',Georgia,serif;
            --sans:'DM Sans',sans-serif;
            --mal:'Noto Serif Malayalam',serif;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:var(--off);color:var(--charcoal);font-family:var(--sans);line-height:1.6;-webkit-font-smoothing:antialiased}
        a{color:var(--navy);text-decoration-thickness:1px;text-underline-offset:3px}
        a:hover{color:var(--saffron)}
        :focus-visible{outline:3px solid rgba(192,118,46,.35);outline-offset:2px;border-radius:6px}
                .site-nav{background:#fff;border-bottom:2px solid var(--saffron);position:sticky;top:0;z-index:100;box-shadow:0 1px 12px rgba(0,0,0,.06)}
        .nav-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;height:72px;padding:0 22px;max-width:1400px;margin:0 auto}
        .nav-logo{display:flex;align-items:center;text-decoration:none}
        .nav-logo img{height:58px;width:auto;display:block}
        .nav-links{display:none;list-style:none;gap:22px;margin:0;padding:0}
        .nav-links a{font-size:13px;color:var(--gray);text-decoration:none;font-weight:500}
        .nav-links a:hover,.nav-links a[aria-current="page"]{color:var(--navy)}
        .nav-right{display:flex;align-items:center;gap:10px}
        .nav-login{border:1.5px solid var(--navy);color:var(--navy);padding:7px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;background:transparent}
        .nav-cta{background:var(--saffron);color:#fff;padding:8px 16px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none}
        .nav-cta:hover{background:var(--brand-dark);color:#fff}
        .nav-menu-btn{display:none;border:1.5px solid var(--navy);background:#fff;color:var(--navy);border-radius:4px;font-size:17px;line-height:1;padding:9px 12px;cursor:pointer}
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
        .card-link:hover{border-color:var(--navy);box-shadow:0 4px 20px rgba(33,77,104,.08)}
        .card-photo{aspect-ratio:4/3;background:linear-gradient(145deg,#eaf2e7,#f3f8f0);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative}
        .card-photo img{width:100%;height:100%;object-fit:cover}
        .card-photo .placeholder{font-size:28px;font-weight:700;color:#b7c3b0;font-family:var(--serif)}
        .card-tier{position:absolute;top:10px;left:10px;background:rgba(33,77,104,.92);color:#fff;font:600 9px var(--sans);letter-spacing:.16em;text-transform:uppercase;padding:4px 8px;border-radius:2px}
        .card-tier.memorial{background:rgba(38,38,38,.92)}
        .card-body{padding:16px 16px 18px}
        .card-body h2{margin:0 0 6px;font-size:18px;line-height:1.25;font-family:var(--serif);font-weight:600;color:var(--navy)}
        .card-meta{margin:0;font-size:14px;color:var(--gray)}
        .pager{margin-top:28px;display:flex;justify-content:center}
        .empty{padding:36px 8px;color:var(--gray);text-align:center}
        @media (min-width:900px){.nav-links{display:flex}.nav-menu-btn{display:none !important}}
        @media (max-width:899px){
            .nav-menu-btn{display:inline-flex;align-items:center}
            .nav-right .nav-login{display:none}
            .nav-links{display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border-bottom:2px solid var(--saffron);flex-direction:column;gap:0;padding:8px 0;margin:0;box-shadow:0 12px 24px rgba(0,0,0,.08)}
            .nav-links.open{display:flex}
            .nav-links li{list-style:none;width:100%}
            .nav-links a{display:block;padding:13px 22px;font-size:15px}
        }
        @media (max-width:640px){
            .site{padding:18px 14px 48px}
            .nav-inner{height:64px}
            .nav-logo img{height:48px}
        }
    </style>
    @stack('head')
</head>
<body>
    <header class="site-nav">
        <div class="nav-inner">
            <a class="nav-logo" href="{{ route('home') }}">
                <img src="{{ asset('branding/jannayaks-logo.jpg') }}" alt="Jannayaks.in">
            </a>
            <button type="button" class="nav-menu-btn" id="publicNavBtn" aria-expanded="false" aria-controls="publicNavLinks" aria-label="Open menu">☰</button>
            <ul class="nav-links" id="publicNavLinks">
                <li><a href="{{ route('home') }}#search" data-nav-close>Search</a></li>
                <li><a href="{{ route('home') }}#hiw" data-nav-close>How It Works</a></li>
                <li><a href="{{ route('faq-charges') }}" @if(($nav ?? '') === 'faq') aria-current="page" @endif data-nav-close>FAQ &amp; Charges</a></li>
                <li><a href="{{ route('gallery.index') }}" @if(($nav ?? '') === 'gallery') aria-current="page" @endif data-nav-close>View Demo Profiles</a></li>
                <li><a href="{{ route('in-memoriam.index') }}" @if(($nav ?? '') === 'in-memoriam') aria-current="page" @endif data-nav-close>In Memoriam</a></li>
                <li><a href="{{ route('login') }}" data-nav-close>Sign In</a></li>
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
    @include('partials.public-footer')    <script>
    (function(){
        var btn = document.getElementById('publicNavBtn');
        var links = document.getElementById('publicNavLinks');
        if (!btn || !links) return;
        function setOpen(open){
            links.classList.toggle('open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
        btn.addEventListener('click', function(e){
            e.stopPropagation();
            setOpen(!links.classList.contains('open'));
        });
        document.addEventListener('click', function(e){
            if (!e.target.closest('.site-nav')) setOpen(false);
        });
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') setOpen(false);
        });
        links.querySelectorAll('[data-nav-close]').forEach(function(a){
            a.addEventListener('click', function(){ setOpen(false); });
        });
    })();
    </script>
</body>
</html>
