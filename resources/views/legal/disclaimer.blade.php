@extends('legal.base', ['legalActive' => 'disclaimer', 'legalEffectiveDate' => '10 November 2026'])

@section('title', 'Disclaimer — Jannayaks')
@section('seoDescription', 'What Jannayaks profiles are and are not: AI-prepared, editor-checked biographical presentations, not endorsements, verifications or guarantees of any outcome.')
@section('seoCanonical', route('legal.disclaimer'))
@section('legal_heading', 'Disclaimer')
@section('legal_intro', 'This Disclaimer should be read with the Terms and Conditions and the Privacy Policy.')

@section('legal_body')
<h2>1. Nature of profiles</h2>
<p>A published profile is a biographical presentation based on information and materials supplied by the applicant or an authorised contributor, prepared first using artificial intelligence tools and then checked by human editors.</p>
<p>Unless a particular verification process is expressly identified, publication does not mean that Aurex Network has independently verified every statement, credential, office held, award, employment history, achievement, affiliation, photograph or link.</p>

<h2>2. Responsibility for submitted information</h2>
<p>The applicant or authorised contributor is responsible for the accuracy, completeness and lawful submission of the information supplied, and should review the final profile carefully before approval and report any error to us.</p>

<h2>3. No endorsement and political neutrality</h2>
<p>Inclusion of a profile is not an endorsement of the person, or of any political party, organisation, candidate, cause or opinion. Views and claims in a profile are those of the subject or contributor, not of Aurex Network.</p>

<h2>4. No guaranteed outcome</h2>
<p>A profile is not a guarantee of election, appointment, employment, investment, business, media attention, search ranking, public recognition or any other outcome. Third parties may interpret or use public information independently.</p>

<h2>5. External links and services</h2>
<p>The platform may link to third-party websites, public databases, payment providers or other external systems. We do not control their content, availability, security or practices. Links do not necessarily imply endorsement.</p>

<h2>6. Limitation</h2>
<p>Nothing in this Disclaimer excludes a duty or liability that cannot lawfully be excluded. It must be read consistently with applicable consumer, privacy and other Indian laws.</p>

<h2>7. Contact</h2>
<p>For corrections or concerns about a published profile, write to <a href="mailto:founder@jannayaks.in">founder@jannayaks.in</a>.</p>
@endsection
