@extends('layouts.public', ['nav' => ''])

@section('title', 'Page not found — Jannayaks')

@push('head')
<style>
    .e404-wrap{max-width:620px;margin:0 auto;padding:56px 22px 72px;text-align:center}
    .e404-code{font:500 15px var(--sans, sans-serif);letter-spacing:.3em;text-transform:uppercase;color:var(--saffron);margin:0 0 14px}
    .e404-title{font-family:var(--serif, Georgia, serif);font-size:clamp(30px,5vw,46px);line-height:1.15;font-weight:600;color:var(--navy);margin:0 0 14px}
    .e404-text{font-size:15.5px;color:var(--ink-soft, #6f8075);line-height:1.7;margin:0 auto 30px;max-width:44ch}
    .e404-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
    .e404-actions a{display:inline-flex;align-items:center;min-height:44px;padding:11px 22px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none}
    .e404-home{background:var(--navy);color:#fff}
    .e404-home:hover{background:var(--saffron);color:#fff}
    .e404-ghost{border:1.5px solid var(--border, #d3ddd1);color:var(--navy)}
    .e404-ghost:hover{border-color:var(--navy)}
</style>
@endpush

@section('content')
<div class="e404-wrap">
    <p class="e404-code">Error 404</p>
    <h1 class="e404-title">This page could not be found.</h1>
    <p class="e404-text">
        The address you followed does not match a page on Jannayaks. It may have been
        renamed, retired, or typed incorrectly.
    </p>
    <div class="e404-actions">
        <a class="e404-home" href="{{ route('home') }}">Return to the homepage</a>
        <a class="e404-ghost" href="{{ route('demo-profiles.index') }}">View Demo Profiles</a>
    </div>
</div>
@endsection
