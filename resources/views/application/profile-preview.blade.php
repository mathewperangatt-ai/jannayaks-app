@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:center">
        <span class="tag">YOUR Jannayaks PROFILE</span>
        <span class="tag {{ $alreadyApproved ? 'ok' : 'warn' }}">{{ $statusLabel }}</span>
    </div>

    <h1 style="font-size:28px;letter-spacing:-.02em">{{ $english->title ?: $application->preferred_display_name ?: $application->full_name }}</h1>
    @if($english->summary)
        <p class="lead" style="margin:0;font-size:16px;color:var(--ink-soft)">{{ $english->summary }}</p>
    @endif

    @if (session('status'))
        <div class="warnbox" role="status" style="border-color:#d4e8da;background:#eef7f1;color:var(--ok)">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="missbox" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="divider"></div>

    <article class="stack" style="white-space:pre-wrap;font-size:16px;line-height:1.7">{{ $english->body }}</article>

    @if($malayalam && filled($malayalam->body))
        <div class="divider"></div>
        <h2 style="font-size:18px">Malayalam</h2>
        @if($malayalam->title)
            <h3 style="font-size:16px;margin:0">{{ $malayalam->title }}</h3>
        @endif
        <article class="stack" style="white-space:pre-wrap;font-size:16px;line-height:1.7">{{ $malayalam->body }}</article>
    @endif
</section>

<section class="card stack">
    <h2 style="font-size:16px;margin:0">What you can do</h2>
    <p class="sub" style="margin:0">
        You review and approve this finished profile. You do not edit the biography text directly —
        the editorial team prepares the narrative. Included revision rounds remaining:
        <b>{{ $remainingRevisions }}</b> of 2.
    </p>

    <div class="row">
        <a class="btn" href="{{ route('applications.show', $application) }}">← Back to application</a>
    </div>

    @if($canApprove)
        <div class="divider"></div>
        <h3 style="font-size:15px;margin:0">Approve for publication</h3>
        <p class="sub" style="margin:0">By approving, you consent to this editorially prepared profile being published on Jannayaks. Approval does not publish it by itself — Jannayaks staff completes publication.</p>
        <form method="POST" action="{{ route('applications.preview.approve', $application) }}" class="stack" onsubmit="return confirm('Approve this editorially prepared profile for publication on Jannayaks?');">
            @csrf
            <input type="hidden" name="english_editorial_content_id" value="{{ $english->id }}">
            <label class="field" style="display:flex;gap:10px;align-items:flex-start">
                <input type="checkbox" name="confirm_approval" value="1" required style="margin-top:4px">
                <span>I have reviewed this editorially prepared profile and I approve its publication on Jannayaks.</span>
            </label>
            <button class="btn primary" type="submit">Approve this profile</button>
        </form>
    @elseif($alreadyApproved)
        <div class="warnbox" role="status" style="border-color:#d4e8da;background:#eef7f1;color:var(--ok)">
            You approved this profile{{ $application->customer_approved_at ? ' on '.$application->customer_approved_at->format('d M Y') : '' }}. It is awaiting publication.
        </div>
    @endif

    @if($canRequestRevision || $canRequestFactualCorrection)
        <div class="divider"></div>
        <h3 style="font-size:15px;margin:0">Request a change</h3>
        <form method="POST" action="{{ route('applications.preview.revision', $application) }}" class="stack">
            @csrf
            <label class="field">
                <span>Type of request</span>
                <select name="request_type" required>
                    @if($canRequestRevision)
                        <option value="revision" @selected(old('request_type') === 'revision')>Included revision (uses 1 of 2 rounds)</option>
                    @endif
                    @if($canRequestFactualCorrection)
                        <option value="factual_correction" @selected(old('request_type', 'factual_correction') === 'factual_correction')>Factual / typographical correction (does not use a revision round)</option>
                    @endif
                </select>
            </label>
            <label class="field">
                <span>Your comments for the editorial team</span>
                <textarea name="request_text" rows="5" maxlength="5000" required placeholder="Describe the change needed. Do not paste a rewritten biography.">{{ old('request_text') }}</textarea>
            </label>
            <button class="btn" type="submit">Submit request</button>
        </form>
    @elseif($application->status === 'editorial_revision_requested')
        <div class="warnbox" role="status">Your request is with the editorial team. An updated preview will appear here when ready.</div>
    @endif
</section>
@endsection
