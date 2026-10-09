{{-- Shared shell for the legal pages. Contact and address values are provided
     to every legal.* view by the composer in AppServiceProvider. --}}
@extends('layouts.public', ['nav' => ''])

@push('head')
<style>
    .legal{max-width:780px;margin:0 auto}
    .legal-nav{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 26px}
    .legal-nav a{font-size:12.5px;font-weight:600;color:var(--navy);text-decoration:none;border:1.5px solid var(--border);background:#fff;border-radius:999px;padding:6px 12px}
    .legal-nav a:hover{border-color:var(--navy)}
    .legal-nav a[aria-current="page"]{background:var(--navy);border-color:var(--navy);color:#fff}
    .legal h1{font-family:var(--serif);font-weight:600;color:var(--navy);font-size:34px;line-height:1.2;margin:0 0 6px}
    .legal-updated{font-size:12.5px;color:var(--gray);margin:0 0 16px}
    .legal h2{font-family:var(--serif);font-weight:600;color:var(--navy);font-size:21px;line-height:1.3;margin:30px 0 10px}
    .legal p,.legal li{font-size:15px;line-height:1.75;color:var(--charcoal)}
    .legal p{margin:0 0 14px}
    .legal ul,.legal ol{margin:0 0 16px;padding-left:22px}
    .legal li{margin:0 0 6px}
    .legal-box{display:block;background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:16px 18px;margin:0 0 18px;font-style:normal;font-size:14.5px;line-height:1.75}
    .legal-box p:last-child{margin-bottom:0}
    .legal-box-title{display:block;color:var(--navy);font-size:13px;letter-spacing:.04em;margin:0 0 6px}
    .legal-pending{background:#FFFBEB;color:#92400E;border:1px dashed #D97706;border-radius:4px;padding:0 6px;font-weight:600}
    @media(max-width:640px){.legal h1{font-size:28px}.legal h2{font-size:19px}}
</style>
@endpush

@section('content')
<article class="legal">
    <nav class="legal-nav" aria-label="Legal pages">
        @foreach ([
            'privacy' => ['legal.privacy', 'Privacy Policy'],
            'terms' => ['legal.terms', 'Terms & Conditions'],
            'refund' => ['legal.refund', 'Refund & Cancellation'],
            'grievance' => ['legal.grievance', 'Grievance Redressal'],
            'disclaimer' => ['legal.disclaimer', 'Disclaimer'],
        ] as $legalKey => [$legalRoute, $legalLabel])
            <a href="{{ route($legalRoute) }}" @if(($legalActive ?? '') === $legalKey) aria-current="page" @endif>{{ $legalLabel }}</a>
        @endforeach
    </nav>

    <p class="eyebrow">Legal</p>
    <h1>@yield('legal_heading')</h1>
    <p class="legal-updated">Last updated: 9 October 2026</p>
    <p class="lede">@yield('legal_intro')</p>

    @yield('legal_body')
</article>
@endsection
