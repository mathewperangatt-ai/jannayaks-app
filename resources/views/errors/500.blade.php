@extends('layouts.public', ['nav' => ''])

@section('title', 'Something went wrong — Jannayaks')

@push('head')
<style>
    .e500-wrap{max-width:620px;margin:0 auto;padding:56px 22px 72px;text-align:center}
    .e500-code{font:500 15px var(--sans, sans-serif);letter-spacing:.3em;text-transform:uppercase;color:var(--saffron);margin:0 0 14px}
    .e500-title{font-family:var(--serif, Georgia, serif);font-size:clamp(30px,5vw,46px);line-height:1.15;font-weight:600;color:var(--navy);margin:0 0 14px}
    .e500-text{font-size:15.5px;color:var(--ink-soft, #6f8075);line-height:1.7;margin:0 auto 30px;max-width:46ch}
    .e500-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
    .e500-actions a{display:inline-flex;align-items:center;min-height:44px;padding:11px 22px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none}
    .e500-home{background:var(--navy);color:#fff}
    .e500-home:hover{background:var(--saffron);color:#fff}
</style>
@endpush

@section('content')
<div class="e500-wrap">
    <p class="e500-code">Error 500</p>
    <h1 class="e500-title">Something went wrong on our side.</h1>
    <p class="e500-text">
        Jannayaks encountered a temporary problem and could not complete your request.
        Please try again in a little while.
    </p>
    <div class="e500-actions">
        <a class="e500-home" href="{{ route('home') }}">Return to the homepage</a>
    </div>
</div>
@endsection
