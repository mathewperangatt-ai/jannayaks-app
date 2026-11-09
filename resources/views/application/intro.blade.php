@extends('layouts.app')

@section('content')
<section class="card stack">
    <div>
        <span class="tag">ONLINE INTERVIEW</span>
    </div>
    <h1>Tell us about your achievements and journey.</h1>
    <p class="lead" lang="ml" style="margin:-4px 0 6px">
        നിങ്ങളുടെ നേട്ടങ്ങളും ജീവിതയാത്രയും ഞങ്ങളോട് പറയൂ.
    </p>
    <p class="lead">
        <b>Jannayaks</b> is a digital gallery of notable people across all fields. The Online Interview is how your story reaches us — in <em>your</em> words, at <em>your</em> pace.
    </p>

    <div class="warnbox" role="note" aria-label="What this is">
        Jannayaks is not a debate, campaigning or discussion platform. The material you share is used only for editorial preparation before publication.
        You will have the chance to review and approve the finished profile before it appears.
    </div>

    <div class="warnbox" role="note" aria-label="Review and approval" style="margin-top:10px">
        You will have an opportunity to review and approve your prepared profile before publication.
        <span lang="ml" style="display:block;margin-top:4px">തയ്യാറാക്കിയ പ്രൊഫൈൽ പ്രസിദ്ധീകരിക്കുന്നതിന് മുമ്പ് പരിശോധിക്കാനും അംഗീകരിക്കാനും നിങ്ങൾക്ക് അവസരമുണ്ടാകും.</span>
    </div>

    <div style="margin-top:10px">
        @include('partials.interview-notices', ['notice' => 'intro-full'])
    </div>

    <h2 style="margin-top:8px">How it works</h2>
    <ol class="sub" style="list-style:decimal;margin:0;padding-left:22px;color:var(--ink-soft)">
        <li style="padding:2px 0"><b>Begin your profile application</b> — one Profile.</li>
        <li style="padding:2px 0"><b>Sign in</b> with your Jannayaks username, Google, or create an account.</li>
        <li style="padding:2px 0"><b>Complete the Online Interview</b> (or direct submission) and upload source material.</li>
        <li style="padding:2px 0"><b>Pay</b> the Profile fee.</li>
        <li style="padding:2px 0">Editorial preparation, human review, your preview and approval precede publication.</li>
        <li style="padding:2px 0">Publication is subject to editorial acceptance. Payment of the profile fee does not guarantee publication. If Jannayaks rejects an application before publication, the full amount paid, including applicable taxes, will be refunded.</li>
    </ol>

    <h2 style="margin-top:8px">Before you apply</h2>
    <ul class="sub" style="margin:0;padding-left:22px;color:var(--ink-soft)">
        <li style="padding:2px 0">You submit your own information directly. Jannayaks does not accept profile applications submitted on behalf of another person by representatives.</li>
        <li style="padding:2px 0">You choose the preferred name you wish to use publicly. Your profile URL is generated from it, subject to availability and formatting requirements. Once your profile is published, its URL cannot be changed — please choose your preferred name carefully.</li>
        <li style="padding:2px 0">You are responsible for the accuracy of the information you submit. Jannayaks does not independently verify every factual statement.</li>
    </ul>

    <div class="row" style="margin-top:6px;justify-content:flex-end">
        <a class="btn primary block" href="{{ auth()->check() ? route('apply') : route('login', ['return' => route('apply')]) }}">Begin your profile application →</a>
        @guest
            <a class="btn block" href="{{ route('login') }}">Already have an account? Sign in</a>
        @endguest
    </div>
</section>

@include('partials.public-footer')
@endsection
