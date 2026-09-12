@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between">
        <div>
            <span class="tag">APPLICATION DASHBOARD</span>
            @if ($application->source_method === 'online_interview')
                <span class="list-pill">Online Interview</span>
            @elseif ($application->source_method === 'direct_submission')
                <span class="list-pill">Direct submission</span>
            @else
                <span class="list-pill">Admin test/demo</span>
            @endif
        </div>
        <div>
            @if ($application->isInterviewSubmitted() || $application->isDirectSubmitted())
                <span class="tag ok">SUBMITTED</span>
            @else
                <span class="tag warn">IN PROGRESS</span>
            @endif
        </div>
    </div>
    <h1>{{ $application->full_name }}</h1>
    <div class="sub">
        @php
            $tierMap = ['emerging' => 'Emerging Leader','accomplished' => 'Accomplished Leader','distinguished' => 'Distinguished Leader'];
            $priceMap = ['emerging' => '₹3,000','accomplished' => '₹8,000','distinguished' => '₹25,000'];
        @endphp
        <span class="list-pill">Tier: <b>{{ $tierMap[(string)$application->package_tier] ?? ucfirst($application->package_tier) }}</b> ({{ $priceMap[(string)$application->package_tier] ?? '—' }} incl. GST)</span>
        <span class="list-pill">Started {{ $application->intake_started_at ? $application->intake_started_at->format('d M Y') : '—' }}</span>
        @if($application->online_interview_completed_at)
            <span class="list-pill">Submitted {{ $application->online_interview_completed_at->format('d M Y · H:i') }}</span>
        @endif
        @if($application->direct_submission_received_at)
            <span class="list-pill">Direct-submission received {{ $application->direct_submission_received_at->format('d M Y · H:i') }}</span>
        @endif
    </div>

    @if($application->source_method === 'online_interview')
        <div class="progress" aria-label="Interview progress">
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
    @endif

    <h2 style="margin-top:10px;font-size:16px">Next action</h2>
    @if ($application->source_method === 'online_interview')
        @if($application->isInterviewSubmitted())
            <p class="lead" style="margin:0">
                Your Online Interview has been submitted. The editorial team will prepare a draft and contact you for review and approval before publication.
            </p>
        @elseif(!empty($progress['missing_required']))
            <p class="lead" style="margin:0">
                Continue the Online Interview. {{ count($progress['missing_required']) }} required questions remain before you can submit.
            </p>
        @else
            <p class="lead" style="margin:0">
                All required questions are complete. Review your answers, then submit for editorial processing.
            </p>
        @endif
    @else
        @if($application->isDirectSubmitted())
            <p class="lead" style="margin:0">
                Your direct submission is received. The editorial team will review the source materials and contact you for the next step.
            </p>
        @else
            <p class="lead" style="margin:0">
                Upload source material files. You can always add more before submission.
            </p>
        @endif
    @endif

    <div class="divider"></div>

    <h2 style="font-size:16px">Work on this application</h2>
    <div class="row">
        @if($application->source_method === 'online_interview')
            <a class="btn primary" href="{{ route('online-interview.show', $application) }}">{{ $application->isInterviewSubmitted() ? 'View submitted answers' : 'Continue Online Interview →' }}</a>
        @endif
        <a class="btn" href="{{ route('applications.upload.show', $application) }}">{{ $materialsCount > 0 ? 'Upload more material ('.$materialsCount.')' : 'Upload source material →' }}</a>
        @if($application->source_method === 'online_interview' && !$application->isInterviewSubmitted())
            <form method="POST" action="{{ route('online-interview.submit', $application) }}" style="margin:0" onsubmit="return confirm('Submit your answers for editorial processing? You cannot edit them after submission.');">
                @csrf
                <button class="btn" type="submit" {{ !empty($progress['missing_required']) ? 'disabled aria-disabled=true' : '' }}>
                    Submit for editorial processing
                </button>
            </form>
        @endif
    </div>

    @if(!empty($progress['missing_required']))
        <div class="missbox" role="note" aria-live="polite">
            Required questions still pending: {{ implode(', ', array_map('strtoupper', $progress['missing_required'])) }}. Complete them to enable Submit.
        </div>
    @endif
</section>

<section class="card stack">
    <h2 style="font-size:16px">Source material uploaded</h2>
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
