@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => $isDemonstration ? 'demo-profiles' : 'gallery'])
@php($listingUrl = $isDemonstration ? route('demo-profiles.index') : route('gallery.index'))

@section('title', $displayName.' — Jannayaks')

@section('seoDescription', \Illuminate\Support\Str::limit(trim((string) ($activeEditorial?->summary ?: $headline ?: $profession)), 300))
@section('seoCanonical', $canonicalUrl)
@section('seoImage', $photo ? route('profiles.public.photo', [$profile, $photo]) : '')
@section('seoType', 'profile')

@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->profileGraph(
    $canonicalUrl,
    $displayName,
    trim((string) ($activeEditorial?->summary ?? '')) !== '' ? trim((string) $activeEditorial->summary) : null,
    $photo ? route('profiles.public.photo', [$profile, $photo]) : null,
    filled($profession) ? $profession : null,
    [['Home', route('home')], ['Demo Profiles', $listingUrl], [$displayName, $canonicalUrl]],
)))

@push('head')
<style>
    /* ————— Approved profile visual standard —————
       Sage ground, deep blue serif typography, restrained saffron accents,
       framed portrait, editorial columns. Scoped under .jk-page. */
    .jk-page{--jk-bg:#f3f8f0;--jk-surface:#f7faf5;--jk-surface2:#eaf2e7;--jk-blue:#214d68;--jk-blue2:#52758a;
        --jk-saffron:#c77e2e;--jk-green:#86a982;--jk-ink:#1f2924;--jk-muted:#6f8075;--jk-line:#d3ddd1;
        --jk-serif:'Fraunces','Cormorant Garamond',Georgia,serif;--jk-ui:'DM Sans',system-ui,sans-serif;
        background:var(--jk-bg);color:var(--jk-ink);margin:-28px -22px -56px;padding:0}
    .jk-wrap{max-width:1180px;margin:0 auto;padding:26px clamp(18px,4vw,42px) 72px}
    .jk-crumbs{font:500 11px var(--jk-ui);letter-spacing:.14em;text-transform:uppercase;color:var(--jk-muted);margin:0 0 26px}
    .jk-crumbs a{color:var(--jk-muted);text-decoration:none}
    .jk-crumbs a:hover{color:var(--jk-blue)}
    .jk-crumbs span[aria-current]{color:var(--jk-blue)}

    /* hero */
    .jk-hero{display:grid;grid-template-columns:minmax(0,42fr) minmax(0,58fr);gap:clamp(28px,4.5vw,58px);align-items:center;padding:8px 0 64px;border-bottom:1px solid var(--jk-line)}
    .jk-portrait-wrap{position:relative;padding:10px;border:1px solid #bdcfba;max-width:430px;background:var(--jk-surface)}
    .jk-portrait{width:100%;aspect-ratio:3/4;object-fit:cover;display:block;border:1px solid #aab9a6;background:var(--jk-surface2)}
    /* Demonstration portraits carry a diagonal "AI GENERATED" watermark that must stay fully visible. */
    .jk-portrait.jk-portrait--whole,.jk-side-feature.jk-portrait--whole{aspect-ratio:auto;height:auto;object-fit:contain}
    .jk-portrait-mono{width:100%;aspect-ratio:3/4;display:flex;align-items:center;justify-content:center;border:1px solid #aab9a6;background:linear-gradient(160deg,#e7efe4,#dbe7d7);font:500 clamp(64px,7vw,110px) var(--jk-serif);color:#9db3a4}
    .jk-caption{display:flex;justify-content:space-between;gap:12px;font:500 9.5px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:#82907f;padding-top:13px}
    .jk-eyebrow{font:600 11px var(--jk-ui);letter-spacing:.22em;text-transform:uppercase;color:var(--jk-saffron);margin:0}
    .jk-name{font:500 clamp(32px,3.9vw,54px)/1.04 var(--jk-serif);color:var(--jk-blue);margin:20px 0 8px;letter-spacing:-.01em}
    .jk-name span{color:var(--jk-saffron)}
    .jk-org{font:italic clamp(17px,1.7vw,21px) var(--jk-serif);color:var(--jk-blue2);margin:0 0 12px}
    .jk-location{font:500 10px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:var(--jk-muted);margin:0 0 26px}
    .jk-intro{font-size:clamp(17px,1.8vw,19px);line-height:1.6;border-left:5px solid #8eaf88;padding:4px 0 4px 22px;max-width:620px;margin:0}
    .jk-url-label{font:600 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#7d9b79;margin:30px 0 8px}
    .jk-urlrow{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .jk-urlpill,.jk-copy{height:42px;border:1px solid #b8c9b5;background:rgba(255,255,255,.36);padding:0 15px;display:inline-flex;align-items:center;font:500 12.5px var(--jk-ui);color:var(--jk-blue);border-radius:2px}
    .jk-copy{background:var(--jk-blue);color:#fff;border-color:var(--jk-blue);cursor:pointer;text-transform:uppercase;letter-spacing:.1em;font-size:10px;font-weight:600}
    .jk-copy:hover{background:var(--jk-saffron);border-color:var(--jk-saffron)}
    .jk-lang{display:inline-flex;border:1px solid #b8c9b5;border-radius:2px;margin-left:6px;background:rgba(255,255,255,.36)}
    .jk-lang a{height:40px;display:inline-flex;align-items:center;padding:0 16px;font:500 13.5px var(--jk-ui);color:var(--jk-blue);text-decoration:none}
    .jk-lang a + a{border-left:1px solid #b8c9b5}
    .jk-lang a[lang="ml"]{font-family:'Noto Serif Malayalam',var(--jk-ui)}
    .jk-lang a:hover{background:#fff}
    .jk-lang a[aria-current="true"]{color:var(--jk-saffron);font-weight:600;background:#fffdf8;box-shadow:inset 0 -2px 0 var(--jk-saffron)}
    .jk-demo-note{margin:16px 0 0;font:500 11px var(--jk-ui);color:var(--jk-muted);letter-spacing:.06em}

    /* well-wisher rail: contact + reactions */
    .jk-rail{grid-column:3}
    .jk-hero.with-rail{grid-template-columns:minmax(0,30fr) minmax(0,48fr) minmax(0,22fr)}
    .jk-box{background:var(--jk-surface);border:1px solid var(--jk-line);padding:22px;margin-bottom:16px}
    .jk-box h2{font:600 12px var(--jk-ui);letter-spacing:.18em;text-transform:uppercase;color:var(--jk-blue);margin:0 0 6px}
    .jk-box .jk-box-sub{font:400 12.5px/1.55 var(--jk-ui);color:var(--jk-muted);margin:0 0 14px}
    .jk-field{margin-bottom:10px}
    .jk-field label{display:block;font:600 10px var(--jk-ui);letter-spacing:.12em;text-transform:uppercase;color:var(--jk-muted);margin-bottom:4px}
    .jk-field input,.jk-field textarea{width:100%;padding:9px 11px;border:1px solid #c3d2c0;background:#fff;font:400 13.5px var(--jk-ui);color:var(--jk-ink);border-radius:2px}
    .jk-field textarea{min-height:74px;resize:vertical}
    .jk-send{width:100%;padding:10px 14px;border:0;background:var(--jk-blue);color:#fff;font:600 11px var(--jk-ui);letter-spacing:.12em;text-transform:uppercase;cursor:pointer;border-radius:2px}
    .jk-send:hover{background:var(--jk-saffron)}
    .jk-privacy{font:400 11px/1.5 var(--jk-ui);color:var(--jk-muted);margin:10px 0 0}
    .jk-status{margin:0 0 12px;padding:9px 12px;background:#e7f0e4;border-left:3px solid var(--jk-green);font:500 12.5px var(--jk-ui);color:#33553f}
    .jk-status.err{background:#f6ebe6;border-left-color:#b3652f;color:#6b3a1d}
    .jk-reactions{display:flex;gap:8px}
    .jk-react{flex:1;padding:10px 8px;border:1px solid #b8c9b5;background:var(--jk-surface);font:600 12px var(--jk-ui);color:var(--jk-blue);cursor:pointer;border-radius:2px}
    .jk-react:hover{border-color:var(--jk-saffron);color:var(--jk-saffron)}
    .jk-react.on{background:var(--jk-blue);border-color:var(--jk-blue);color:#fff}
    .jk-react-login{display:block;text-align:center;padding:10px;border:1px dashed #b8c9b5;color:var(--jk-muted);font:500 12px var(--jk-ui);text-decoration:none;border-radius:2px}
    .jk-react-login:hover{color:var(--jk-blue)}

    /* sections */
    .jk-section{padding-top:64px}
    .jk-sectionhead{display:grid;grid-template-columns:auto auto 1fr auto;gap:16px;align-items:center;border-bottom:1px solid var(--jk-line);padding-bottom:12px;margin-bottom:36px}
    .jk-num{font:500 25px var(--jk-serif);color:var(--jk-saffron);line-height:1}
    .jk-dash{height:1px;width:28px;background:var(--jk-saffron)}
    .jk-sectionhead h2{font:500 clamp(26px,3.4vw,42px)/1.05 var(--jk-serif);color:var(--jk-blue);margin:0}
    .jk-tail{font:600 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#6e9180;justify-self:end;text-align:right}
    .jk-bio-grid{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:clamp(28px,4vw,55px);align-items:start}
    .jk-bodytext{max-width:600px;font-size:16.5px;line-height:1.78}
    .jk-bodytext p{margin:0 0 24px}
    .jk-bodytext p:first-child::first-letter{font:500 3.4em/0.82 var(--jk-serif);float:left;padding:8px 10px 0 0;color:var(--jk-saffron)}
    .jk-summary-quote{background:var(--jk-surface2);padding:26px 28px 20px;color:var(--jk-blue);font:italic 20px/1.45 var(--jk-serif);position:relative;margin:0 0 26px}
    .jk-summary-quote::before{content:"\201C";font-size:64px;line-height:1;position:absolute;left:18px;top:2px;color:#c8d8c4}
    .jk-summary-quote p{position:relative;z-index:1;margin:0;padding-top:22px}
    .jk-side-feature{width:100%;aspect-ratio:16/10;object-fit:cover;display:block;border:1px solid var(--jk-line);background:var(--jk-surface2)}
    .jk-figcap{font:500 9px var(--jk-ui);letter-spacing:.2em;text-transform:uppercase;color:#81907e;margin-top:8px}

    /* ledger + lists */
    .jk-ledgerrow{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:26px;padding:20px 0;border-bottom:1px solid var(--jk-line)}
    .jk-ledgerrow:first-of-type{border-top:1px solid var(--jk-line)}
    .jk-ledgerrow strong{font:500 17px var(--jk-serif);color:var(--jk-blue);display:block}
    .jk-ledgerrow .where{font:500 10px var(--jk-ui);letter-spacing:.14em;text-transform:uppercase;color:var(--jk-muted);margin:3px 0 0}
    .jk-ledgerrow span.d{font-size:15px;color:#46554d;line-height:1.66;display:block;margin-top:6px}
    .jk-item{display:grid;grid-template-columns:64px minmax(0,1fr);gap:18px;padding:20px 0;border-bottom:1px solid var(--jk-line)}
    .jk-item:first-of-type{border-top:1px solid var(--jk-line)}
    .jk-code{font:600 10px var(--jk-ui);letter-spacing:.16em;color:var(--jk-saffron);padding-top:5px}

    /* gallery */
    .jk-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px}
    .jk-gallery a{display:block;border:1px solid #bdcfba;background:var(--jk-surface);text-decoration:none}
    .jk-gallery img{width:100%;aspect-ratio:4/5;object-fit:cover;display:block}
    .jk-note{padding:22px 26px;background:var(--jk-surface2);color:var(--jk-blue);font-size:16.5px;line-height:1.6}

    .jk-videos{margin:0;padding:0;list-style:none}
    .jk-videos li{padding:14px 0;border-bottom:1px solid var(--jk-line)}
    .jk-videos a{font:600 15px var(--jk-ui);color:var(--jk-blue)}

    /* Malayalam typography */
    .jk-page.lang-ml .jk-bodytext,.jk-page.lang-ml .jk-intro{font-family:'Noto Serif Malayalam',var(--jk-serif);line-height:1.95}
    .jk-page.lang-ml .jk-bodytext p:first-child::first-letter{font-size:2.5em;line-height:1.1;padding-top:6px}
    .jk-page.lang-ml .jk-name{font-family:'Noto Serif Malayalam',var(--jk-serif);line-height:1.25}
    .jk-page.lang-ml .jk-sectionhead h2{font-family:'Noto Serif Malayalam',var(--jk-serif)}
    .jk-page.lang-ml .jk-summary-quote{font-style:normal;line-height:1.8}

    /* mobile */
    @media(max-width:1020px){
        .jk-hero.with-rail{grid-template-columns:minmax(0,42fr) minmax(0,58fr)}
        .jk-rail{grid-column:1/-1;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
    }
    @media(max-width:720px){
        .jk-page{margin:-18px -14px -48px}
        .jk-hero,.jk-hero.with-rail{grid-template-columns:1fr;gap:30px}
        .jk-portrait-wrap{max-width:340px;margin:0 auto}
        .jk-rail{grid-template-columns:1fr}
        .jk-bio-grid{grid-template-columns:1fr}
        .jk-urlrow{flex-direction:column;align-items:stretch}
        .jk-urlpill,.jk-copy{justify-content:center;width:100%}
        .jk-section{padding-top:48px}
        .jk-sectionhead{grid-template-columns:auto auto 1fr}
        .jk-tail{grid-column:3}
        .jk-ledgerrow{grid-template-columns:1fr;gap:8px}
        .jk-bodytext{font-size:15.5px}
    }
</style>
@endpush

@section('content')
@php($nameWords = preg_split('/\s+/u', trim($displayName), -1, PREG_SPLIT_NO_EMPTY))
@php($nameFirst = implode(' ', array_slice($nameWords, 0, -1)))
@php($nameLast = $nameWords ? array_pop($nameWords) : '')
<div class="jk-page {{ $language === 'ml' ? 'lang-ml' : '' }}">
<div class="jk-wrap">

    <nav class="jk-crumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> / <a href="{{ $listingUrl }}">Demo Profiles</a> /
        <span aria-current="page">{{ $displayName }}</span>
    </nav>

    <section class="jk-hero with-rail">
        <figure style="margin:0">
            <div class="jk-portrait-wrap">
                @if($photo)
                    <img class="jk-portrait{{ $isDemonstration ? ' jk-portrait--whole' : '' }}" src="{{ route('profiles.public.photo', [$profile, $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$displayName) }}">
                @else
                    <div class="jk-portrait-mono" aria-hidden="true">{{ mb_strtoupper(mb_substr(trim($nameLast !== '' ? $nameLast : $displayName), 0, 1)) }}</div>
                @endif
                @include('public.partials.tier-mark', ['tier' => $profile->application?->package_tier])
            </div>
            <figcaption class="jk-caption">
                <span>{{ $locationLabel ? 'KERALAM · INDIA' : 'PUBLIC LIFE' }}</span>
                <span>JANNAYAKS</span>
            </figcaption>
        </figure>

        <div>
            @if($headline || $profession)
                <p class="jk-eyebrow">{{ $headline ?: $profession }}</p>
            @endif
            <h1 class="name jk-name">@if($nameFirst !== ''){{ $nameFirst }} @endif<span>{{ $nameLast }}</span></h1>
            @if($profession && $headline)
                <p class="jk-org">{{ $profession }}</p>
            @endif
            @if($locationLabel)
                <p class="jk-location">{{ $locationLabel }}</p>
            @endif

            @if($activeEditorial && filled($activeEditorial->summary))
                <p class="jk-intro">{{ $activeEditorial->summary }}</p>
            @endif

            <div class="jk-url-label">Permanent profile URL</div>
            <div class="jk-urlrow">
                <span class="jk-urlpill">{{ $canonicalUrl }}</span>
                <span style="display:inline-flex;gap:8px">
                    <button type="button" class="jk-copy" data-copy="{{ $canonicalUrl }}">Copy link</button>
                    <button type="button" class="jk-copy" style="background:transparent;color:var(--jk-blue)" data-share="{{ $canonicalUrl }}" data-share-title="{{ $displayName }} — Jannayaks">Share</button>
                </span>
                @if($malayalam)
                    <span class="jk-lang" role="navigation" aria-label="Language">
                        <a href="{{ route('profiles.public', ['slug' => $profile->slug, 'lang' => 'en']) }}" lang="en" @if($language === 'en') aria-current="true" @endif>English</a>
                        <a href="{{ route('profiles.public', ['slug' => $profile->slug, 'lang' => 'ml']) }}" lang="ml" @if($language === 'ml') aria-current="true" @endif>മലയാളം</a>
                    </span>
                @endif
            </div>

            @if($isDemonstration)
                <p class="jk-demo-note">Fictional demonstration profile — created to show how a Jannayaks profile reads. It does not describe a real person.</p>
            @endif
        </div>

        {{-- Well-wisher rail: Contact box + Like / Applaud (first laptop viewport) --}}
        <aside class="jk-rail" id="wellwishers">
            <div class="jk-box">
                <h2>Contact</h2>
                <p class="jk-box-sub">Send a brief message to {{ $displayName }}. Your details are shared only with the profile owner — never displayed publicly.</p>
                @if(session('contact_status') === 'sent')
                    <p class="jk-status">Thank you. Your message has been passed on to {{ $displayName }}.</p>
                @endif
                @if($errors->any())
                    <p class="jk-status err">{{ $errors->first('contact') ?: 'Please review the highlighted fields and try again.' }}</p>
                @endif
                <form method="POST" action="{{ route('profiles.contact', $profile->slug) }}">
                    @csrf
                    <input type="text" name="honey_bot" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">
                    <div class="jk-field">
                        <label for="visitor_name">Your name</label>
                        <input id="visitor_name" name="visitor_name" maxlength="120" required value="{{ old('visitor_name') }}">
                    </div>
                    <div class="jk-field">
                        <label for="visitor_mobile">Your mobile number</label>
                        <input id="visitor_mobile" name="visitor_mobile" maxlength="20" required inputmode="tel" value="{{ old('visitor_mobile') }}">
                    </div>
                    <div class="jk-field">
                        <label for="message">Message / reason for contact</label>
                        <textarea id="message" name="message" maxlength="1000" required>{{ old('message') }}</textarea>
                    </div>
                    <button type="submit" class="jk-send">Send message</button>
                </form>
            </div>

            <div class="jk-box">
                <h2>Show appreciation</h2>
                <p class="jk-box-sub">No public counts — only {{ $displayName }} will privately see your name and broad location.</p>
                @if(auth()->check())
                    <div class="jk-reactions">
                        <form method="POST" action="{{ route('profiles.react', $profile->slug) }}">
                            @csrf
                            <input type="hidden" name="reaction" value="like">
                            @php($liked = in_array('like', $viewerReactions))
                            <button type="submit" class="jk-react {{ $liked ? 'on' : '' }}" aria-pressed="{{ $liked ? 'true' : 'false' }}">♡ Like</button>
                        </form>
                        <form method="POST" action="{{ route('profiles.react', $profile->slug) }}">
                            @csrf
                            <input type="hidden" name="reaction" value="applaud">
                            @php($applauded = in_array('applaud', $viewerReactions))
                            <button type="submit" class="jk-react {{ $applauded ? 'on' : '' }}" aria-pressed="{{ $applauded ? 'true' : 'false' }}">👏 Applaud</button>
                        </form>
                    </div>
                    <p class="jk-privacy">Your name and broad location may be visible to the profile owner. Your phone number and email are never shared because of a reaction.</p>
                @else
                    <a class="jk-react-login" href="{{ route('login', ['return' => url()->current()]) }}">Sign in to Like or Applaud →</a>
                    <p class="jk-privacy">A lightweight sign-in (Google or mobile OTP) is required, so profile owners see genuine well-wishers only.</p>
                @endif
            </div>
        </aside>
    </section>

    @if($activeEditorial && filled($activeEditorial->body))
        <section class="jk-section" id="biography">
            <div class="jk-sectionhead">
                <span class="jk-num">I</span><span class="jk-dash"></span>
                <h2>{{ $language === 'ml' ? 'ജീവചരിത്രം' : 'Biography' }}</h2>
                <span class="jk-tail">{{ $profession ?: ($headline ?: 'Public Life') }}</span>
            </div>
            <div class="jk-bio-grid">
                <div class="jk-bodytext">
                    @foreach(preg_split('/\n\s*\n/u', trim((string) $activeEditorial->body)) as $para)
                        @if(trim($para) !== '')<p>{{ trim($para) }}</p>@endif
                    @endforeach
                </div>
                <aside>
                    @if($photo)
                        <img class="jk-side-feature{{ $isDemonstration ? ' jk-portrait--whole' : '' }}" src="{{ route('profiles.public.photo', [$profile, $photo]) }}" alt="{{ $photo->alt_text ?: ($displayName.' — documentary photograph') }}">
                        <p class="jk-figcap">{{ strtoupper($displayName) }} · DOCUMENTARY RECORD</p>
                    @else
                        <div class="jk-summary-quote"><p>{{ $activeEditorial->summary ?: ($headline ?: 'A carefully prepared record of a life of public contribution.') }}</p></div>
                    @endif
                </aside>
            </div>
        </section>
    @endif

    @if($publicOffices->isNotEmpty())
        <section class="jk-section" id="career">
            <div class="jk-sectionhead">
                <span class="jk-num">II</span><span class="jk-dash"></span>
                <h2>{{ $language === 'ml' ? 'കരിയറും ഉത്തരവാദിത്തങ്ങളും' : 'Career &amp; Responsibilities' }}</h2>
                <span class="jk-tail">PUBLIC LIFE</span>
            </div>
            <div>
                @foreach($publicOffices as $office)
                    <div class="jk-ledgerrow">
                        <div>
                            <strong>{{ $office->office_name }}</strong>
                            @if(filled($office->where_location))
                                <p class="where">{{ $office->where_location }}</p>
                            @endif
                        </div>
                        @if(filled($office->term_summary))
                            <span class="d">{{ $office->term_summary }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($photos->count() > 1)
        <section class="jk-section" id="gallery">
            <div class="jk-sectionhead">
                <span class="jk-num">{{ $publicOffices->isNotEmpty() ? 'III' : 'II' }}</span><span class="jk-dash"></span>
                <h2>{{ $language === 'ml' ? 'ഗാലറി' : 'Gallery' }}</h2>
                <span class="jk-tail">DOCUMENTARY PHOTOGRAPHS</span>
            </div>
            <div class="jk-gallery">
                @foreach($photos->skip(1) as $galleryPhoto)
                    <a href="{{ route('profiles.public.photo', [$profile, $galleryPhoto]) }}">
                        <img src="{{ route('profiles.public.photo', [$profile, $galleryPhoto]) }}" alt="{{ $galleryPhoto->alt_text ?: ($displayName.' photograph') }}" loading="lazy">
                    </a>
                @endforeach
            </div>
            <p class="jk-note" style="margin-top:16px">Photographs are held as documentary records — captioned and preserved alongside the written record.</p>
        </section>
    @endif

    @if($videoLinks->isNotEmpty())
        <section class="jk-section" id="video">
            <div class="jk-sectionhead">
                <span class="jk-num">{{ $photos->count() > 1 ? 'IV' : ($publicOffices->isNotEmpty() ? 'III' : 'II') }}</span><span class="jk-dash"></span>
                <h2>Video</h2>
                <span class="jk-tail">EXTERNAL LINKS</span>
            </div>
            <ul class="jk-videos">
                @foreach($videoLinks as $video)
                    <li>
                        <a href="{{ $video->url }}" rel="noopener noreferrer" target="_blank">{{ $video->label ?: 'Watch external video' }}</a>
                        <span style="display:block;margin-top:3px;font:400 12px var(--jk-ui);color:var(--jk-muted)">External link — opens in a new tab</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <p style="margin-top:56px"><a href="{{ $listingUrl }}" style="font:600 12px var(--jk-ui);letter-spacing:.08em;color:var(--jk-blue)">← Back to Demo Profiles</a></p>
</div>
</div>

@push('scripts')
<script>
(function(){
    var copied = 'Link copied';
    function toast(msg){
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;left:50%;bottom:26px;transform:translateX(-50%);background:#214d68;color:#fff;padding:10px 18px;font:500 13px sans-serif;z-index:60;opacity:0;transition:opacity .2s';
        document.body.appendChild(t);
        requestAnimationFrame(function(){ t.style.opacity = '1'; });
        setTimeout(function(){ t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 300); }, 1800);
    }
    function copyText(text){
        if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text).then(function(){ toast(copied); });
        var ta = document.createElement('textarea');
        ta.value = text; ta.setAttribute('readonly',''); ta.style.cssText = 'position:fixed;opacity:0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); toast(copied); } catch(e){}
        ta.remove();
        return Promise.resolve();
    }
    document.querySelectorAll('[data-copy]').forEach(function(b){
        b.addEventListener('click', function(){ copyText(b.dataset.copy); });
    });
    document.querySelectorAll('[data-share]').forEach(function(b){
        b.addEventListener('click', function(){
            if (navigator.share) { navigator.share({ title: b.dataset.shareTitle, url: b.dataset.share }).catch(function(){}); }
            else { copyText(b.dataset.share); }
        });
    });
})();
</script>
@endpush
@endsection
