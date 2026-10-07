@extends('layouts.app')

@section('content')
<style>
    .maint-card{background:#fff;border:1px solid #d3ddd1;border-radius:12px;padding:26px 28px;max-width:760px}
    .maint-title{font-family:'Fraunces',Georgia,serif;font-weight:500;font-size:clamp(22px,3vw,28px);color:#214d68;margin:0 0 10px}
    .maint-lead{font-size:14.5px;line-height:1.65;color:#46554d;margin:0 0 16px}
    .maint-notice{border-left:3px solid #c0762e;background:#fffaf3;border-radius:0 10px 10px 0;padding:12px 16px;font-size:13.5px;line-height:1.6;color:#7a4a1c;margin:0 0 16px}
    .maint-notice b{color:#5b3812}
    .maint-eligible{border-left-color:#86a982;background:#eaf4ec;color:#33553f}
    .maint-eligible b{color:#33553f}
    .maint-label{display:block;font:600 10.5px/1.4 'Inter',sans-serif;letter-spacing:.16em;text-transform:uppercase;color:#6f8075;margin:0 0 8px}
    .maint-textarea{width:100%;min-height:170px;border:1px solid #c3d2c0;border-radius:8px;padding:12px 14px;font:400 14px/1.6 'DM Sans',sans-serif;color:#1f2924;background:#fff;resize:vertical}
    .maint-textarea:focus{outline:2px solid #52758a;outline-offset:1px}
    .maint-hint{font-size:12.5px;color:#6f8075;margin:8px 0 16px}
    .maint-status{border:1px solid #d3ddd1;background:#f7faf5;border-radius:10px;padding:14px 16px;font-size:14px;color:#1f2924;margin:0 0 16px}
    .maint-status b{color:#214d68}
</style>

<section class="card stack">
    <p class="dash-eyebrow">Profile maintenance <span class="dash-pill">Ref&nbsp;<b>{{ $profile->reference_code }}</b></span></p>
    <h1 class="maint-title">Request a profile update</h1>

    <p class="maint-lead">
        Your published profile is maintained by the Jannayaks editorial team — it is never edited
        directly by you or published without your approval. Describe <b>all the changes you would
        like together in this one request</b>: factual corrections, biography or designation changes,
        additions or removals, and photograph replacement. Photograph replacement is part of this
        maintenance request (submitted photographs always pass the normal editorial approval).
    </p>

    @if($openRequest)
        <div class="maint-status">
            <b>Update request submitted</b> — it is with the editorial team
            (submitted {{ $openRequest->created_at->format('d M Y') }},
            {{ $openRequest->billing_classification === \App\Models\EditorialRevisionRequest::BILLING_COMPLIMENTARY ? 'Complimentary update' : 'Paid update request' }}).
            You will be able to request a further update once this one has been processed. No publication date is promised here.
        </div>
    @endif

    @php($isEligible = $eligibility['eligible'])
    <div class="maint-notice {{ $isEligible ? 'maint-eligible' : '' }}">
        @if($isEligible)
            <b>Complimentary update available now.</b> Your profile includes one complimentary
            maintenance update every 3 months, counted from your publication date
            ({{ $profile->published_at->format('d M Y') }}). Submitting now uses this cycle's
            complimentary update. The next complimentary window opens
            {{ $eligibility['next_eligible_on']->format('d M Y') }}.
        @else
            <b>Paid update opportunity.</b> This profile's complimentary update for the current
            3-month cycle has already been used. You may still submit this request — it will be
            treated as a <b>paid update opportunity</b>, and the Jannayaks team will contact you
            about it before any work begins. The next complimentary window opens
            {{ $eligibility['next_eligible_on']->format('d M Y') }}.
        @endif
    </div>

    <form method="POST" action="{{ route('applications.maintenance.store', $application) }}" class="stack">
        @csrf
        <label class="maint-label" for="request_text">Your requested changes (one bundled request)</label>
        <textarea class="maint-textarea" id="request_text" name="request_text" required
            placeholder="Example: Please update my designation to Chairman of XYZ Foundation (appointed 2026), correct the spelling of my hometown, and replace my photograph — a new portrait will follow through the photograph approval process.">{{ old('request_text') }}</textarea>
        @error('request_text')
            <div class="warnbox" role="alert" style="margin:10px 0 0">{{ $message }}</div>
        @enderror
        <p class="maint-hint">5–5,000 characters. Multiple changes submitted together count as one maintenance request.</p>

        <div class="row">
            <button class="btn primary" type="submit">Submit update request</button>
            <a class="btn" href="{{ route('applications.show', $application) }}">Back to dashboard</a>
        </div>
        <p class="maint-hint" style="margin-bottom:0">Submitting does not change your live profile, and no publication date is promised — every update passes editorial review and your approval before publication.</p>
    </form>
</section>
@endsection
