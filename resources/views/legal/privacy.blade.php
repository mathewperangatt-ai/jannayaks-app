@extends('legal.base', ['legalActive' => 'privacy'])

@section('title', 'Privacy Policy — Jannayaks')
@section('seoDescription', 'How Jannayaks, operated by Aurex Network, collects, uses, shares and protects personal data under the Digital Personal Data Protection Act, 2023, and the rights you have.')
@section('seoCanonical', route('legal.privacy'))
@section('legal_heading', 'Privacy Policy')
@section('legal_intro', 'This notice tells you what personal data Jannayaks collects, why, who we share it with, how long we keep it, and how you can withdraw your consent, use your rights or complain. It is written to meet India\'s Digital Personal Data Protection Act, 2023 (the DPDP Act).')

@push('head')
<style>
    .legal table{width:100%;border-collapse:collapse;margin:0 0 18px;font-size:13.5px;background:#fff;border:1.5px solid var(--border);border-radius:10px;overflow:hidden}
    .legal th,.legal td{padding:10px 12px;text-align:left;vertical-align:top;border-bottom:1px solid var(--line);line-height:1.6}
    .legal th{background:var(--light);color:var(--navy);font-size:12px;letter-spacing:.06em;text-transform:uppercase}
    .legal tr:last-child td{border-bottom:none}
    @media(max-width:600px){.legal table,.legal thead,.legal tbody,.legal tr,.legal th,.legal td{display:block}.legal thead{display:none}.legal tr{border-bottom:1px solid var(--line);padding:8px 0}.legal td{border:none;padding:4px 12px}.legal td:first-child{font-weight:700;color:var(--navy)}}
</style>
@endpush

@section('legal_body')
<h2>1. Who we are</h2>
<p>Jannayaks (jannayaks.in) is operated by <strong>Aurex Network</strong>, a sole proprietorship (proprietor: Mathew Perangatt). Under the DPDP Act, Aurex Network is the <strong>data fiduciary</strong>: it decides why and how your personal data is used.</p>
<address class="legal-box">
    <strong class="legal-box-title">Contact for questions about your data</strong>
    Aurex Network<br>
    {{ $legalAddress }}<br>
    Name of the person who answers: Mathew<br>
    Email: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a><br>
    Telephone: <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>
</address>

<h2>2. What we collect, and why</h2>
<p>We collect only what we need for the purposes below.</p>
<table>
    <thead><tr><th>What</th><th>Why we need it</th><th>How long</th></tr></thead>
    <tbody>
        <tr><td>Name, username and email address; basic details from Google if you use Google sign-in</td><td>To create and secure your account and contact you about it</td><td>While your account exists, plus the retention period in section 9</td></tr>
        <tr><td>Interview answers, photographs, documents and video links you give us; the district, local body and ward you choose</td><td>To prepare your write-up with you and publish your profile</td><td>See section 9</td></tr>
        <tr><td>Voter ID (EPIC) details</td><td>Only to confirm who you are. Never published</td><td>Only as long as needed to complete and record verification</td></tr>
        <tr><td>Payment confirmations and references (not your card or UPI details, which stay with our payment gateway); invoices we issue</td><td>To take payment, issue invoices and handle refunds and renewals</td><td>As long as tax and accounting law requires</td></tr>
        <tr><td>Messages you send through the contact box, Recommend Someone form or Request an Invitation form</td><td>To pass on or answer your message</td><td>Until the matter is closed, then deleted unless needed for a dispute</td></tr>
        <tr><td>Name and contact details of someone you recommend</td><td>Only to send that person one invitation</td><td>Only as long as needed for that invitation. Automatic deletion is not yet in place; you or they may ask us to erase these details at any time</td></tr>
        <tr><td>For In Memoriam: details about the person who has passed away; your name, relationship and contact details</td><td>To prepare and verify the memorial and to contact you</td><td>See section 9</td></tr>
        <tr><td>IP address and basic device and browser information</td><td>To keep the site secure and find faults</td><td>Only as long as needed for security and fault-finding</td></tr>
    </tbody>
</table>

<h2>3. What becomes public</h2>
<p>A profile is public once you approve it and we publish it. It shows the write-up our editors prepared with you, the photographs you approved, and the place details you chose. We do not publish your email address, identity details, or the documents you uploaded, and we do not show your mobile number on your public profile. Messages sent through a profile's contact box go to you privately and are not displayed.</p>

<h2>4. Your consent</h2>
<p>We use your personal data with your consent, and in the few situations where the DPDP Act allows use without it (for example, keeping records the law requires, or responding to a court order).</p>
<ul>
    <li><strong>How consent is asked.</strong> When you submit your interview, we record your consent to your answers and material being used to prepare your write-up, including by the artificial intelligence service providers described in section 5. Before your profile is published, you approve it by ticking a box yourself; that box is never ticked in advance.</li>
    <li><strong>What we record.</strong> We keep a record of what you agreed to, when, and which version of our consent notice was shown.</li>
    <li><strong>Withdrawing consent.</strong> You can withdraw consent at any time, as easily as you gave it, by writing to <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>. We will stop using your data for that purpose, and we will delete it within 30 days unless the law requires us to keep it. Withdrawing consent to publication means we will take your profile offline. It does not affect anything we lawfully did before you withdrew, and it does not cancel a payment already made (see our <a href="{{ route('legal.refund') }}">Refund &amp; Cancellation</a> page).</li>
</ul>

<h2>5. Who we share it with</h2>
<p>We do not sell your personal data and we do not run advertising. We share data only with service providers who process it for us under our instructions:</p>
<ul>
    <li><strong>Our payment gateway</strong> — payments.</li>
    <li><strong>Google</strong> — optional sign-in, fonts, and the translation widget.</li>
    <li><strong>Cloudflare and our hosting provider</strong> — website delivery, hosting and file storage.</li>
    <li><strong>Email delivery services</strong> — receipts, reminders and messages.</li>
    <li><strong>Artificial intelligence service providers</strong> — our human editors use artificial intelligence to help prepare write-ups, so the text of your answers and the material you upload may be sent to such a provider for that purpose only. Our editors review the result before anything is published.</li>
</ul>
<p>We may also disclose data where a law, court or government authority requires it.</p>

<h2>6. Where data is stored</h2>
<p>Our providers may store or process data on servers outside India. We transfer data only to countries and providers the law permits, and we choose providers with security commitments.</p>

<h2>7. How we protect it</h2>
<p>The site uses HTTPS. Uploaded applicant files are kept in private storage that is not reachable from the public site, and only authorised staff can open them. Access to the admin area is restricted to authorised staff, and key staff actions are recorded. No system is perfectly secure.</p>
<p><strong>If there is a personal data breach</strong> that affects you, we will tell you without delay, in plain language, what happened, what data was involved, what we are doing, and what you can do. We will also report it to the Data Protection Board of India as the law requires.</p>

<h2>8. Your rights</h2>
<p>Under the DPDP Act you may:</p>
<ul>
    <li>ask for a summary of the personal data we hold about you, who we have shared it with, and how we use it;</li>
    <li>ask us to correct, complete or update it;</li>
    <li>ask us to erase it (we will, unless the law requires us to keep it);</li>
    <li>withdraw your consent, as described above;</li>
    <li>raise a grievance about how we handle your data; and</li>
    <li>nominate another person to exercise these rights for you if you die or become unable to.</li>
</ul>
<p>To use a right, write to <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>, giving your name and username. We will acknowledge within 48 hours and reply within 30 days. Our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> page explains the process. If you are not satisfied, you may complain to the <strong>Data Protection Board of India</strong>. Please use our process first.</p>

<h2>9. How long we keep it</h2>
<ul>
    <li><strong>Published profiles</strong> stay online while your membership is active and for a short grace period after it ends.</li>
    <li><strong>After membership ends,</strong> we keep your profile records for one year in case you wish to renew. After that, we delete or anonymise personal data we no longer need.</li>
    <li><strong>Interview answers and uploaded materials</strong> are kept for at least six months after publication so that we can resolve any dispute about the profile, and are then removed when no longer needed.</li>
    <li><strong>Invoices and payment records</strong> are kept for as long as tax and accounting law requires.</li>
    <li>When the purpose for which we collected your data has been served, or you withdraw your consent, we erase the data, except where the law requires us to keep it.</li>
</ul>
<p>Deletion at the end of these periods is not yet automatic. Until it is, you can ask us at any time to erase data we no longer need to keep (see section 8).</p>

<h2>10. Children</h2>
<p>Jannayaks profiles are for adults aged 18 and above. By applying, you confirm that you are 18 or older (see our <a href="{{ route('legal.terms') }}">Terms &amp; Conditions</a>). We do not knowingly collect personal data from anyone under 18, because the DPDP Act requires verifiable parental consent for that and our service is not set up for it. If you believe we have collected a child's data, please contact us and we will delete it.</p>

<h2>11. People who have passed away, and people you recommend</h2>
<p>The DPDP Act protects the data of living people. An In Memoriam page is prepared with the consent of the family member who requests it, whose own details we handle under this notice. If a family member or lawful representative objects to a memorial, we will review it under our grievance process.</p>
<p>If you recommend someone, we use their details only to send them one invitation. We do not create a profile for them unless they apply and consent themselves.</p>

<h2>12. Cookies</h2>
<p>We use essential cookies to keep you signed in and to protect forms and payments. If you choose a language with the translate option, Google sets a cookie to remember it. We do not use advertising or analytics cookies. Pages that load Google Fonts or the Google translation widget share your IP address with Google.</p>

<h2>13. Changes to this notice</h2>
<p>We may update this notice. The date at the top shows the latest version. If a change significantly affects how we use your data, we will tell you by email or on the site and ask for your consent again where the law requires.</p>

<h2>14. Contact</h2>
<p>For anything about your data, write to <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> or call <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>.</p>
@endsection
