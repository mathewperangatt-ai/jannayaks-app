@extends('layouts.app')

@section('content')
<style>
    /* Dashboard card — public Jannayaks design tokens (sage/blue/saffron) */
    .dash-eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 12px;font:600 10.5px/1.2 Inter,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:#c77e2e}
    .dash-eyebrow .tag{letter-spacing:.02em;text-transform:none}
    .dash-name{font-family:'Fraunces',Georgia,serif;font-weight:500;font-size:clamp(26px,4vw,36px);line-height:1.12;color:#214d68;margin:0 0 14px;letter-spacing:-.01em}
    .dash-meta{display:flex;flex-wrap:wrap;gap:8px}
    .dash-pill{display:inline-flex;align-items:center;font-size:12.5px;font-weight:600;color:#46554d;background:#eaf2e7;border:1px solid #d3ddd1;border-radius:999px;padding:5px 12px}
    .dash-status{border:1px solid #d3ddd1;background:#f7faf5;border-radius:10px;padding:16px 18px;margin-top:8px}
    .dash-status-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:0 0 8px}
    .dash-status-text{font-family:'Fraunces',Georgia,serif;font-size:19px;line-height:1.4;color:#1f2924;margin:0}
    .dash-section{margin-top:24px;padding-top:20px;border-top:1px solid var(--line)}
    .dash-label{font:600 10px/1.2 Inter,sans-serif;letter-spacing:.18em;text-transform:uppercase;color:#6f8075;margin:0 0 12px}
    .dash-next{margin:0 0 14px}
    .dash-actions{display:flex;flex-wrap:wrap;gap:10px}
    .dash-actions .btn:not(.primary){background:#fff;border:1px solid #d3ddd1;color:#214d68;font-weight:600}
    .dash-actions .btn:not(.primary):hover{border-color:#214d68;background:#fbfaf7}
    .dash-manage{display:grid;gap:10px}
    @media(min-width:560px){.dash-manage{grid-template-columns:1fr 1fr}}
    .dash-manage-card{display:flex;flex-direction:column;gap:4px;padding:14px 16px;border:1px solid #d3ddd1;border-radius:10px;background:#fff;text-decoration:none}
    .dash-manage-card:hover{border-color:var(--saffron);background:#f7faf5}
    .dmc-label{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:14px;font-weight:600;color:#214d68}
    .dmc-arrow{color:var(--saffron);font-weight:400}
    .dmc-sub{font-size:12.5px;color:#6f8075}
    .dash-support .dash-label{margin-bottom:14px}
    .dash-support .mat-row{border-color:#d3ddd1;background:#fbfaf7;border-radius:10px}
    .dash-support .list-pill{background:#eaf2e7;border-color:#d3ddd1;color:#46554d}
    .dash-support .sub{color:#6f8075}
    .dash-identity{display:flex;gap:18px;align-items:flex-start}
    .dash-identity-main{min-width:0;flex:1}
    .dash-photo{width:76px;height:95px;object-fit:cover;border:1px solid #d3ddd1;border-radius:8px;background:#eaf2e7;flex:none}
    .dash-ref{font-variant-numeric:tabular-nums;letter-spacing:.08em}
    .dash-ref b{color:#214d68}
    .dash-publicpill{background:#fffdf8;border-color:#e4d3b8}
    .dash-publicpill a{color:#214d68;text-decoration:none}
    @media(max-width:480px){.dash-identity{gap:13px}.dash-photo{width:58px;height:73px}}
</style>

<section class="card stack">
    @if(session('maintenance_status'))
        <div style="border:1px solid #86a982;background:#eaf4ec;border-radius:10px;padding:12px 16px;font-size:14px;color:#33553f" role="status">
            {{ session('maintenance_status') }}
        </div>
    @endif
    <div class="dash-identity">
        @if($identityPhoto && $application->profile)
            <img class="dash-photo" src="{{ route('profiles.public.photo', [$application->profile, $identityPhoto]) }}" alt="Approved profile photograph of {{ $application->full_name }}" width="76" height="95">
        @endif
        <div class="dash-identity-main">
            <p class="dash-eyebrow">Profile dashboard
                @if ($application->source_method === 'online_interview')
                    <span class="dash-pill">Online Interview</span>
                @elseif ($application->source_method === 'direct_submission')
                    <span class="dash-pill">Direct submission</span>
                @else
                    <span class="dash-pill">Admin test/demo</span>
                @endif
                <span class="tag {{ $application->isInterviewSubmitted() || $application->isDirectSubmitted() ? 'ok' : 'warn' }}" style="margin-left:auto">{{ $application->isInterviewSubmitted() || $application->isDirectSubmitted() ? 'SUBMITTED' : 'IN PROGRESS' }}</span>
            </p>
            <h1 class="dash-name">{{ $application->full_name }}</h1>
            <div class="dash-meta">
                @php
                    $tierMap = ['emerging' => \App\Support\TierLabels::label('emerging'),'accomplished' => \App\Support\TierLabels::label('accomplished'),'distinguished' => \App\Support\TierLabels::label('distinguished')];
                    $priceMap = [];
                    foreach (['emerging', 'accomplished', 'distinguished'] as $tierKey) {
                        $base = (int) config('jannayaks.tier_pricing.packages.'.$tierKey.'.base_amount', 0);
                        $priceMap[$tierKey] = '₹'.number_format($base).' + GST';
                    }
                @endphp
                @if($application->profile?->reference_code)
                    <span class="dash-pill dash-ref">Ref&nbsp;<b>{{ $application->profile->reference_code }}</b></span>
                @endif
                <span class="dash-pill">Tier:&nbsp;<b>{{ $tierMap[(string)$application->package_tier] ?? ucfirst($application->package_tier) }}</b></span>
                <span class="dash-pill">{{ $priceMap[(string)$application->package_tier] ?? '—' }}</span>
                <span class="dash-pill">Started {{ $application->intake_started_at ? $application->intake_started_at->format('d M Y') : '—' }}</span>
                @if($application->online_interview_completed_at)
                    <span class="dash-pill">Submitted {{ $application->online_interview_completed_at->format('d M Y · H:i') }}</span>
                @endif
                @if($application->direct_submission_received_at)
                    <span class="dash-pill">Direct-submission received {{ $application->direct_submission_received_at->format('d M Y · H:i') }}</span>
                @endif
                @if($publicProfileUrl)
                    <span class="dash-pill dash-publicpill"><a href="{{ $publicProfileUrl }}">Public profile&nbsp;<b>{{ $publicProfilePath }}</b></a></span>
                @endif
            </div>
        </div>
    </div>

    @php
        $editorialWorkflow = app(\App\Services\CustomerEditorialWorkflowService::class);
        $memberStatus = $editorialWorkflow->memberFacingStatusLabel($application);
        $canPreview = $editorialWorkflow->canMemberViewPreview($application);
    @endphp

    <div class="dash-status">
        <div class="dash-status-head">
            <p class="dash-label" style="margin:0">Profile status</p>
        </div>
        <p class="dash-status-text">{{ $memberStatus }}</p>
        @if($canPreview)
            <div class="row" style="margin-top:10px">
                <a class="btn {{ $publicProfileUrl ? '' : 'primary' }}" href="{{ route('applications.preview', $application) }}">Review your profile →</a>
                @if($publicProfileUrl)
                    <a class="btn primary" href="{{ $publicProfileUrl }}">View public profile →</a>
                @endif
            </div>
        @elseif($publicProfileUrl)
            <div class="row" style="margin-top:10px">
                <a class="btn primary" href="{{ $publicProfileUrl }}">View public profile →</a>
            </div>
        @endif
    </div>

    @if($application->source_method === 'online_interview')
        <div class="dash-section" style="margin-top:20px;padding-top:18px">
            <p class="dash-label">Online Interview progress</p>
            <div class="progress" aria-label="Interview progress" style="margin:0">
                <div class="bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['total'] ? (int)(($progress['answered']/$progress['total'])*100) : 0 }}">
                    <span style="width:{{ $progress['total'] ? (int)(($progress['answered']/$progress['total'])*100) : 0 }}%"></span>
                </div>
                <div class="progress-meta">
                    <div>Answered <b>{{ $progress['answered'] }}</b> of {{ $progress['total'] }} unlocked</div>
                    <div>Required <b>{{ $progress['required_answered'] }}</b> of {{ $progress['required_total'] }}</div>
                    @if (! empty($progress['missing_required']))
                        <div style="color:var(--bad)">Missing required: {{ count($progress['missing_required']) }}</div>
                    @else
                        <div style="color:var(--ok)">All required questions complete</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="dash-section">
        <p class="dash-label">Next steps</p>

        @if ($application->source_method === 'online_interview')
            @if($application->isInterviewSubmitted())
                <p class="dash-next">
                    @if($canPreview)
                        Your Online Interview is complete. Review your finished profile when ready, request included revisions if needed, then approve for publication.
                    @else
                        Your Online Interview has been submitted. The editorial team is preparing your profile. You will review and approve it here before publication.
                    @endif
                </p>
            @elseif(!empty($progress['missing_required']))
                <p class="dash-next">
                    Continue the Online Interview. {{ count($progress['missing_required']) }} required questions remain before you can submit.
                </p>
            @else
                <p class="dash-next">
                    All required questions are complete. Review your answers, then submit for editorial processing.
                </p>
            @endif
        @else
            @if($application->isDirectSubmitted())
                <p class="dash-next">
                    Your direct submission is received. The editorial team will prepare your profile for review and approval.
                </p>
            @else
                <p class="dash-next">
                    Upload source material files. You can always add more before submission.
                </p>
            @endif
        @endif

        @php
            $linkedProfile = $application->profile;
            $unlocked = app(\App\Services\ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application);
        @endphp

        <div class="dash-actions">
            <a class="btn primary" href="{{ route('applications.payment', $application) }}">
                {{ $application->isPaymentSettled() ? 'View payment / receipt' : 'Pay to unlock interview →' }}
            </a>
            @if($unlocked && $application->source_method === 'online_interview')
                <a class="btn" href="{{ route('online-interview.show', $application) }}">{{ $application->isInterviewSubmitted() ? 'View submitted answers' : 'Continue Online Interview →' }}</a>
            @endif
            @if($unlocked)
                <a class="btn" href="{{ route('applications.upload.show', $application) }}">{{ $materialsCount > 0 ? 'Upload more material ('.$materialsCount.')' : 'Upload source material →' }}</a>
            @endif
            @if($unlocked && $application->source_method === 'online_interview' && !$application->isInterviewSubmitted())
                <form method="POST" action="{{ route('online-interview.submit', $application) }}" style="margin:0" onsubmit="return confirm('Submit your answers for editorial processing? You cannot edit them after submission.');">
                    @csrf
                    <button class="btn" type="submit" {{ !empty($progress['missing_required']) ? 'disabled aria-disabled=true' : '' }}>
                        Submit for editorial processing
                    </button>
                </form>
            @endif
        </div>

        @unless($unlocked)
            <div class="warnbox" role="note" style="margin-bottom:0">Complete payment to unlock the Online Interview and source-material uploads.</div>
        @endunless

        @if(!empty($progress['missing_required']))
            <div class="missbox" role="note" aria-live="polite" style="margin-bottom:0">
                Required questions still pending: {{ implode(', ', array_map('strtoupper', $progress['missing_required'])) }}. Complete them to enable Submit.
            </div>
        @endif
    </div>

    @php
        $canChoosePersonal = in_array(strtolower((string) $application->package_tier), ['accomplished', 'distinguished'], true);
        $showProfileUrl = $canChoosePersonal
            || ($linkedProfile && filled($linkedProfile->slug))
            || $application->status === \App\Models\Application::STATUS_PUBLISHED;
    @endphp
    @if($showProfileUrl || $application->status === \App\Models\Application::STATUS_PUBLISHED || $linkedProfile)
        <div class="dash-section">
            <p class="dash-label">Manage</p>
            <div class="dash-manage">
                @if($showProfileUrl || $application->status === \App\Models\Application::STATUS_PUBLISHED)
                    <a class="dash-manage-card" href="{{ route('applications.profile-url', $application) }}">
                        <span class="dmc-label">Profile URL <span class="dmc-arrow">→</span></span>
                        <span class="dmc-sub">Your public profile address</span>
                    </a>
                @endif
                @if($application->status === \App\Models\Application::STATUS_PUBLISHED && $linkedProfile?->membership)
                    <a class="dash-manage-card" href="{{ route('membership.show', $linkedProfile) }}">
                        <span class="dmc-label">Membership &amp; renewal <span class="dmc-arrow">→</span></span>
                        <span class="dmc-sub">Annual hosting, status and renewal</span>
                    </a>
                @endif
                @if($linkedProfile)
                    <a class="dash-manage-card" href="{{ route('applications.media', $application) }}">
                        <span class="dmc-label">Photographs &amp; video <span class="dmc-arrow">→</span></span>
                        <span class="dmc-sub">Portraits and external video links</span>
                    </a>
                @endif
                @if($application->status === \App\Models\Application::STATUS_PUBLISHED && $linkedProfile)
                    <a class="dash-manage-card" href="{{ route('applications.maintenance.show', $application) }}">
                        <span class="dmc-label">Request profile update <span class="dmc-arrow">→</span></span>
                        <span class="dmc-sub">One complimentary maintenance every 3 months · photograph replacement included</span>
                    </a>
                @endif
            </div>
        </div>
    @endif
</section>

<section class="card stack dash-support">
    <h2 class="dash-label">Source material</h2>
    @if($materials->isEmpty())
        <p class="sub" style="margin:0">No material uploaded yet. Source material is optional and is used as editorial context — it does not replace the Online Interview answers.</p>
    @else
        @foreach($materials as $m)
            <div class="mat-row" role="listitem">
                <div class="mat-meta">
                    <b>{{ $m->original_filename }}</b>
                    <span>
                        <span class="list-pill">{{ $typeMap[$m->material_type] ?? $m->material_type }}</span>
                        <span class="bytes">{{ number_format((int)$m->file_bytes) }} bytes</span>
                        <span class="bytes">· uploaded {{ $m->uploaded_at ? $m->uploaded_at->format('d M H:i') : '—' }}</span>
                        @if($m->isPurged())
                            <span class="tag bad" style="margin-left:6px">PURGED</span>
                        @endif
                    </span>
                </div>
            </div>
        @endforeach
    @endif
</section>
@endsection
