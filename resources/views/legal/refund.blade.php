@extends('legal.base', ['legalActive' => 'refund'])

@section('title', 'Refund and Cancellation — Jannayaks')
@section('seoDescription', 'When Jannayaks refunds a fee, how to cancel, and how refunds are paid.')
@section('seoCanonical', route('legal.refund'))
@section('legal_heading', 'Refund & Cancellation')
@section('legal_intro', 'Here is when you can get your money back, how to cancel, and how a refund reaches you. Fees are charged by Aurex Network, which operates Jannayaks.')

@section('legal_body')
<div class="legal-box">
    <strong class="legal-box-title">In short</strong>
    <p>If you cancel before the completed profile is delivered to your dashboard, you receive a refund of {{ $refundPercent }}% of the fee you paid. After that delivery there is no routine refund, except in the cases listed in section 3. A memorial, which has no customer dashboard, follows publication instead of dashboard delivery.</p>
</div>

<h2>1. Before the profile reaches your dashboard</h2>
<p>You may cancel by writing to us before the completed profile is delivered to your dashboard. Delivery is the moment Jannayaks releases the finished profile for you to review. Creating an internal profile record is not that moment. You will receive a refund of <strong>{{ $refundPercent }}%</strong> of the fee you paid, including any GST included in that fee. We do not deduct GST from the refund as a separate step.</p>

<h2>2. After dashboard delivery</h2>
<p>Once the completed profile has been delivered to your dashboard, the fee is <strong>not routinely refundable</strong>, including if you later ask us to take the profile offline or your membership ends. This does not limit the cases in section 3, or any right that cannot be waived.</p>

<h2>3. When we will refund you anyway</h2>
<p>Whatever the stage of your application, we will refund you in full, or in the part that is fair, if:</p>
<ul>
    <li>we decline to publish your profile or memorial after you have paid;</li>
    <li>you were charged twice, or charged an amount different from the one shown to you;</li>
    <li>a payment was taken but not confirmed on our side, and no service was delivered; or</li>
    <li>we made an error that we cannot correct to a reasonable standard after you have pointed it out.</li>
</ul>

<h2>4. Renewals</h2>
<p>Renewal is not automatic: you are charged only if you choose to pay for a renewal. A renewal fee, once paid, is not refundable. If you do not renew, a profile stays online until the end of the period you have paid for, plus a short grace period, and is then taken offline.</p>

<h2>5. In Memoriam pages</h2>
<p>In Memoriam has no customer dashboard. The same full-refund rule applies before the memorial is published, and there is no routine refund after publication except in the cases in section 3. An In Memoriam page is hosted for three years from the date it is published. Whether that term should start on a different date has not been changed.</p>

<h2>6. How to ask</h2>
<p>Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>. Please give your name, your username, the date and amount of the payment, and the reason. We will reply within two working days.</p>

<h2>7. How refunds are paid</h2>
<p>Refunds are not sent automatically by this website. When we approve a refund, we record a credit note. That record is not itself a payment-gateway refund. Our team then has to initiate the refund through the payment gateway to the method you used. Until that gateway refund is made, the money has not been returned. After the gateway accepts it, the time it takes to reach you depends on the gateway and your bank or card issuer.</p>

<h2>8. Your other rights</h2>
<p>Nothing on this page limits any right you have under Indian consumer protection law. If you are not satisfied with how we have handled a refund request, please use our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> process.</p>
@endsection
