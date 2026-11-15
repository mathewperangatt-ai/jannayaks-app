@extends('layouts.public', ['htmlLang' => $language === 'ml' ? 'ml' : 'en', 'nav' => 'gallery'])

@section('title', 'Customer preview — Jannayaks')

@php
    $malayalamShown = $malayalam && filled($malayalam->body);
    $consentText = $malayalamShown
        ? 'I have reviewed both the English and Malayalam versions of this editorially prepared profile shown above. I consent to the publication of both versions.'
        : 'I have reviewed the English version of this editorially prepared profile shown above. I consent to the publication of the English version. This approval covers the English version only; no Malayalam version was shown for my review.';
@endphp

@section('content')
<div style="max-width:1180px;margin:0 auto;padding:16px clamp(18px,4vw,42px) 0">
    <div style="background:#fff;border:1.5px solid var(--border);border-left:4px solid var(--saffron);border-radius:10px;padding:14px 18px;font:400 13.5px/1.6 var(--fb, sans-serif);color:var(--charcoal,#1F2937)">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong style="letter-spacing:.08em;text-transform:uppercase;font-size:11px;color:var(--navy,#214d68)">Customer preview{{ $alreadyApproved ? ' — approved' : '' }}</strong>
            <span style="font-size:11px;border:1px solid var(--border);border-radius:999px;padding:2px 10px;background:#fffdf8">{{ $statusLabel }}</span>
        </div>
        <p style="margin:6px 0 0;font-size:13px;color:#6B7280">
            This is exactly how your profile will appear when published — the same layout, typography, section order, content and photographs.
            Approval and review controls are below the profile; they are not part of the published page.
        </p>
        @if (session('status'))
            <p style="margin:8px 0 0;font-size:13px;color:#1A6B3C;background:#F0FDF4;border:1px solid #d4e8da;border-radius:8px;padding:8px 12px" role="status">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div style="margin:8px 0 0;font-size:13px;color:#8a1a1a;background:#fdf2f2;border:1px solid #f2d4d4;border-radius:8px;padding:8px 12px" role="alert">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@include('public.profile-content')

<div style="max-width:1180px;margin:0 auto;padding:8px clamp(18px,4vw,42px) 64px">
    <div style="background:#fff;border:1.5px solid var(--border);border-radius:10px;padding:22px 24px;font:400 13.5px/1.6 var(--fb, sans-serif);color:var(--charcoal,#1F2937)">
        <h2 style="font:600 15px/1.4 var(--fb, sans-serif);color:var(--navy,#214d68);margin:0 0 6px">Review and approval</h2>
        <p style="margin:0 0 14px;font-size:13px;color:#6B7280">
            You review and approve this finished profile. You do not edit the biography text directly —
            the editorial team prepares the narrative. Included revision rounds remaining:
            <b>{{ $remainingRevisions }}</b> of 2.
        </p>

        <a href="{{ route('applications.show', $application) }}" style="display:inline-block;font:600 12.5px var(--fb, sans-serif);color:var(--navy,#214d68);text-decoration:none;border:1px solid var(--border);border-radius:6px;padding:8px 14px">← Back to application</a>

        @php($maintenanceActive = isset($maintenanceRequest) && $maintenanceRequest !== null && $maintenanceRequest->request_type === \App\Models\EditorialRevisionRequest::TYPE_PUBLISHED_UPDATE)

        @if($maintenanceActive && isset($canApproveMaintenance) && $canApproveMaintenance)
            <div style="margin-top:18px">
                <h3 style="font:600 13.5px var(--fb, sans-serif);color:var(--navy,#214d68);margin:0 0 4px">Your updated profile is ready for approval</h3>
                <p style="margin:0 0 10px;font-size:13px;color:#6B7280">The editorial team has prepared your updated profile. Review it above, then approve it, or request minor corrections. It becomes public only after Jannayaks publishes it.</p>

                <form method="POST" action="{{ route('applications.maintenance.approve', $application) }}" style="margin:0 0 16px" onsubmit="return confirm('Approve this updated profile? Jannayaks will then complete the publication.');">
                    @csrf
                    <input type="hidden" name="english_editorial_content_id" value="{{ $english->id }}">
                    @error('maintenance_approval')
                        <div style="margin:0 0 8px;color:#8a1a1a">{{ $message }}</div>
                    @enderror
                    <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px">
                        <input type="checkbox" name="confirm_approval" value="1" required style="margin-top:3px">
                        <span>{{ $consentText }}</span>
                    </label>
                    <button type="submit" style="margin-top:10px;background:var(--navy,#214d68);color:#fff;border:none;border-radius:6px;padding:10px 18px;font:600 13px var(--fb, sans-serif);cursor:pointer">Approve Profile</button>
                </form>

                <form method="POST" action="{{ route('applications.maintenance.correction', $application) }}">
                    @csrf
                    <h3 style="font:600 13.5px var(--fb, sans-serif);color:var(--navy,#214d68);margin:0 0 4px">Request minor corrections</h3>
                    <label style="display:block;font-size:13px;margin:6px 0">
                        <span style="display:block;margin-bottom:4px;color:#6B7280">Your corrections (the editorial team reviews and applies appropriate changes)</span>
                        <textarea name="correction_text" rows="4" maxlength="5000" required placeholder='Example: "Please change Director to Chairman." or "Please change 2024 to 2025."' style="width:100%;border:1px solid var(--border);border-radius:6px;padding:9px 11px;font:400 13.5px var(--fb, sans-serif)">{{ old('correction_text') }}</textarea>
                    </label>
                    @error('correction_text')
                        <div style="margin:0 0 8px;color:#8a1a1a">{{ $message }}</div>
                    @enderror
                    <button type="submit" style="background:transparent;color:var(--navy,#214d68);border:1px solid var(--navy,#214d68);border-radius:6px;padding:9px 16px;font:600 13px var(--fb, sans-serif);cursor:pointer">Request Minor Corrections</button>
                    <p style="margin:8px 0 0;font-size:12.5px;color:#6B7280">Corrections are part of this same maintenance update — they do not use another complimentary update or create a new charge. Typographical and factual corrections are free.</p>
                </form>
            </div>
        @elseif($maintenanceActive && isset($maintenanceCorrectionPending) && $maintenanceCorrectionPending)
            <p style="margin:14px 0 0;font-size:13px;background:#FFFBEB;border:1px solid #f2e2bd;border-radius:8px;padding:10px 12px">Your correction request is with the editorial team. The corrected profile will return here for your approval.</p>
        @elseif($maintenanceActive && isset($maintenanceApproved) && $maintenanceApproved)
            <p style="margin:14px 0 0;font-size:13px;background:#F0FDF4;border:1px solid #d4e8da;border-radius:8px;padding:10px 12px">
                <b>Customer approved — ready for final Jannayaks publication.</b> Your updated profile was approved{{ $application->customer_approved_at ? ' on '.$application->customer_approved_at->format('d M Y') : '' }}. Jannayaks staff completes the publication.
            </p>
        @elseif($canApprove)
            <div style="margin-top:18px">
                <h3 style="font:600 13.5px var(--fb, sans-serif);color:var(--navy,#214d68);margin:0 0 4px">Approve for publication</h3>
                <p style="margin:0 0 10px;font-size:13px;color:#6B7280">By approving, you consent to this profile — exactly as presented above — being published on Jannayaks. Approval does not publish it by itself — Jannayaks staff completes publication.</p>
                <form method="POST" action="{{ route('applications.preview.approve', $application) }}" onsubmit="return confirm('Approve this profile, exactly as presented above, for publication on Jannayaks?');">
                    @csrf
                    <input type="hidden" name="english_editorial_content_id" value="{{ $english->id }}">
                    <label style="display:flex;gap:10px;align-items:flex-start;font-size:13px">
                        <input type="checkbox" name="confirm_approval" value="1" required style="margin-top:3px">
                        <span>{{ $consentText }}</span>
                    </label>
                    <button type="submit" style="margin-top:10px;background:var(--navy,#214d68);color:#fff;border:none;border-radius:6px;padding:10px 18px;font:600 13px var(--fb, sans-serif);cursor:pointer">Approve this profile</button>
                </form>
            </div>
        @elseif($alreadyApproved)
            <p style="margin:14px 0 0;font-size:13px;background:#F0FDF4;border:1px solid #d4e8da;border-radius:8px;padding:10px 12px">
                You approved this profile{{ $application->customer_approved_at ? ' on '.$application->customer_approved_at->format('d M Y') : '' }}. It is awaiting publication.
            </p>
        @endif

        @if($canRequestRevision || $canRequestFactualCorrection)
            <div style="margin-top:18px;border-top:1px solid var(--border);padding-top:14px">
                <h3 style="font:600 13.5px var(--fb, sans-serif);color:var(--navy,#214d68);margin:0 0 6px">Request a change</h3>
                <form method="POST" action="{{ route('applications.preview.revision', $application) }}">
                    @csrf
                    <label style="display:block;font-size:13px;margin:6px 0">
                        <span style="display:block;margin-bottom:4px;color:#6B7280">Type of request</span>
                        <select name="request_type" required style="border:1px solid var(--border);border-radius:6px;padding:8px 10px;font:400 13.5px var(--fb, sans-serif)">
                            @if($canRequestRevision)
                                <option value="revision" @selected(old('request_type') === 'revision')>Included revision (uses 1 of 2 rounds)</option>
                            @endif
                            @if($canRequestFactualCorrection)
                                <option value="factual_correction" @selected(old('request_type', 'factual_correction') === 'factual_correction')>Factual / typographical correction (free — does not use a revision round)</option>
                            @endif
                        </select>
                    </label>
                    <label style="display:block;font-size:13px;margin:6px 0">
                        <span style="display:block;margin-bottom:4px;color:#6B7280">Your comments for the editorial team</span>
                        <textarea name="request_text" rows="5" maxlength="5000" required placeholder="Describe the change needed. Do not paste a rewritten biography." style="width:100%;border:1px solid var(--border);border-radius:6px;padding:9px 11px;font:400 13.5px var(--fb, sans-serif)">{{ old('request_text') }}</textarea>
                    </label>
                    <button type="submit" style="background:transparent;color:var(--navy,#214d68);border:1px solid var(--navy,#214d68);border-radius:6px;padding:9px 16px;font:600 13px var(--fb, sans-serif);cursor:pointer">Submit request</button>
                </form>
            </div>
        @elseif($application->status === 'editorial_revision_requested')
            <p style="margin:14px 0 0;font-size:13px;background:#FFFBEB;border:1px solid #f2e2bd;border-radius:8px;padding:10px 12px">Your request is with the editorial team. An updated preview will appear here when ready.</p>
        @endif
    </div>
</div>
@endsection
