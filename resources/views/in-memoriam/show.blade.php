@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => 'in-memoriam'])

@section('title', $displayName.' — In Memoriam — Jannayaks')

@section('seoDescription', \Illuminate\Support\Str::limit(trim((string) ($activeEditorial?->summary ?: $headline ?: $profession)), 300))
@section('seoCanonical', $canonicalUrl)
@section('seoImage', $photo ? route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $photo]) : '')
@section('seoType', 'profile')

@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->profileGraph(
    $canonicalUrl,
    $displayName,
    ($activeEditorial && filled($activeEditorial->summary) && $activeEditorial->summary !== ($headline ?: $profession)) ? trim((string) $activeEditorial->summary) : null,
    $photo ? route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $photo]) : null,
    filled($headline) ? $headline : $profession,
    [['Home', route('home')], ['In Memoriam', route('in-memoriam.index')], [$displayName, $canonicalUrl]],
    $profile->deceased_date_of_birth?->format('Y'),
    $profile->deceased_date_of_death?->format('Y'),
)))

@push('head')
<style>
    /* ————— Sombre memorial treatment —————
       Quiet charcoal palette, desaturated framing, generous whitespace.
       Respectful and permanent — not celebratory, not theatrical. */
    .im-page{--im-bg:#f2f2f0;--im-surface:#fafaf8;--im-ink:#26262a;--im-soft:#6a6a70;--im-mute:#8f8f95;
        --im-line:#d8d8d4;--im-deep:#3c3c42;--im-serif:'Fraunces','Cormorant Garamond',Georgia,serif;
        background:var(--im-bg);color:var(--im-ink);margin:-28px -22px -56px;padding:0}
    .im-wrap{max-width:880px;margin:0 auto;padding:26px clamp(18px,4vw,42px) 72px}
    .im-crumbs{font:500 11px var(--sans);letter-spacing:.14em;text-transform:uppercase;color:var(--im-mute);margin:0 0 30px}
    .im-crumbs a{color:var(--im-mute);text-decoration:none}
    .im-crumbs a:hover{color:var(--im-deep)}
    .im-designation{display:flex;align-items:center;gap:14px;margin:0 0 34px}
    .im-designation .rule{height:1px;flex:1;background:var(--im-line)}
    .im-designation span{font:600 10.5px var(--sans);letter-spacing:.26em;text-transform:uppercase;color:var(--im-soft)}
    .im-hero{display:grid;grid-template-columns:230px minmax(0,1fr);gap:clamp(28px,4vw,52px);align-items:start;padding-bottom:44px;border-bottom:1px solid var(--im-line)}
    .im-portrait-frame{padding:9px;border:1px solid #c4c4c0;background:var(--im-surface)}
    .im-portrait{width:100%;aspect-ratio:3/4;object-fit:cover;display:block;filter:grayscale(88%) contrast(.97);border:1px solid #b6b6b2;background:var(--im-bg)}
    .im-portrait-mono{width:100%;aspect-ratio:3/4;display:flex;align-items:center;justify-content:center;border:1px solid #b6b6b2;background:linear-gradient(165deg,#ececEA,#e2e2de);font:500 64px var(--im-serif);color:#b0b0ac}
    .im-name{font:500 clamp(34px,4.6vw,58px)/1.04 var(--im-serif);color:var(--im-deep);margin:0 0 10px;letter-spacing:-.01em}
    .im-years{font:400 16px var(--im-serif);font-style:italic;color:var(--im-soft);margin:0 0 16px}
    .im-role{font:500 11px var(--sans);letter-spacing:.2em;text-transform:uppercase;color:var(--im-soft);margin:0 0 26px;line-height:1.8}
    .im-intro{font-size:17px;line-height:1.65;color:var(--im-ink);border-left:3px solid #b9b9b4;padding-left:20px;margin:0}
    .im-lang{display:inline-flex;border:1px solid var(--im-line);font:600 11px var(--sans);margin-top:24px;background:var(--im-surface)}
    .im-lang a{padding:9px 12px;color:var(--im-mute);text-decoration:none}
    .im-lang a[aria-current="true"]{color:var(--im-deep);background:#efefec}
    .im-section{padding-top:52px}
    .im-sectionhead{font:600 11px var(--sans);letter-spacing:.24em;text-transform:uppercase;color:var(--im-soft);margin:0 0 26px;display:flex;align-items:center;gap:14px}
    .im-sectionhead::after{content:"";height:1px;flex:1;background:var(--im-line)}
    .im-body{max-width:620px;font-size:16.5px;line-height:1.85;color:#333338}
    .im-body p{margin:0 0 22px}
    .im-body p:first-child::first-letter{font:500 3.1em/0.84 var(--im-serif);float:left;padding:7px 9px 0 0;color:var(--im-soft)}
    .im-facts{margin:0;padding:0;list-style:none;max-width:620px}
    .im-facts li{padding:13px 0;border-bottom:1px solid var(--im-line);font-size:15px;color:#3a3a40}
    .im-facts strong{display:block;font:500 16px var(--im-serif);color:var(--im-deep)}
    .im-facts .w{font:500 10px var(--sans);letter-spacing:.14em;text-transform:uppercase;color:var(--im-mute)}
    .im-photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
    .im-photos img{width:100%;aspect-ratio:4/5;object-fit:cover;filter:grayscale(88%);border:1px solid var(--im-line)}
    .im-hosting{margin-top:48px;font:400 12px var(--sans);color:var(--im-mute)}
    .im-back{margin-top:26px}
    .im-back a{font:600 12px var(--sans);letter-spacing:.08em;color:var(--im-deep)}
    .im-page.lang-ml .im-body,.im-page.lang-ml .im-intro{font-family:'Noto Serif Malayalam',var(--im-serif);line-height:2.0}
    .im-page.lang-ml .im-name{font-family:'Noto Serif Malayalam',var(--im-serif)}
    @media(max-width:640px){
        .im-page{margin:-18px -14px -48px}
        .im-hero{grid-template-columns:1fr;gap:26px}
        .im-portrait-frame{max-width:250px;margin:0 auto}
        .im-body{font-size:15.5px}
    }
</style>
@endpush

@section('content')
<div class="im-page {{ $language === 'ml' ? 'lang-ml' : '' }}">
<div class="im-wrap">

    <nav class="im-crumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> / <a href="{{ route('in-memoriam.index') }}">In Memoriam</a> /
        <span aria-current="page">{{ $displayName }}</span>
    </nav>

    <div class="im-designation" aria-label="In Memoriam">
        <span class="rule"></span>
        <span>{{ $language === 'ml' ? 'ഓർമ്മയ്ക്കായി · In Memoriam' : 'In Memoriam · ഓർമ്മയ്ക്കായി' }}</span>
        <span class="rule"></span>
    </div>

    <section class="im-hero">
        <div class="im-portrait-frame">
            @if($photo)
                <img class="im-portrait" src="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$displayName) }}">
            @else
                <div class="im-portrait-mono" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            @endif
        </div>
        <div>
            <h1 class="im-name">{{ $displayName }}</h1>
            @if($profile->deceased_date_of_birth || $profile->deceased_date_of_death)
                <p class="im-years">
                    @if($profile->deceased_date_of_birth){{ $profile->deceased_date_of_birth->format('Y') }}@endif
                    @if($profile->deceased_date_of_birth && $profile->deceased_date_of_death) – @endif
                    @if($profile->deceased_date_of_death){{ $profile->deceased_date_of_death->format('Y') }}@endif
                </p>
            @endif
            <p class="im-role">{{ $headline ?: $profession }}</p>

            @if($activeEditorial && filled($activeEditorial->summary) && $activeEditorial->summary !== ($headline ?: $profession))
                <p class="im-intro">{{ $activeEditorial->summary }}</p>
            @endif

            @if($commissionerLabel)
                <p style="margin:22px 0 0;font:400 12.5px var(--sans);color:var(--im-mute)">Remembered with the consent of {{ $commissionerLabel }}.</p>
            @endif

            @if($malayalam)
                <span class="im-lang" role="navigation" aria-label="Language">
                    <a href="{{ route('in-memoriam.show', ['slug' => $profile->slug, 'lang' => 'en']) }}" @if($language === 'en') aria-current="true" @endif>EN</a>
                    <a href="{{ route('in-memoriam.show', ['slug' => $profile->slug, 'lang' => 'ml']) }}" @if($language === 'ml') aria-current="true" @endif>ML</a>
                </span>
            @endif
        </div>
    </section>

    @if($activeEditorial && filled($activeEditorial->body))
        <section class="im-section">
            <h2 class="im-sectionhead">{{ $language === 'ml' ? 'ഓർമ്മകൾ' : 'In Remembrance' }}</h2>
            @if(filled($activeEditorial->title) && $activeEditorial->title !== $displayName)
                <p style="font:italic 22px var(--im-serif);color:var(--im-deep);margin:0 0 18px">{{ $activeEditorial->title }}</p>
            @endif
            <div class="im-body">
                @foreach(preg_split('/\n\s*\n/u', trim((string) $activeEditorial->body)) as $para)
                    @if(trim($para) !== '')<p>{{ trim($para) }}</p>@endif
                @endforeach
            </div>
        </section>
    @endif

    @if($publicOffices->isNotEmpty())
        <section class="im-section">
            <h2 class="im-sectionhead">{{ $language === 'ml' ? 'പൊതുജീവിതം' : 'Life &amp; Work' }}</h2>
            <ul class="im-facts">
                @foreach($publicOffices as $office)
                    <li>
                        <strong>{{ $office->office_name }}</strong>
                        @if(filled($office->where_location))
                            <span class="w">{{ $office->where_location }}</span>
                        @endif
                        @if(filled($office->term_summary))
                            <span style="display:block;margin-top:5px">{{ $office->term_summary }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($photos->count() > 1)
        <section class="im-section">
            <h2 class="im-sectionhead">{{ $language === 'ml' ? 'ഛായാചിത്രങ്ങൾ' : 'Photographs' }}</h2>
            <div class="im-photos">
                @foreach($photos as $galleryPhoto)
                    <a href="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $galleryPhoto]) }}">
                        <img src="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $galleryPhoto]) }}" alt="{{ $galleryPhoto->alt_text ?: ($displayName.' photograph') }}" loading="lazy">
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($hostingStartsOn && $hostingEndsOn)
        <p class="im-hosting">This memorial record is hosted for the agreed period ({{ $hostingStartsOn->toFormattedDateString() }} – {{ $hostingEndsOn->toFormattedDateString() }}).</p>
    @endif

    <p class="im-back"><a href="{{ route('in-memoriam.index') }}">← About In Memoriam</a></p>
</div>
</div>
@endsection
