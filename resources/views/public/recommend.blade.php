@extends('layouts.public', ['nav' => ''])

@section('title', 'Recommend Someone You May Know — Jannayaks')
@section('seoDescription', 'Recommend someone whose life, work or public contribution deserves to be recorded in the Jannayaks archive.')
@section('seoCanonical', route('recommend.show'))
@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->webPageGraph(route('recommend.show'), 'Recommend Someone You May Know — Jannayaks')))

@push('head')
<style>
    .rc-wrap{max-width:680px;margin:0 auto}
    .rc-title{font-family:var(--serif);font-size:clamp(1.9rem,4vw,2.5rem);line-height:1.12;margin:6px 0 12px;font-weight:600;color:var(--navy)}
    .rc-lede{font-size:15.5px;color:var(--ink-soft);line-height:1.7;margin:0 0 30px}
    .rc-form{background:#fff;border:1.5px solid var(--line);border-radius:10px;padding:26px}
    .rc-field{margin-bottom:16px}
    .rc-field label{display:block;font-size:13px;font-weight:600;color:var(--navy);margin-bottom:5px}
    .rc-field .hint{font-weight:400;color:var(--gray);font-size:12px}
    .rc-field input,.rc-field textarea{width:100%;min-height:46px;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;background:#fff;font:inherit}
    .rc-field textarea{min-height:110px;resize:vertical}
    .rc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .rc-terms{display:flex;gap:10px;align-items:flex-start;margin:4px 0 18px;font-size:13px;color:var(--ink-soft);line-height:1.6}
    .rc-terms input{margin-top:3px}
    .rc-submit{background:var(--navy);color:#fff;border:0;border-radius:8px;padding:13px 22px;font-weight:600;font-size:14px;cursor:pointer}
    .rc-submit:hover{background:var(--saffron)}
    .rc-status{padding:14px 18px;background:#e7f0e4;border-left:4px solid var(--green);color:#2f5238;margin-bottom:22px;font-size:14px;line-height:1.6;border-radius:4px}
    .rc-error{padding:12px 16px;background:#f6ebe6;border-left:4px solid #b3652f;color:#6b3a1d;margin-bottom:18px;font-size:13.5px;border-radius:4px}
    .rc-notes{margin:26px 0 0;padding:0;list-style:none;font-size:13px;color:var(--gray);line-height:1.8}
    .rc-notes li{padding-left:18px;position:relative}
    .rc-notes li::before{content:"—";position:absolute;left:0;color:var(--line)}
    @media(max-width:560px){.rc-grid{grid-template-columns:1fr}.rc-form{padding:18px}}
</style>
@endpush

@section('content')
<div class="rc-wrap">
    <p class="eyebrow">Recommendations</p>
    <h1 class="rc-title">Recommend Someone You May Know</h1>
    <p class="rc-lede">
        Know someone whose life or contribution deserves to be recorded? Jannayaks welcomes
        suggestions of people whose lives, work or public contribution may be appropriate for inclusion.
    </p>

    @if(session('recommendation_status') === 'received')
        <div class="rc-status" role="status">
            <strong>Thank you.</strong> Your recommendation has been received and will be reviewed editorially.
            If the suggestion is taken forward, the person may be contacted by Jannayaks.
        </div>
    @endif

    @if($errors->any())
        <div class="rc-error" role="alert">
            {{ $errors->first('recommend') ?: 'Please review the highlighted fields and try again.' }}
        </div>
    @endif

    <form class="rc-form" method="POST" action="{{ route('recommend.store') }}">
        @csrf
        <input type="text" name="honey_bot" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

        <div class="rc-grid">
            <div class="rc-field">
                <label for="recommender_name">Your name</label>
                <input id="recommender_name" name="recommender_name" maxlength="120" required value="{{ old('recommender_name') }}">
            </div>
            <div class="rc-field">
                <label for="recommender_contact">Your mobile or email</label>
                <input id="recommender_contact" name="recommender_contact" maxlength="255" required value="{{ old('recommender_contact') }}">
            </div>
        </div>

        <div class="rc-grid">
            <div class="rc-field">
                <label for="recommended_name">Name of the person you are recommending</label>
                <input id="recommended_name" name="recommended_name" maxlength="120" required value="{{ old('recommended_name') }}">
            </div>
            <div class="rc-field">
                <label for="recommended_location">Their town / broad location <span class="hint">(optional)</span></label>
                <input id="recommended_location" name="recommended_location" maxlength="120" value="{{ old('recommended_location') }}">
            </div>
        </div>

        <div class="rc-field">
            <label for="recommended_role">Their profession / public role / area of contribution <span class="hint">(optional)</span></label>
            <input id="recommended_role" name="recommended_role" maxlength="255" value="{{ old('recommended_role') }}">
        </div>

        <div class="rc-field">
            <label for="reason">Why do you believe this person may be appropriate for Jannayaks?</label>
            <textarea id="reason" name="reason" maxlength="2000" required>{{ old('reason') }}</textarea>
        </div>

        <div class="rc-field">
            <label for="supporting_info">Any supporting information <span class="hint">(optional)</span></label>
            <textarea id="supporting_info" name="supporting_info" maxlength="2000">{{ old('supporting_info') }}</textarea>
        </div>

        <label class="rc-terms">
            <input type="checkbox" name="acknowledged_terms" value="1" required @checked(old('acknowledged_terms'))>
            <span>I understand that a recommendation does not guarantee inclusion, and that Jannayaks editorial selection applies.
            Inclusion is not an electoral or political endorsement. The person may be contacted if appropriate, and the
            information submitted here may be reviewed editorially.</span>
        </label>

        <button type="submit" class="rc-submit">Submit recommendation</button>
    </form>

    <ul class="rc-notes">
        <li>Recommendation does not guarantee inclusion — Jannayaks editorial selection applies.</li>
        <li>Inclusion is not an electoral or political endorsement.</li>
        <li>The person recommended may be contacted if appropriate.</li>
        <li>This is not an application by the person themselves — for that, use <a href="{{ route('invitation-request.show') }}">Request an Invitation</a> or <a href="{{ route('apply') }}">Create Profile</a>.</li>
    </ul>
</div>
@endsection
