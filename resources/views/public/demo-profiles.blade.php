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
            ? 'പൂർത്തിയായ Jannayaks പേജുകൾ ഇംഗ്ലീഷിലും മലയാളത്തിലും എങ്ങനെ കാണപ്പെടുന്നുവെന്ന് കാണിക്കാൻ തയ്യാറാക്കിയ ജനനായക പ്രൊഫൈലുകളും സ്മരണാ രേഖകളും.'
            : 'Profiles prepared to show how finished Jannayaks pages read, in English and Malayalam.' }}
    </p>
    <p class="dp-note">
        <b>{{ $ml ? 'ഡെമോ' : 'Demo' }}</b>
        <span>{{ $ml
            ? 'ഇവ സാങ്കൽപ്പിക ഡെമോ പ്രൊഫൈലുകളാണ്; യഥാർത്ഥ വ്യക്തികളെക്കുറിച്ചുള്ളതല്ല. ചിത്രങ്ങൾ AI ഉപയോഗിച്ച് സൃഷ്ടിച്ചവയാണ്.'
            : 'These are fictional demonstration profiles. They do not describe real people, and the portraits are AI-generated.' }}</span>
    </p>

    <section class="dp-section" aria-labelledby="dp-profiles">
        <div class="dp-section-head"><h2 id="dp-profiles">{{ $ml ? 'പ്രൊഫൈലുകൾ' : 'Profiles' }}</h2></div>
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

</div>
@endsection
