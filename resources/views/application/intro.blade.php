@extends('layouts.app')

@section('content')
<section class="card stack">
    <div>
        <span class="tag">ONLINE INTERVIEW</span>
    </div>
    <h1>Tell us about a life worth remembering.</h1>
    <p class="lead">
        <b>Jannayaks</b> is a documentary digital gallery of public lives in Kerala. The Online Interview is how your story reaches us — in <em>your</em> words, at <em>your</em> pace.
    </p>

    <div class="warnbox" role="note" aria-label="What this is">
        Jannayaks is not a debate, campaigning or discussion platform. The material you share is used only for editorial preparation before publication.
        You will have the chance to review and approve the finished profile before it appears.
    </div>

    <h2 style="margin-top:8px">How it works</h2>
    <ol class="sub" style="list-style:decimal;margin:0;padding-left:22px;color:var(--ink-soft)">
        <li style="padding:2px 0"><b>Choose a tier</b> that matches the depth of editorial coverage you want — you choose; no one qualifies or disqualifies you.</li>
        <li style="padding:2px 0"><b>Answer a few questions.</b> Write comfortably in English, Malayalam, Manglish, or any mix. One answer field per question — no separate languages needed.</li>
        <li style="padding:2px 0"><b>Save and continue any time</b>. Come back later from any device; answers stay safe on the server (never rely on localStorage).</li>
        <li style="padding:2px 0">Optionally, <b>upload source material</b> (résumé, articles, notes, biography) to help editorial.</li>
        <li style="padding:2px 0">When you're ready, <b>submit for editorial processing</b>. Submission does <em>not</em> publish anything — that happens only after your review.</li>
    </ol>

    <h2 style="margin-top:8px">What your answers support</h2>
    <ul class="sub" style="margin:0;padding-left:22px;color:var(--ink-soft)">
        <li style="padding:2px 0">Documentary, reader-friendly Malayalam and English editorial text</li>
        <li style="padding:2px 0">Approved sections: About You · Your Journey · Contribution · Experiences · Recognition · The Person · Looking Back · Closing</li>
        <li style="padding:2px 0">Photo/video slots per tier, plus public-office, geography and verification metadata</li>
    </ul>

    <div class="row" style="margin-top:6px;justify-content:flex-end">
        @auth
            <a class="btn primary block" href="{{ route('apply') }}">Continue to tier selection →</a>
        @else
            <a class="btn primary block" href="{{ route('filament.admin.auth.login') }}">Log in to begin →</a>
            @if (Route::has('filament.admin.auth.login'))
                <a class="btn block" href="{{ route('filament.admin.auth.login') }}">Create an account first</a>
            @endif
        @endauth
    </div>
</section>
@endsection
