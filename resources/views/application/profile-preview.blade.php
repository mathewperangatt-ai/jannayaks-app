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

    @php($maintenanceActive = isset($maintenanceRequest) && $maintenanceRequest !== null && $maintenanceRequest->request_type === \App\Models\EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)

    @if($maintenanceActive && isset($canApproveMaintenance) && $canApproveMaintenance)
        <div class="divider"></div>
        <h3 style="font-size:15px;margin:0">Your updated profile is ready for approval</h3>
        <p class="sub" style="margin:0">The editorial team has prepared your updated profile. Review it above, then approve it, or request minor corrections. It becomes public only after Jannayaks publishes it.</p>

        <form method="POST" action="{{ route('applications.maintenance.approve', $application) }}" class="stack" onsubmit="return confirm('Approve this updated profile? Jannayaks will then complete the publication.');">
            @csrf
            <input type="hidden" name="english_editorial_content_id" value="{{ $english->id }}">
            @error('maintenance_approval')
                <div class="missbox" role="alert" style="margin:0">{{ $message }}</div>
            @enderror
            <label class="field" style="display:flex;gap:10px;align-items:flex-start">
                <input type="checkbox" name="confirm_approval" value="1" required style="margin-top:4px">
                <span>I have reviewed this updated profile and I approve it.</span>
            </label>
            <button class="btn primary" type="submit">Approve Profile</button>
        </form>

        <form method="POST" action="{{ route('applications.maintenance.correction', $application) }}" class="stack">
            @csrf
            <h3 style="font-size:15px;margin:12px 0 0">Request minor corrections</h3>
            <label class="field">
                <span>Your corrections (the editorial team reviews and applies appropriate changes)</span>
                <textarea name="correction_text" rows="4" maxlength="5000" required placeholder='Example: "Please change Director to Chairman." or "Please change 2024 to 2025."'>{{ old('correction_text') }}</textarea>
            </label>
            @error('correction_text')
                <div class="missbox" role="alert" style="margin:0">{{ $message }}</div>
            @enderror
            <button class="btn" type="submit">Request Minor Corrections</button>
            <p class="sub" style="margin:0">Corrections are part of this same maintenance update — they do not use another complimentary update or create a new charge.</p>
        </form>
    @elseif($maintenanceActive && isset($maintenanceCorrectionPending) && $maintenanceCorrectionPending)
        <div class="warnbox" role="status">Your correction request is with the editorial team. The corrected profile will return here for your approval.</div>
    @elseif($maintenanceActive && isset($maintenanceApproved) && $maintenanceApproved)
        <div class="warnbox" role="status" style="border-color:#d4e8da;background:#eef7f1;color:var(--ok)">
            <b>Customer approved — ready for final Jannayaks publication.</b> Your updated profile was approved{{ $application->customer_approved_at ? ' on '.$application->customer_approved_at->format('d M Y') : '' }}. Jannayaks staff completes the publication.
        </div>
    @elseif($canApprove)
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
