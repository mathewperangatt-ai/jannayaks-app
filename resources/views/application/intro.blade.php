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
        <li style="padding:2px 0"><b>Choose a tier</b> — unrestricted package choice.</li>
        <li style="padding:2px 0"><b>Sign in</b> with Google (or Indian mobile OTP).</li>
        <li style="padding:2px 0"><b>Pay</b> for the selected profile package.</li>
        <li style="padding:2px 0"><b>Complete the Online Interview</b> (or direct submission) and upload source material.</li>
        <li style="padding:2px 0">Editorial preparation, human review, your preview and approval precede publication.</li>
    </ol>

    <div class="row" style="margin-top:6px;justify-content:flex-end">
        <a class="btn primary block" href="{{ route('apply', ['step' => 'tiers']) }}">Choose a profile tier →</a>
        @guest
            <a class="btn block" href="{{ route('login') }}">Already have an account? Sign in</a>
        @endguest
    </div>
</section>
@endsection
