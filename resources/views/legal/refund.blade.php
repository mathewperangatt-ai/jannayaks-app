@extends('legal.base', ['legalActive' => 'refund', 'legalEffectiveDate' => '10 November 2026'])

@section('title', 'Refund and Cancellation — Jannayaks')
@section('seoDescription', 'The refund stages for profile preparation services purchased through Jannayaks: full refund before submission for approval, 60% for up to seven days after, and how to request one.')
@section('seoCanonical', route('legal.refund'))
@section('legal_heading', 'Refund and Cancellation Policy')
@section('legal_intro', 'This Policy applies to payments made to Aurex Network for profile preparation services purchased through Jannayaks™.')

@section('legal_body')
<h2>1. Scope and purchase disclosures</h2>
<p>The service description, applicable charge, taxes, deliverables, payment stages and any service-specific conditions are displayed before payment. The checkout disclosure and the transaction confirmation form part of the purchase terms. This Policy sets out the refund stages that apply to the amount you have actually paid. It does not publish tariff amounts.</p>

<h2>2. Refund stages</h2>
<p>A profile is “submitted for approval” when we make the prepared version of your profile available to you for review. A profile is “approved” when you confirm it for publication.</p>
<table class="legal-table">
    <thead>
        <tr><th>Stage</th><th>Refund of the amount paid</th></tr>
    </thead>
    <tbody>
        <tr><td>Before the profile is submitted for approval</td><td>Full refund</td></tr>
        <tr><td>From submission for approval until 7 days after submission</td><td>60% refund</td></tr>
        <tr><td>After you approve the profile, or after the 7-day period ends</td><td>No refund</td></tr>
    </tbody>
</table>
<p>Your request must reach us within the relevant stage. If you approve the profile within the 7-day period, your right to a refund ends on approval. Approval is your confirmation that you have reviewed and accepted the profile, so please review it carefully before approving.</p>

<h2>3. Cases where a full refund applies</h2>
<p>A full refund will be made where we decline your application after payment, where we are unable to provide the service you paid for, or where a payment was debited more than once for the same transaction, was collected in error, or was received without a corresponding order being created. Send the transaction reference so that we can verify the payment.</p>

<h2>4. Cancellation requests</h2>
<p>To cancel, email <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a> from your registered email address, quoting your application or transaction reference. We may ask for reasonable information to verify the request. We will confirm the stage reached and the refund due.</p>

<h2>5. Renewals</h2>
<p>Where an annual renewal or other continuing service is offered, its date, charge, scope and method of renewal will be disclosed before the renewal transaction, together with any refund terms that apply to it. You may decline a renewal by contacting us before it is processed. A renewal is not authorised merely because an earlier payment was made. An automatic or recurring charge will be made only where you have given a separate and clear authorisation.</p>

<h2>6. Refund method and timing</h2>
<p>Approved refunds are initiated through the original payment channel, or another method permitted by the payment provider and applicable law. The time taken for funds to appear depends on the payment provider, bank, card issuer or UPI participant. We will give you a refund reference when available. We do not promise a fixed settlement period where that period is controlled by a bank or payment provider.</p>

<h2>7. Statutory rights</h2>
<p>Nothing in this Policy limits any refund, replacement, correction or other remedy that you are entitled to under mandatory Indian law, including consumer protection law.</p>

<h2>8. How to contact us</h2>
<p>Email <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a> with your registered email address, application or order reference, payment date, transaction identifier and a short explanation. Do not email full card numbers, PINs, passwords or OTPs. Postal address: Aurex Network, a sole proprietorship operating from 3-532A, Manchadi Jn, Moongode P.O., Thiruvananthapuram, Kerala 695573, India.</p>
@endsection
