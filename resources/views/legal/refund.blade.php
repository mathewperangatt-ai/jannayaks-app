@extends('legal.base', ['legalActive' => 'refund'])

@section('title', 'Refund and Cancellation — Jannayaks')
@section('seoDescription', 'When Jannayaks refunds a fee, how to cancel, and how refunds are paid.')
@section('seoCanonical', route('legal.refund'))
@section('legal_heading', 'Refund & Cancellation')
@section('legal_intro', 'Here is when you can get your money back, how to cancel, and how a refund reaches you. Fees are charged by Aurex Network, which operates Jannayaks.')

@section('legal_body')
<div class="legal-box">
    <strong class="legal-box-title">In short</strong>
    <p>If you cancel before your profile or memorial is published, you receive a refund of {{ $refundPercent }}% of the fee you paid. Once it has been published, the fee is not refundable, except in the cases listed in section 3.</p>
</div>

<h2>1. Before publication</h2>
<p>You may cancel your application at any time before your profile or memorial is published by writing to us. You will receive a refund of <strong>{{ $refundPercent }}%</strong> of the fee you paid.</p>

<h2>2. After publication</h2>
<p>Once your profile or memorial has been published, the fee is <strong>not refundable</strong>, except in the cases listed in section 3. This is the case even if you later ask us to take it offline, or your membership ends.</p>

<h2>3. When we will refund you anyway</h2>
<p>Whatever the stage, we will refund you in full, or in the part that is fair, if:</p>
<ul>
    <li>we decline to publish your profile or memorial after you have paid;</li>
    <li>you were charged twice, or charged an amount different from the one shown to you;</li>
    <li>a payment was taken but not confirmed on our side, and no service was delivered; or</li>
    <li>we made an error that we cannot correct to a reasonable standard after you have pointed it out.</li>
</ul>

<h2>4. Renewals</h2>
<p>Renewal is not automatic: you are charged only if you choose to pay for a renewal. A renewal fee, once paid, is not refundable for the year that has begun, except in the cases in section 3. If you do not renew, a profile stays online until the end of the period you have paid for, plus a short grace period, and is then taken offline.</p>

<h2>5. In Memoriam pages</h2>
<p>The same rules apply: a refund of {{ $refundPercent }}% if you cancel before the memorial is published, and no refund after that except in the cases in section 3. An In Memoriam page is hosted for three years from the date it is published.</p>

<h2>6. How to ask</h2>
<p>Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>. Please give your name, your username, the date and amount of the payment, and the reason. We will reply within two working days.</p>

<h2>7. How refunds are paid</h2>
<p>Approved refunds go back to the same payment method you used, through our payment gateway. We start the refund within seven working days of approving it. After that, your bank or card issuer may take a few more days to show it. We issue a credit note for every refund.</p>

<h2>8. Your other rights</h2>
<p>Nothing on this page limits any right you have under Indian consumer protection law. If you are not satisfied with how we have handled a refund request, please use our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> process.</p>
@endsection
