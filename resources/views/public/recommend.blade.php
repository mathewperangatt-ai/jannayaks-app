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
    .rc-field input{width:100%;min-height:46px;padding:10px 14px;border:1.5px solid var(--line);border-radius:8px;background:#fff;font:inherit}
    .rc-submit{background:var(--navy);color:#fff;border:0;border-radius:8px;padding:13px 22px;font-weight:600;font-size:14px;cursor:pointer}
    .rc-submit:hover{background:var(--saffron)}
    .rc-draft-note{margin:4px 0 16px;font-size:13.5px;color:var(--ink-soft);line-height:1.6}
    .rc-notes{margin:26px 0 0;padding:0;list-style:none;font-size:13px;color:var(--gray);line-height:1.8}
    .rc-notes li{padding-left:18px;position:relative}
    .rc-notes li::before{content:"—";position:absolute;left:0;color:var(--line)}
    @media(max-width:560px){.rc-form{padding:18px}}
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

    <form class="rc-form" id="recommend-draft">
        <div class="rc-field">
            <label for="recommender_name">Your name</label>
            <input id="recommender_name" name="recommender_name" maxlength="120" required autocomplete="name">
        </div>

        <div class="rc-field">
            <label for="recommended_name">Name of the person you are recommending</label>
            <input id="recommended_name" name="recommended_name" maxlength="120" required autocomplete="off">
        </div>

        <div class="rc-field">
            <label for="recommended_email">Their email address</label>
            <input id="recommended_email" name="recommended_email" type="email" maxlength="255" required autocomplete="off">
        </div>

        <p class="rc-draft-note">
            Your email application will open with this message ready for you to review.
            Jannayaks does not send the email. You must press Send yourself.
        </p>

        <button class="rc-submit" type="button" id="openEmailDraft">Open email draft</button>
    </form>

    <ul class="rc-notes">
        <li>Recommendation does not guarantee inclusion — Jannayaks editorial selection applies.</li>
        <li>Inclusion is not an electoral or political endorsement.</li>
        <li>No profile is created unless the person applies.</li>
        <li>This is not an application by the person themselves — for that, use <a href="{{ route('invitation-request.show') }}">Request an Invitation</a> or <a href="{{ route('apply') }}">Create Profile</a>.</li>
    </ul>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('recommend-draft');
    var button = document.getElementById('openEmailDraft');
    if (!form || !button) return;
    var website = 'https://jannayaks.in';

    form.addEventListener('submit', function (event) {
        event.preventDefault();
    });

    button.addEventListener('click', function () {
        if (!form.reportValidity()) return;
        var recommender = clean(document.getElementById('recommender_name').value);
        var email = clean(document.getElementById('recommended_email').value);
        if (recommender.length < 2 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            form.reportValidity();
            return;
        }
        var body = 'Hi,\n\n'
            + recommender + ' wanted to introduce you to Jannayaks, a digital gallery celebrating Malayalees who have made a mark in various fields.\n\n'
            + 'You can visit the Jannayaks website at ' + website + '\n';
        window.location.href = 'mailto:' + email
            + '?subject=' + encodeURIComponent('Jannayaks')
            + '&body=' + encodeURIComponent(body);
    });

    function clean(value) {
        return (value || '').replace(/[\r\n]+/g, ' ').trim();
    }
})();
</script>
@endpush
