@extends('legal.base', ['legalActive' => 'grievance'])

@section('title', 'Grievance Redressal — Jannayaks')
@section('seoDescription', 'How to complain to Jannayaks about a profile, your personal data, a payment or any other concern, and how we respond.')
@section('seoCanonical', route('legal.grievance'))
@section('legal_heading', 'Grievance Redressal')
@section('legal_intro', 'If something on Jannayaks is wrong, or you are unhappy with how we have treated you, tell us. This page explains who to write to and what happens next.')

@section('legal_body')
<h2>Grievance Officer</h2>
<address class="legal-box">
    <strong class="legal-box-title">Contact the Grievance Officer</strong>
    Name: <span class="legal-pending">[to be named before launch]</span><br>
    Aurex Network, operator of Jannayaks<br>
    {{ $legalAddress }}<br>
    Email: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a><br>
    Telephone: <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>
</address>

<h2>What you can complain about</h2>
<ul>
    <li>A published profile or memorial that is inaccurate, defamatory, or about you or a relative without consent.</li>
    <li>Someone impersonating you, or a profile created in your name that you did not request.</li>
    <li>Photographs or other material used without your permission.</li>
    <li>How your personal data is used or kept, or a request to access, correct or erase it.</li>
    <li>A payment, invoice, refund or renewal problem.</li>
    <li>Any other concern about our service or our staff.</li>
</ul>

<h2>How to complain</h2>
<p>Write to the email address above with the following details, so that we can act quickly:</p>
<ol>
    <li>Your name and how to reach you.</li>
    <li>The address (URL) of the page, or the profile name and reference, you are writing about.</li>
    <li>What is wrong, and what you would like us to do.</li>
    <li>Any documents that support your complaint.</li>
</ol>
<p>You may also telephone us, but we ask that you follow up in writing so that there is a clear record.</p>

<h2>What happens next</h2>
<ul>
    <li><strong>Acknowledgement:</strong> within 48 hours of receiving your complaint.</li>
    <li><strong>Review:</strong> we may ask you for more information, and we may temporarily take a page offline while we look into it.</li>
    <li><strong>Reply:</strong> a written decision within 15 days.</li>
</ul>
<p>Where a complaint is about intimate or private images of a person published without their consent, or about someone impersonating a person, we treat it as urgent and aim to act within 24 hours of receiving it.</p>

<h2>If you are still not satisfied</h2>
<p>Reply to our decision and ask for it to be reviewed again by the proprietor of Aurex Network. If your complaint concerns your personal data and we have not resolved it, you may approach the Data Protection Board of India. You may also approach a consumer forum or a court of competent jurisdiction.</p>

<h2>Related pages</h2>
<p>See our <a href="{{ route('legal.privacy') }}">Privacy Policy</a>, <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a> and <a href="{{ route('legal.refund') }}">Refund &amp; Cancellation</a> page.</p>
@endsection
