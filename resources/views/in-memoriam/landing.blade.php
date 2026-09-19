@extends('layouts.public', ['htmlLang' => 'en', 'nav' => 'in-memoriam'])

@section('title', 'In Memoriam — Jannayaks')

@push('head')
<style>
    .im-hero{margin:8px 0 36px;max-width:40rem}
    .im-hero h1{font-family:var(--serif);font-size:clamp(2rem,5vw,2.75rem);line-height:1.15;margin:0 0 14px;letter-spacing:-.02em}
    .im-hero p{margin:0 0 14px;font-size:1.05rem;color:var(--ink-soft);line-height:1.7}
    .im-panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px 20px;max-width:40rem}
    .im-panel h2{font-family:var(--serif);font-size:1.25rem;margin:0 0 10px}
    .im-panel p,.im-panel li{color:var(--ink-soft);line-height:1.65}
    .im-panel ul{margin:0 0 14px;padding-left:1.2rem}
    .im-cta{display:inline-block;margin-top:8px;padding:12px 18px;border-radius:6px;background:var(--saffron,#C65102);color:#fff;font-weight:700;text-decoration:none}
    .im-cta:hover{background:#a84400;color:#fff}
    .im-hero h1{font-family:var(--serif);font-size:clamp(2rem,5vw,2.75rem);line-height:1.15;margin:0 0 14px;letter-spacing:-.02em;color:var(--navy,#0F1F3D)}
    .im-panel h2{font-family:var(--serif);font-size:1.25rem;margin:0 0 10px;color:var(--navy,#0F1F3D)}
    .im-note{font-size:13px;color:var(--ink-soft);margin-top:16px}
</style>
@endpush

@section('content')
<section class="im-hero">
    <p class="eyebrow">Jannayaks service</p>
    <h1>In Memoriam</h1>
    <p>
        A premium digital memorial page prepared by Jannayaks for families and representatives
        who wish to honour a life of public service and community leadership.
    </p>
    <p>
        There is no online application and no family dashboard.
        The service is arranged directly with Jannayaks after you contact us.
    </p>
</section>

<section class="im-panel" aria-labelledby="im-how">
    <h2 id="im-how">How it works</h2>
    <ul>
        <li>A family member or representative contacts Jannayaks.</li>
        <li>Particulars are verified offline, including examination of the death certificate where appropriate.</li>
        <li>The memorial narrative is preferably supplied in writing by the family.</li>
        <li>Jannayaks staff edits for clarity and presentation, and prepares the page with photographs.</li>
        <li>Payment is arranged offline. Staff then publish the finished memorial for the agreed hosting period.</li>
    </ul>
    <p>Memorial biographies are human-authored. Jannayaks does not generate In Memoriam narratives with AI.</p>
    @if($pricingLabel)
        <p>
            Current offering: <strong>{{ $pricingLabel }}</strong>
            for {{ $hostingYears }} {{ $hostingYears === 1 ? 'year' : 'years' }} of hosting
            (inclusive of GST as configured).
        </p>
    @endif
    <a class="im-cta" href="mailto:{{ $contactEmail }}?subject={{ rawurlencode('In Memoriam enquiry') }}">
        Contact Jannayaks
    </a>
    <p class="im-note">Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>.</p>
</section>
@endsection
