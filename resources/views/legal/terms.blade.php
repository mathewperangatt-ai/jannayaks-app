@extends('legal.base', ['legalActive' => 'terms'])

@section('title', 'Terms and Conditions — Jannayaks')
@section('seoDescription', 'The terms that apply when you use Jannayaks, apply for a profile or In Memoriam page, and pay for membership.')
@section('seoCanonical', route('legal.terms'))
@section('legal_heading', 'Terms & Conditions')
@section('legal_intro', 'These terms are an agreement between you and Aurex Network, which operates Jannayaks (jannayaks.in). By using the site, applying for a profile, or paying a fee, you agree to them. Please read them with our Privacy Policy and Refund & Cancellation page.')

@section('legal_body')
<h2>1. About Jannayaks</h2>
<p>Jannayaks is an editorial platform that publishes biographical profiles, in Malayalam and English, of people's leaders, and In Memoriam pages for people who have passed away. Jannayaks is operated by Aurex Network, a sole proprietorship (proprietor: Mathew Perangatt), {{ $legalAddress }}. Contact: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>, <a href="tel:{{ $contactTel }}">{{ $contactPhone }}</a>.</p>

<h2>2. Who can apply</h2>
<p>You must be at least 18 years old and able to enter a legal agreement. You may apply only for your own profile, or, for an In Memoriam page, for a person who has passed away and with whom you have a genuine family or close connection. You may recommend another living person through the Recommend Someone form, but a profile for that person is created only if they themselves apply and consent.</p>

<h2>3. Your account</h2>
<p>You are responsible for keeping your sign-in details safe and for everything done through your account. Tell us at once if you think someone else has used it. We may suspend an account to protect the site, other people, or ourselves from misuse.</p>

<h2>4. What you promise about your application</h2>
<ul>
    <li>Everything you tell us is true, accurate and not misleading.</li>
    <li>You have the right to give us any photographs, documents and other material you upload, and publishing them will not infringe anyone's rights.</li>
    <li>You are not impersonating anyone, and you will complete identity verification honestly.</li>
</ul>
<p>If we find that information is false or that you are not who you claim to be, we may refuse, suspend or remove a profile.</p>

<h2>5. Identity verification</h2>
<p>Before a living profile is published we verify identity. For residents of India this is a manual check against the public Voter ID (EPIC) records of the Election Commission of India; for people living overseas it is an identity document. Verification confirms identity only. It is not a certificate of character, and it does not mean Jannayaks endorses you.</p>

<h2>6. How profiles are prepared</h2>
<p>Your write-up is prepared by our editorial team, with the help of artificial intelligence, before publication. Our human editors review every write-up. Nothing is published until you have approved your preview and our staff has completed publication. Each package includes two rounds of changes before publication. Later changes to a published profile are handled as described on the <a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a> page.</p>

<h2>7. Editorial independence</h2>
<p>Paying a fee does not buy a particular wording, a favourable tone or a guarantee of publication. Jannayaks is politically neutral. We do not endorse, rank or campaign for any person, party or organisation, and a profile is not a political advertisement. Our editors decide what is included and how it is written. We may decline to publish a profile, or edit, correct or remove published content, if it is inaccurate, cannot be verified, is defamatory, is hateful or unlawful, or does not meet our standards.</p>

<h2>8. In Memoriam pages</h2>
<p>The person requesting a memorial confirms that they are authorised to do so and that the details given are accurate. We may ask for proof of the relationship or of the person's passing. If a family member or other lawful representative objects to a memorial, we will look into it under our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> process and may unpublish the page while we do.</p>

<h2>9. Permission to publish</h2>
<p>You keep ownership of the photographs and material you provide. You give Aurex Network a non-exclusive licence to use, edit for clarity and presentation, translate, store and publish that material, and the write-up prepared from it, on Jannayaks for as long as your profile is published and for the retention period in our Privacy Policy. Jannayaks keeps the rights in its own editorial writing, design and software, and you may not copy or reuse them without our written permission.</p>

<h2>10. Fees and payment</h2>
<p>The fee for each package, and for renewals and revisions, is shown on the <a href="{{ route('faq-charges') }}">FAQ &amp; Charges</a> page and again before you pay. Payments are made online through Razorpay. Aurex Network is not currently registered for GST and does not charge GST. If it becomes registered, GST will be added from the date of registration, shown before you pay, and applied to payments made after that date. Refunds are governed by the <a href="{{ route('legal.refund') }}">Refund &amp; Cancellation</a> page.</p>

<h2>11. Membership, renewal and expiry</h2>
<p>A profile stays public while your membership is active. Membership runs for one year from publication and renews each year at the price shown for your package. We send reminders before expiry. If you do not renew, the profile stays online for a short grace period after the end date and is then taken offline. We keep your records for a limited time after that so that you can still renew, as set out in the Privacy Policy.</p>

<h2>12. What you must not do</h2>
<ul>
    <li>Give false information or use another person's identity.</li>
    <li>Upload anything unlawful, defamatory, hateful, obscene, or that infringes anyone's rights.</li>
    <li>Use the contact box, recommendation or invitation forms to harass, spam or deceive.</li>
    <li>Scrape the site, interfere with its security, or try to get into areas you are not authorised to use.</li>
    <li>Use Jannayaks to create the impression of an official government or party endorsement.</li>
</ul>

<h2>13. Third-party services and links</h2>
<p>The site uses third-party services such as Razorpay, Google sign-in and the Google translation widget. Their own terms apply to them. Machine translation into other languages is automatic and may contain mistakes. The Malayalam and English versions prepared by our editors are the reference versions. We are not responsible for external websites that a profile may link to.</p>

<h2>14. Availability and changes to the service</h2>
<p>We aim to keep Jannayaks available, but we do not promise that it will always be uninterrupted or free from errors. We may change, pause or discontinue features. If we discontinue the service as a whole, we will tell members in advance and deal fairly with fees paid for any period we can no longer provide.</p>

<h2>15. Limits of our responsibility</h2>
<p>Jannayaks is provided on an "as is" basis. To the extent the law permits, Aurex Network is not liable for indirect or consequential loss, loss of reputation, profits or opportunities, or for loss caused by events outside our reasonable control. Our total liability to you for any claim about our service is limited to the fees you paid us for the profile or page the claim concerns. Nothing in these terms limits any right you have under Indian consumer protection law that cannot lawfully be limited.</p>

<h2>16. Ending your membership</h2>
<p>You may stop renewing, or ask us to remove your profile, at any time by writing to us. We may suspend or end your access if you break these terms. Sections that by their nature should continue (such as our publication licence for the retention period, payment obligations already due, and the limits of our responsibility) continue after membership ends.</p>

<h2>17. Changes to these terms</h2>
<p>We may update these terms. The date at the top shows the latest version. If you keep using Jannayaks after a change takes effect, you accept the updated terms. For significant changes, we will tell members by email.</p>

<h2>18. Governing law and disputes</h2>
<p>These terms are governed by the laws of India. Please first raise any problem through our <a href="{{ route('legal.grievance') }}">Grievance Redressal</a> process. If it cannot be resolved that way, the courts at Thiruvananthapuram, Kerala will have jurisdiction, without affecting any right you have to approach a consumer forum.</p>
@endsection
