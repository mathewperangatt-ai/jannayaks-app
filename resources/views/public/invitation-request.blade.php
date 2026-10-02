@extends('layouts.public', ['nav' => ''])

@section('title', 'Request an Invitation — Jannayaks')
@section('seoDescription', 'Request an invitation to create a Jannayaks profile through the editorial process.')
@section('seoCanonical', route('invitation-request.show'))
@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->webPageGraph(route('invitation-request.show'), 'Request an Invitation — Jannayaks')))

@push('head')
<style>
    .iv-wrap{max-width:680px;margin:0 auto}
    .iv-title{font-family:var(--serif);font-size:clamp(1.9rem,4vw,2.5rem);line-height:1.12;margin:6px 0 12px;font-weight:600;color:var(--navy)}
    .iv-lede{font-size:15.5px;color:var(--ink-soft);line-height:1.7;margin:0 0 30px}
    .iv-form{background:#fff;border:1.5px solid var(--line);border-radius:10px;padding:26px}
    .iv-field{margin-bottom:16px}
    .iv-field label{display:block;font-size:13px;font-weight:600;color:var(--navy);margin-bottom:5px}
    .iv-field .hint{font-weight:400;color:var(--gray);font-size:12px}
    .iv-field input,.iv-field textarea{width:100%;min-height:46px;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;background:#fff;font:inherit}
    .iv-field textarea{min-height:110px;resize:vertical}
    .iv-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .iv-terms{display:flex;gap:10px;align-items:flex-start;margin:4px 0 18px;font-size:13px;color:var(--ink-soft);line-height:1.6}
    .iv-terms input{margin-top:3px}
    .iv-submit{background:var(--navy);color:#fff;border:0;border-radius:8px;padding:13px 22px;font-weight:600;font-size:14px;cursor:pointer}
    .iv-submit:hover{background:var(--saffron)}
    .iv-status{padding:14px 18px;background:#e7f0e4;border-left:4px solid var(--green);color:#2f5238;margin-bottom:22px;font-size:14px;line-height:1.6;border-radius:4px}
    .iv-error{padding:12px 16px;background:#f6ebe6;border-left:4px solid #b3652f;color:#6b3a1d;margin-bottom:18px;font-size:13.5px;border-radius:4px}
    .iv-distinct{margin-top:26px;padding:16px 20px;background:var(--paper-alt);border-radius:8px;font-size:13px;color:var(--ink-soft);line-height:1.7}
    @media(max-width:560px){.iv-grid{grid-template-columns:1fr}.iv-form{padding:18px}}
</style>
@endpush

@section('content')
<div class="iv-wrap">
    <p class="eyebrow">Invitations</p>
    <h1 class="iv-title">Request an Invitation</h1>
    <p class="iv-lede">
        Jannayaks profiles are prepared through an editorial process. If you believe your own life,
        work or public contribution may be appropriate for inclusion, you may request an invitation
        to create a profile.
    </p>

    @if(session('invitation_status') === 'received')
        <div class="iv-status" role="status">
            <strong>Thank you.</strong> Your request has been received and will be reviewed editorially.
            If it is taken forward, Jannayaks will contact you using the details you provided.
        </div>
    @endif

    @if($errors->any())
        <div class="iv-error" role="alert">
            {{ $errors->first('invitation') ?: 'Please review the highlighted fields and try again.' }}
        </div>
    @endif

    <form class="iv-form" method="POST" action="{{ route('invitation-request.store') }}">
        @csrf
        <input type="text" name="honey_bot" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px">

        <div class="iv-grid">
            <div class="iv-field">
                <label for="name">Your name</label>
                <input id="name" name="name" maxlength="120" required value="{{ old('name') }}">
            </div>
            <div class="iv-field">
                <label for="contact">Your mobile or email</label>
                <input id="contact" name="contact" maxlength="255" required value="{{ old('contact') }}">
            </div>
        </div>

        <div class="iv-grid">
            <div class="iv-field">
                <label for="town">Your town <span class="hint">(optional)</span></label>
                <input id="town" name="town" maxlength="120" value="{{ old('town') }}">
            </div>
            <div class="iv-field">
                <label for="role">Your profession / public role <span class="hint">(optional)</span></label>
                <input id="role" name="role" maxlength="255" value="{{ old('role') }}">
            </div>
        </div>

        <div class="iv-field">
            <label for="reason">A little about your life, work or public contribution</label>
            <textarea id="reason" name="reason" maxlength="2000" required>{{ old('reason') }}</textarea>
        </div>

        <label class="iv-terms">
            <input type="checkbox" name="acknowledged_terms" value="1" required @checked(old('acknowledged_terms'))>
            <span>I understand that requesting an invitation does not guarantee inclusion, and that Jannayaks
            editorial selection applies. Inclusion is not an electoral or political endorsement.</span>
        </label>

        <button type="submit" class="iv-submit">Submit request</button>
    </form>

    <div class="iv-distinct">
        <strong style="color:var(--navy)">Requesting for someone else?</strong>
        Use <a href="{{ route('recommend.show') }}">Recommend Someone You May Know</a> to suggest another person,
        or <a href="{{ route('apply') }}">Create Profile</a> to begin an application directly.
    </div>
</div>
@endsection
