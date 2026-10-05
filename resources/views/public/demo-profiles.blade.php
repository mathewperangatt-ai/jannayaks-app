@extends('layouts.public', ['htmlLang' => $language])

@php
    $ml = $language === 'ml';
    $cardLanguage = $ml ? 'ml' : null;
@endphp

@section('title', 'Demo Profiles — Jannayaks')
@section('seoDescription', 'Fictional demonstration profiles showing how finished Jannayaks pages read in English and Malayalam.')
@section('seoCanonical', route('demo-profiles.index'))

@push('head')
<style>
    .dp-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:8px}
    .dp-head h1{font-family:var(--serif);font-size:clamp(1.8rem,4vw,2.4rem);line-height:1.15;margin:0 0 10px;font-weight:700}
    .dp-lang{display:inline-flex;border:1px solid var(--line);border-radius:999px;overflow:hidden;background:#fff;margin-bottom:12px}
    .dp-lang a{padding:6px 14px;font:600 12px var(--sans);letter-spacing:.08em;color:var(--navy);text-decoration:none}
    .dp-lang a[aria-current="page"]{background:var(--navy);color:#fff}
    .dp-note{display:flex;gap:10px;align-items:flex-start;max-width:42rem;margin:0 0 34px;padding:11px 14px;border:1px dashed #c9d6c6;border-radius:10px;background:rgba(255,255,255,.6);font-size:13px;line-height:1.6;color:var(--gray)}
    .dp-note b{flex:none;font:600 9.5px var(--sans);letter-spacing:.18em;text-transform:uppercase;color:var(--saffron);padding-top:3px}
    .dp-section{margin:0 0 44px}
    .dp-section-head{display:flex;align-items:center;gap:14px;margin:0 0 18px}
    .dp-section-head h2{margin:0;font:600 11px var(--sans);letter-spacing:.22em;text-transform:uppercase;color:var(--navy);white-space:nowrap}
    .dp-section-head::after{content:"";flex:1;height:1px;background:var(--line)}
    .dp-ml,.dp-ml .lede,.dp-ml .dp-note{font-family:var(--mal)}
    .dp-ml .eyebrow,.dp-ml .dp-section-head h2,.dp-ml .dp-note b,.dp-section-head h2 [lang="ml"]{letter-spacing:.02em;font-family:var(--mal)}
    .dp-memorials{padding:22px 20px 24px;border:1px solid #d8d8d4;border-radius:16px;background:#f6f6f4}
    .dp-memorials .dp-section-head h2{color:#4a4a50}
    .dp-memorials .dp-section-head::after{background:#d8d8d4}
    .dp-memorial{display:block;color:inherit;text-decoration:none;background:#fafaf8;border:1px solid #d8d8d4;border-radius:10px;overflow:hidden;transition:border-color .15s ease,box-shadow .15s ease}
    .dp-memorial:hover{border-color:#8f8f95;box-shadow:0 4px 18px rgba(40,40,44,.08)}
    .dp-memorial .card-photo{background:linear-gradient(165deg,#ececea,#e2e2de)}
    .dp-memorial .card-photo img{filter:grayscale(1)}
    .dp-memorial .card-photo .placeholder{color:#b0b0ac}
    .dp-memorial strong{display:block;font-family:var(--serif);font-size:18px;font-weight:600;line-height:1.25;color:#3c3c42;margin:0 0 6px}
    .dp-memorial span{display:block;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8f8f95}
</style>
@endpush

@section('content')
<div @class(['dp-ml' => $ml])>
    <div class="dp-head">
        <div>
            <p class="eyebrow">{{ $ml ? 'ഡെമോ ശേഖരം' : 'Demonstration collection' }}</p>
            <h1>{{ $ml ? 'ഡെമോ പ്രൊഫൈലുകൾ' : 'Demo Profiles' }}</h1>
        </div>
        <nav class="dp-lang" aria-label="Language">
            <a href="{{ route('demo-profiles.index') }}" @if(! $ml) aria-current="page" @endif>EN</a>
            <a href="{{ route('demo-profiles.index', ['lang' => 'ml']) }}" @if($ml) aria-current="page" @endif lang="ml">ML</a>
        </nav>
    </div>
    <p class="lede">
        {{ $ml
            ? 'പൂർത്തിയായ ജന്നായക്സ് പേജുകൾ ഇംഗ്ലീഷിലും മലയാളത്തിലും എങ്ങനെ കാണപ്പെടുന്നുവെന്ന് കാണിക്കാൻ തയ്യാറാക്കിയ ജനനായക പ്രൊഫൈലുകളും സ്മരണാ രേഖകളും.'
            : 'Living profiles and In Memoriam records prepared to show how finished Jannayaks pages read, in English and Malayalam.' }}
    </p>
    <p class="dp-note">
        <b>{{ $ml ? 'ഡെമോ' : 'Demo' }}</b>
        <span>{{ $ml
            ? 'ഇവ സാങ്കൽപ്പിക ഡെമോ പ്രൊഫൈലുകളാണ്; യഥാർത്ഥ വ്യക്തികളെക്കുറിച്ചുള്ളതല്ല. ചിത്രങ്ങൾ AI ഉപയോഗിച്ച് സൃഷ്ടിച്ചവയാണ്.'
            : 'These are fictional demonstration profiles. They do not describe real people, and the portraits are AI-generated.' }}</span>
    </p>

    <section class="dp-section" aria-labelledby="dp-living">
        <div class="dp-section-head"><h2 id="dp-living">{{ $ml ? 'ജനനായകർ' : 'Living profiles' }}</h2></div>
        @if($profiles->isEmpty())
            <p class="empty">{{ $ml ? 'ഡെമോ പ്രൊഫൈലുകൾ ലഭ്യമല്ല.' : 'No demonstration profiles are published.' }}</p>
        @else
            <div class="grid">
                @foreach($profiles as $item)
                    @include('public.partials.profile-card', ['item' => $item, 'presentation' => $presentation, 'cardLanguage' => $cardLanguage])
                @endforeach
            </div>
        @endif
    </section>

    @if($memorials->isNotEmpty())
        <section class="dp-section dp-memorials" aria-labelledby="dp-memorial">
            <div class="dp-section-head"><h2 id="dp-memorial">
                @if($ml)
                    ഓർമ്മയ്ക്കായി
                @else
                    In Memoriam · <span lang="ml">ഓർമ്മയ്ക്കായി</span>
                @endif
            </h2></div>
            <div class="grid">
                @foreach($memorials as $memorial)
                    @php($memorialProfile = $memorial['profile'])
                    @php($memorialName = $memorialProfile->displayName())
                    <a class="dp-memorial" href="{{ route('in-memoriam.show', array_filter(['slug' => $memorialProfile->slug, 'lang' => $cardLanguage])) }}">
                        <div class="card-photo">
                            @if($memorial['photo'])
                                <img src="{{ route('in-memoriam.photo', ['slug' => $memorialProfile->slug, 'media' => $memorial['photo']]) }}" alt="{{ $memorial['photo']->alt_text ?: ('Portrait of '.$memorialName) }}" width="480" height="360" loading="lazy">
                            @else
                                <span class="placeholder" aria-hidden="true">{{ mb_strtoupper(mb_substr($memorialName, 0, 1)) }}</span>
                            @endif
                            @include('public.partials.tier-mark', ['tier' => 'in_memoriam'])
                        </div>
                        <div class="card-body">
                            <strong>{{ $memorialName }}</strong>
                            @if(filled($memorialProfile->bio_headline))
                                <span>{{ $memorialProfile->bio_headline }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
