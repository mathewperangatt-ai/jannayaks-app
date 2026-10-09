@extends('legal.base', ['legalActive' => 'refund'])

@section('title', 'Refund and Cancellation — Jannayaks')
@section('seoDescription', 'When Jannayaks refunds a fee, how to cancel or stop renewing, and how refunds are paid.')
@section('seoCanonical', route('legal.refund'))
@section('legal_heading', 'Refund & Cancellation')
@section('legal_intro', 'Here is when you can get your money back, how to cancel, and how a refund reaches you. Fees are charged by Aurex Network, which operates Jannayaks.')

@section('legal_body')
<div class="legal-box">
    <strong class="legal-box-title">In short</strong>
    <p>If you ask before our editors begin preparing your write-up, you get a full refund. Once preparation has begun, the fee is not refundable, except in the cases listed in section 3.</p>
</div>

<h2>1. Before preparation begins</h2>
<p>You may cancel your application and receive a <strong>full refund</strong> of what you paid if you write to us before our editorial team starts preparing your write-up. Preparation starts when our team begins work on your interview answers and uploaded material, which usually follows confirmation of your payment. To keep a refund possible, please write to us as soon as you decide to cancel.</p>

<h2>2. After preparation has begun</h2>
<p>Preparing a write-up involves editorial work, in Malayalam and English, that cannot be undone, so the fee is <strong>not refundable</strong> once that work has begun. This is the case even if you later decide not to publish, or you do not approve the result, or your membership ends.</p>

<h2>3. When we will refund you anyway</h2>
<p>Whatever the stage, we will refund you in full, or in the part that is fair, if:</p>
<ul>
    <li>we decline to publish your profile or memorial after you have paid;</li>
    <li>you were charged twice, or charged an amount different from the one shown to you;</li>
    <li>a payment was taken but not confirmed on our side, and no service was delivered; or</li>
    <li>we made an error that we cannot correct to a reasonable standard after you have pointed it out.</li>
</ul>

<h2>4. Renewals</h2>
<p>You may stop your membership from renewing at any time before the renewal date by writing to us, and you will not be charged again. A renewal fee, once paid, is not refundable for the year that has begun, except in the cases in section 3. A profile stays online until the end of the period you have paid for, plus a short grace period, and is then taken offline.</p>

<h2>5. In Memoriam pages</h2>
<p>The same rules apply: a full refund if you cancel before preparation begins, and no refund after that except in the cases in section 3. If a family member or lawful representative objects to a memorial and we unpublish it as a result, we will refund the fee for the period it is offline.</p>

<h2>6. How to ask</h2>
<p>Email <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>. Please give your name, your username, the date and amount of the payment, and the reason. We will reply within two working days.</p>

<h2>7. How refunds are paid</h2>
<p>Approved refunds go back to the same payment method you used, through Razorpay. We start the refund within seven working days of approving it. After that, your bank or card issuer may take a few more days to show it. We issue a credit note for every refund.</p>

<h2>8. Your other rights</h2>
<p>Nothing on this page limits any right you have under Indian consumer protection law. If you are not satisfied with how we have handled a refund request, please use our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> process.</p>
@endsection
