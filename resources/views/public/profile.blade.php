@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => ''])

@section('title', $displayName.' — Jannayaks')

@push('head')
<link rel="canonical" href="{{ $canonicalUrl }}">
<style>
    .jp{--tier:#8b6b2e;--tier-soft:#f7f0df;max-width:1080px;margin:0 auto}
    .jp.tier-distinguished{--tier:#6d3b78;--tier-soft:#f6edf8}
    .jp.tier-acclaimed{--tier:#246a63;--tier-soft:#eaf6f3}
    .jp.tier-recognised{--tier:#8b6b2e;--tier-soft:#f8f2e5}
    .jp-hero{position:relative;display:grid;grid-template-columns:minmax(190px,300px) 1fr;gap:42px;align-items:center;padding:34px 34px 38px;border:1px solid var(--line);border-radius:28px;background:linear-gradient(135deg,var(--tier-soft),#fff 62%);overflow:hidden;margin-bottom:30px}
    .jp-hero:after{content:"";position:absolute;right:-80px;top:-115px;width:330px;height:220px;background:var(--tier);transform:rotate(28deg);opacity:.92}
    .jp-tier{position:absolute;right:18px;top:18px;z-index:2;color:#fff;font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;transform:rotate(28deg);transform-origin:center}
    .jp-portrait{width:100%;aspect-ratio:4/5;object-fit:cover;border-radius:20px;border:5px solid rgba(255,255,255,.9);box-shadow:0 18px 45px rgba(0,0,0,.12);background:#eee8df}
    .jp-ph{display:flex;align-items:center;justify-content:center;font-family:var(--serif);font-size:72px;font-weight:700;color:#b7aea1}
    .jp-kicker{display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin:0 0 13px;color:var(--tier);font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
    .jp-dot{width:7px;height:7px;border-radius:50%;background:var(--tier)}
    .jp-name{font-family:var(--serif);font-size:clamp(2.25rem,6vw,4rem);line-height:1.04;letter-spacing:-.035em;margin:0 0 14px}
    .jp-headline{font-family:var(--serif);font-size:clamp(1.15rem,2.2vw,1.45rem);line-height:1.5;margin:0 0 15px;color:var(--ink-soft)}
    .jp-activity{display:inline-block;margin:0 0 14px;padding:8px 12px;border-radius:999px;background:#fff;border:1px solid rgba(0,0,0,.08);font-size:14px;font-weight:700}
    .jp-location{margin:0;color:var(--ink-soft);font-size:14px}
    .jp-lang{display:flex;gap:4px;margin:22px 0 0;padding:4px;border:1px solid var(--line);border-radius:999px;background:#fff;width:max-content}
    .jp-lang a{padding:8px 15px;border-radius:999px;text-decoration:none;font-size:13px;font-weight:800;color:var(--ink-soft)}
    .jp-lang a[aria-current="true"]{background:var(--tier);color:#fff}
    .jp-grid{display:grid;grid-template-columns:minmax(0,1fr) 280px;gap:30px;align-items:start}
    .jp-main{min-width:0}
    .jp-article{max-width:720px}
    .jp-article h2{font-family:var(--serif);font-size:1.55rem;margin:32px 0 13px}
    .jp-article .summary{font-family:var(--serif);font-size:1.22rem;line-height:1.7;margin:0 0 26px;color:var(--ink-soft)}
    .jp-article .body{font-size:1.07rem;line-height:1.9;white-space:pre-wrap}
    .jp-side{display:grid;gap:16px;position:sticky;top:18px}
    .jp-card{padding:20px;border:1px solid var(--line);border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(0,0,0,.05)}
    .jp-card h2{font-family:var(--serif);font-size:1.12rem;margin:0 0 12px}
    .jp-card p{margin:0;color:var(--ink-soft);font-size:14px;line-height:1.65}
    .jp-facts{list-style:none;padding:0;margin:0}
    .jp-facts li{padding:12px 0;border-bottom:1px solid var(--line);font-size:14px}
    .jp-facts li:last-child{border-bottom:0;padding-bottom:0}
    .jp-facts strong{display:block;color:var(--ink-soft);font-size:11px;letter-spacing:.08em;text-transform:uppercase;margin-bottom:4px}
    .jp-photos{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:14px 0 30px}
    .jp-photos img{width:100%;aspect-ratio:4/5;object-fit:cover;border-radius:14px;border:1px solid var(--line)}
    .jp-video-slot{border:1px dashed var(--tier);background:var(--tier-soft);border-radius:16px;padding:18px;margin-top:14px}
    .jp-video-slot strong{display:block;color:var(--tier);margin-bottom:5px}
    .jp-contact a{font-weight:700;color:var(--ink)}
    .jp-back{margin:34px 0;font-size:14px}
    @media(max-width:820px){.jp-hero{grid-template-columns:150px 1fr;gap:24px;padding:24px}.jp-grid{grid-template-columns:1fr}.jp-side{position:static}.jp-hero:after{width:230px;height:150px;right:-75px;top:-80px}.jp-tier{right:9px;top:9px}}
    @media(max-width:600px){.jp-hero{grid-template-columns:1fr;padding:18px;border-radius:22px}.jp-portrait{max-width:230px}.jp-hero:after{width:210px;height:130px}.jp-tier{font-size:10px}.jp-name{font-size:2.35rem}.jp-grid{gap:18px}.jp-photos{grid-template-columns:repeat(2,1fr)}}
</style>
@endpush

@section('content')
<article class="jp tier-{{ $tierKey }}">
    <header class="jp-hero">
        <span class="jp-tier">{{ $tier }}</span>
        <div>
            @if($photo)
                <img class="jp-portrait" src="{{ route('profiles.public.photo', [$profile, $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$displayName) }}" width="600" height="750">
            @else
                <div class="jp-portrait jp-ph" aria-hidden="true">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</div>
            @endif
        </div>
        <div>
            <p class="jp-kicker"><span class="jp-dot"></span> Jannayaks profile</p>
            <h1 class="jp-name">{{ $displayName }}</h1>
            @if($currentActivity)
                <p class="jp-activity">{{ $currentActivity }}</p>
            @elseif($profession)
                <p class="jp-activity">{{ $profession }}</p>
            @endif
            @if($headline)
                <p class="jp-headline">{{ $headline }}</p>
            @endif
            @if($locationLabel)<p class="jp-location">{{ $locationLabel }}</p>@endif
            @if($malayalam)
                <nav class="jp-lang" aria-label="Language">
                    <a href="{{ route('profiles.public', ['slug'=>$profile->slug,'lang'=>'en']) }}" @if($language==='en') aria-current="true" @endif>English</a>
                    <a href="{{ route('profiles.public', ['slug'=>$profile->slug,'lang'=>'ml']) }}" @if($language==='ml') aria-current="true" @endif>മലയാളം</a>
                </nav>
            @endif
        </div>
    </header>

    <div class="jp-grid">
        <main class="jp-main">
            @if(isset($photos) && $photos->count() > 1)
                <div class="jp-photos" aria-label="Photographs">
                    @foreach($photos as $galleryPhoto)
                        <a href="{{ route('profiles.public.photo', [$profile, $galleryPhoto]) }}">
                            <img src="{{ route('profiles.public.photo', [$profile, $galleryPhoto]) }}" alt="{{ $galleryPhoto->alt_text ?: ($displayName.' photograph') }}" width="320" height="400" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif

            @if(isset($videoLinks) && $videoLinks->isNotEmpty())
                <section class="jp-card" style="margin-bottom:24px">
                    <h2>Video</h2>
                    @foreach($videoLinks as $video)
                        <p style="margin-top:8px"><a href="{{ $video->url }}" rel="noopener noreferrer" target="_blank">{{ $video->label ?: 'Watch video' }}</a></p>
                    @endforeach
                </section>
            @endif

            <div class="jp-article">
                @if($activeEditorial)
                    @if(filled($activeEditorial->title) && $activeEditorial->title !== $displayName)<h2>{{ $activeEditorial->title }}</h2>@endif
                    @if(filled($activeEditorial->summary))<p class="summary">{{ $activeEditorial->summary }}</p>@endif
                    @if(filled($activeEditorial->body))<div class="body">{{ $activeEditorial->body }}</div>@endif
                @else
                    <p class="lede">This published profile does not yet have an approved editorial biography.</p>
                @endif
            </div>
        </main>

        <aside class="jp-side">
            @if($publicOffices->isNotEmpty())
                <section class="jp-card">
                    <h2>Public life</h2>
                    <ul class="jp-facts">
                        @foreach($publicOffices as $office)
                            <li><strong>{{ $office->office_name }}</strong>
                                @if(filled($office->where_location)){{ $office->where_location }}@endif
                                @if(filled($office->term_summary))<span style="display:block;margin-top:4px;color:var(--ink-soft)">{{ $office->term_summary }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($publicContact)
                <section class="jp-card jp-contact">
                    <h2>Contact</h2>
                    <p>Public contact details shared with consent.</p>
                    <ul class="jp-facts" style="margin-top:8px">
                        @if($publicContact['email'])<li><strong>Email</strong><a href="mailto:{{ $publicContact['email'] }}">{{ $publicContact['email'] }}</a></li>@endif
                        @if($publicContact['mobile'])<li><strong>Phone</strong><a href="tel:{{ $publicContact['mobile'] }}">{{ $publicContact['mobile'] }}</a></li>@endif
                    </ul>
                </section>
            @endif

            <section class="jp-card">
                <h2>Video &amp; Reel</h2>
                @if($videoLinks && $videoLinks->isNotEmpty())
                    <p>Editorially approved video content is available above.</p>
                @else
                    <div class="jp-video-slot">
                        <strong>Video space</strong>
                        <span style="font-size:13px;color:var(--ink-soft)">A user-submitted video of up to 1 minute may be included, subject to editorial approval.</span>
                    </div>
                @endif
            </section>
        </aside>
    </div>

    <p class="jp-back"><a href="{{ route('gallery.index') }}">← Back to gallery</a></p>
</article>
@endsection
