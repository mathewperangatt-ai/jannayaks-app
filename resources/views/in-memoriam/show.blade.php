@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => 'in-memoriam'])

@section('title', $displayName.' — In Memoriam — Jannayaks')

@push('head')
<link rel="canonical" href="{{ $canonicalUrl }}">
<style>
    .im-hero{display:grid;gap:28px;margin-bottom:36px}
    @media(min-width:720px){.im-hero{grid-template-columns:220px 1fr;align-items:start}}
    .portrait{width:100%;max-width:220px;aspect-ratio:4/5;object-fit:cover;border-radius:18px;background:linear-gradient(145deg,#f3efe8,#e7e1d6);border:1px solid var(--line)}
    .portrait-ph{display:flex;align-items:center;justify-content:center;font-family:var(--serif);font-size:64px;font-weight:700;color:#b7aea1}
    .im-name{font-family:var(--serif);font-size:clamp(2rem,5vw,2.85rem);line-height:1.12;margin:0 0 10px;font-weight:700;letter-spacing:-.02em}
    .im-sub{margin:0 0 8px;font-size:1.05rem;color:var(--ink-soft)}
    .lang-switch{display:inline-flex;border:1px solid var(--line);border-radius:999px;overflow:hidden;margin:18px 0 0;background:#fff}
    .lang-switch a{padding:8px 14px;font-size:13px;font-weight:700;text-decoration:none;color:var(--ink-soft)}
    .lang-switch a[aria-current="true"]{background:var(--brand);color:#fff}
    .article{max-width:42rem}
    .article h2{font-family:var(--serif);font-size:1.35rem;margin:28px 0 12px}
    .article .summary{font-size:1.1rem;color:var(--ink-soft);margin:0 0 22px;line-height:1.65}
    .article .body{font-size:1.05rem;line-height:1.8;white-space:pre-wrap}
    .facts{margin:0 0 28px;padding:0;list-style:none}
    .facts li{padding:10px 0;border-bottom:1px solid var(--line);font-size:15px}
    .facts strong{display:inline-block;min-width:7.5rem;color:var(--ink-soft);font-weight:600}
    .photo-row{display:flex;flex-wrap:wrap;gap:12px;margin:8px 0 28px}
    .photo-row img{width:112px;height:140px;object-fit:cover;border-radius:12px;border:1px solid var(--line)}
    .hosting{margin-top:36px;font-size:13px;color:var(--ink-soft)}
    .back{margin-top:28px;font-size:14px}
</style>
@endpush

@section('content')
<article>
    <div class="im-hero">
        <div>
            @if($photo)
                <img class="portrait" src="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$displayName) }}" width="440" height="550">
            @else
                <div class="portrait portrait-ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            @endif
        </div>
        <div>
            <p class="eyebrow">In Memoriam</p>
            <h1 class="im-name">{{ $displayName }}</h1>
            @if($profession)
                <p class="im-sub">{{ $profession }}</p>
            @elseif($headline)
                <p class="im-sub">{{ $headline }}</p>
            @endif
            @if($profile->deceased_date_of_birth || $profile->deceased_date_of_death)
                <p class="im-sub">
                    @if($profile->deceased_date_of_birth){{ $profile->deceased_date_of_birth->format('Y') }}@endif
                    @if($profile->deceased_date_of_birth && $profile->deceased_date_of_death) – @endif
                    @if($profile->deceased_date_of_death){{ $profile->deceased_date_of_death->format('Y') }}@endif
                </p>
            @endif
            @if($commissionerLabel)
                <p class="im-sub">Remembered with the consent of {{ $commissionerLabel }}</p>
            @endif
            @if($malayalam)
                <div class="lang-switch" role="navigation" aria-label="Language">
                    <a href="{{ route('in-memoriam.show', ['slug' => $profile->slug, 'lang' => 'en']) }}" @if($language === 'en') aria-current="true" @endif>English</a>
                    <a href="{{ route('in-memoriam.show', ['slug' => $profile->slug, 'lang' => 'ml']) }}" @if($language === 'ml') aria-current="true" @endif>മലയാളം</a>
                </div>
            @endif
        </div>
    </div>

    @if($photos->count() > 1)
        <h2 class="eyebrow" style="margin-bottom:8px">Photographs</h2>
        <div class="photo-row" role="list">
            @foreach($photos as $galleryPhoto)
                <a href="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $galleryPhoto]) }}" role="listitem">
                    <img src="{{ route('in-memoriam.photo', ['slug' => $profile->slug, 'media' => $galleryPhoto]) }}" alt="{{ $galleryPhoto->alt_text ?: ($displayName.' photograph') }}" width="112" height="140">
                </a>
            @endforeach
        </div>
    @endif

    @if($publicOffices->isNotEmpty())
        <h2 class="eyebrow" style="margin-bottom:8px">Public life</h2>
        <ul class="facts">
            @foreach($publicOffices as $office)
                <li>
                    <strong>{{ $office->office_name }}</strong>
                    @if(filled($office->where_location))
                        <span> — {{ $office->where_location }}</span>
                    @endif
                    @if(filled($office->term_summary))
                        <span style="display:block;margin-top:4px;color:var(--ink-soft)">{{ $office->term_summary }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div class="article">
        @if($activeEditorial)
            @if(filled($activeEditorial->title))
                <h2>{{ $activeEditorial->title }}</h2>
            @endif
            @if(filled($activeEditorial->summary))
                <p class="summary">{{ $activeEditorial->summary }}</p>
            @endif
            @if(filled($activeEditorial->body))
                <div class="body">{{ $activeEditorial->body }}</div>
            @endif
        @endif
    </div>

    @if($hostingStartsOn && $hostingEndsOn)
        <p class="hosting">
            Published memorial hosting period:
            {{ $hostingStartsOn->toFormattedDateString() }}
            –
            {{ $hostingEndsOn->toFormattedDateString() }}
        </p>
    @endif

    <p class="back"><a href="{{ route('in-memoriam.index') }}">About In Memoriam</a></p>
</article>
@endsection
