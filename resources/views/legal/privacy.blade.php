@extends('legal.base', ['legalActive' => 'privacy', 'legalEffectiveDate' => '10 November 2026'])

@section('title', 'Privacy Policy — Jannayaks')
@section('seoDescription', 'How Jannayaks processes personal data through jannayaks.in under the Digital Personal Data Protection Act, 2023: what we collect, why, processors, retention, your rights and grievances.')
@section('seoCanonical', route('legal.privacy'))
@section('legal_heading', 'Privacy Policy')
@section('legal_intro', 'This Policy is our notice to you about personal data processed through jannayaks.in. It is written to meet the requirements of the Digital Personal Data Protection Act, 2023 (“DPDP Act”) and the Rules made under it, as they come into force.')

@section('legal_body')
<h2>1. Who is responsible</h2>
<p>jannayaks.in is operated by Aurex Network, a sole proprietorship operating from 3-532A, Manchadi Jn, Moongode P.O., Thiruvananthapuram, Kerala 695573, India. For the DPDP Act, Aurex Network decides why and how your personal data is processed and is the Data Fiduciary. In this Policy, “you” is the person whose personal data is processed (the Data Principal).</p>

<h2>2. Personal data we process</h2>
<p><strong>Account and contact data:</strong> name, email address, optional phone number, login details and account preferences.</p>
<p><strong>Profile and editorial data:</strong> biography, education, employment, political or organisational role, achievements, dates, affiliations, questionnaire responses, statements, references, links and corrections. Location details you provide, such as district, local body, ward, state or country, are also processed.</p>
<p><strong>Media and supporting material:</strong> photographs and any documents you choose to submit.</p>
<p><strong>Transaction data:</strong> payment status, order or invoice details, transaction identifiers and refund records. Your card, UPI or bank details are entered with our payment provider and are not stored by us.</p>
<p><strong>Technical and security data:</strong> IP address, device and browser details, timestamps, access logs, diagnostic data, session data and security events.</p>
<p><strong>Communications:</strong> support requests, consent records, rights requests and grievance submissions.</p>

<h2>3. Why we process it and on what basis</h2>
<p>We process personal data to create and manage accounts, receive applications, prepare, edit and publish profiles, communicate with you, take payments and process refunds, provide support, keep the service secure, prevent fraud and abuse, comply with law, and establish or defend legal claims.</p>
<p>Where we rely on your consent, we ask for it by a clear action, such as ticking a box or submitting your application, after showing you this notice. Your consent is limited to the purposes stated. You may withdraw consent at any time, as easily as you gave it, by writing to us. Withdrawal does not affect processing already done, and we will explain what it means for your application or profile. We also process data for certain uses the law allows without consent, such as complying with a court order or legal obligation.</p>

<h2>4. How profiles are prepared</h2>
<p>Profiles are prepared first using artificial intelligence tools and are then checked by human editors before they are shown to you for approval. Editorial checking is a quality step. It does not mean that every statement, date, credential or claim has been independently verified.</p>
<p>To prepare drafts, the information you submit may be sent to third-party artificial intelligence service providers, who may process it on servers outside India. We send only the information needed for drafting. We do not sell your personal data to data brokers.</p>

<h2>5. Public profiles and display on other sites</h2>
<p>A profile becomes public only after the approval and publication steps. Public content may include the approved biography, selected photographs, professional details and links.</p>
<p>Jannayaks does not currently offer applicants an option to have profiles cross-published on IconsHerald. If a profile is already displayed there under an earlier, specific authorisation, the approved profile information and selected media may be processed for that public display. Private application materials are not treated as public profile content.</p>
<p>Public pages may be indexed, cached, quoted or copied by search engines and other third parties. Removing a profile from our site cannot guarantee removal of copies held independently by others.</p>

<h2>6. Service providers and where data is stored</h2>
<p>We use these kinds of providers, who process data on our behalf: Railway for application hosting and databases; Cloudflare for domain, network delivery, security and file storage; Razorpay for payments; an email service provider for messages; and the artificial intelligence providers described above. Where you use the page translation feature for languages other than English or Malayalam, Google Translate processes the page content you are viewing.</p>
<p>Some of these providers store or process data on servers outside India. The DPDP Act permits this unless the Government of India restricts transfers to a particular country. We will follow any such restriction and will update this Policy if our providers or locations change. We share only what each provider needs, and we do not rent your private contact details to third parties for marketing.</p>
<p>We may also disclose information where required by law, court order or a competent authority, or where reasonably necessary to protect users, the public, our rights or platform security.</p>

<h2>7. Security and breaches</h2>
<p>We use access controls, encryption in transit, encryption of sensitive contact details such as phone numbers when stored, restricted administrative access, protected configuration secrets and backups. No online system is completely secure. If a personal data breach occurs, we will notify the Data Protection Board of India and the affected persons as the law requires.</p>

<h2>8. Retention and erasure</h2>
<p>We keep personal data only as long as needed for the purpose it was collected for, or as the law requires. In practice:</p>
<ul>
    <li>Raw questionnaire submissions are kept for at least six months after publication so that disputes can be resolved, and are then erased unless needed for a dispute or legal requirement.</li>
    <li>Approved public profile content is kept while the profile is authorised for display.</li>
    <li>Invoices, payment and refund records are kept for the periods required by tax and accounting law.</li>
    <li>Security and processing logs are kept for at least one year, as the law requires.</li>
    <li>Backups are overwritten in the routine backup cycle.</li>
</ul>
<p>When you withdraw consent or ask for erasure, we will erase your personal data unless the law requires us to keep it, and we will tell you what has been kept and why. Closing an account or removing a public profile does not by itself require immediate deletion of records that the law requires us to retain.</p>

<h2>9. Your rights</h2>
<p>Under the DPDP Act you may ask us for: a summary of the personal data we process and the processing we carry out; correction, completion or updating of your data; erasure of your data; withdrawal of consent; redress of a grievance; and the nomination of another person to exercise your rights if you die or become incapable. Write to <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a>, preferably from your registered email address so that we can verify the request.</p>
<p>If you submit data about another person, you must have that person’s permission and must have given them any notice the law requires. We may contact that person to confirm.</p>
<p>Contact for questions about this Policy: Grievance Officer, Aurex Network (Proprietor), <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a>. Our full complaint process is on the <a href="{{ route('legal.grievance') }}">/grievance</a> page. If you are not satisfied with our response to a personal data grievance, you may complain to the Data Protection Board of India once its complaint mechanism is in operation.</p>

<h2>10. Age restriction</h2>
<p>jannayaks.in is for people aged 18 and over. We do not knowingly collect personal data of anyone under 18, do not accept profiles of persons under 18, and do not track or monitor the behaviour of children or direct advertising at them. If we learn that data of a person under 18 has been submitted, we will remove it.</p>

<h2>11. Cookies</h2>
<p>We use essential cookies and similar technologies for login sessions, security and basic site functions, including security cookies set by Cloudflare. We do not use advertising cookies. If we later add analytics or marketing technologies, we will update this Policy and ask for your consent where the law requires. The Google Translate feature may set its own cookie when you use it.</p>

<h2>12. Language</h2>
<p>This Policy is available in English. You may ask for a Malayalam version by writing to <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a>.</p>

<h2>13. Changes and contact</h2>
<p>We may update this Policy by publishing a revised version with a new effective date, and will tell you of material changes where the law requires. For privacy requests write to <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a>. Postal address: Aurex Network, a sole proprietorship operating from 3-532A, Manchadi Jn, Moongode P.O., Thiruvananthapuram, Kerala 695573, India. Related processing for IconsHerald is described in that site’s own Privacy Policy.</p>
@endsection
