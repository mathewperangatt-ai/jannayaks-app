@extends('legal.base', ['legalActive' => 'disclaimer', 'legalEffectiveDate' => '10 November 2026'])

@section('title', 'Disclaimer — Jannayaks')
@section('seoDescription', 'How Jannayaks profiles are prepared and what to check before relying on them: prepared from information supplied by profile owners, editorially reviewed, not independently verified.')
@section('seoCanonical', route('legal.disclaimer'))
@section('legal_heading', 'Disclaimer')
@section('legal_intro', 'Information on Jannayaks — how profiles are prepared, what to check before you rely on them, and who is responsible for their accuracy.')

@section('legal_body')
<h2>Information on Jannayaks</h2>
<p>Jannayaks profiles are prepared from information and material supplied directly by the profile owner. Our editorial team, with the help of artificial intelligence, edits and reviews each profile before publication. We check profiles for editorial quality and suitability, but we do not independently verify every statement in them.</p>

<h2>Please check before you rely</h2>
<p>If you plan to contact, hire, donate to, or do business with anyone listed on Jannayaks, please check the facts yourself first. A profile is an editorial presentation of information supplied by the individual. It is not a certificate, recommendation or endorsement.</p>

<h2>Responsibility</h2>
<p>Each profile owner is responsible for the accuracy of the information they provide. Jannayaks is not a party to any dealings between users and is not responsible for them. If you believe a profile contains an error or a false statement, please tell us through the <a href="{{ route('legal.grievance') }}">Grievance Redressal page</a>.</p>

<hr style="border:0;border-top:1.5px solid var(--border);margin:28px 0">

<h2>Jannayaks-ലെ വിവരങ്ങളെക്കുറിച്ച്</h2>
<p>Jannayaks-ലെ പ്രൊഫൈലുകൾ തയ്യാറാക്കുന്നത് അതത് വ്യക്തികൾ നേരിട്ട് നൽകുന്ന വിവരങ്ങളുടെയും രേഖകളുടെയും അടിസ്ഥാനത്തിലാണ്. ഞങ്ങളുടെ Editorial Team, Artificial Intelligence (AI) യുടെ സഹായത്തോടെ ഓരോ പ്രൊഫൈലും എഡിറ്റ് ചെയ്ത് പ്രസിദ്ധീകരണത്തിന് മുമ്പ് പരിശോധിക്കുന്നു. ഭാഷ, വ്യക്തത, അവതരണരീതി, പ്രസിദ്ധീകരണത്തിനുള്ള അനുയോജ്യത എന്നിവയാണ് ഈ പരിശോധനയിൽ പ്രധാനമായും പരിഗണിക്കുന്നത്. എന്നാൽ പ്രൊഫൈലിൽ ഉൾപ്പെടുത്തിയിട്ടുള്ള എല്ലാ വസ്തുതകളും ഞങ്ങൾ സ്വതന്ത്രമായി സ്ഥിരീകരിക്കുന്നില്ല.</p>

<h2>വിവരങ്ങൾ ആശ്രയിക്കുന്നതിന് മുമ്പ് പരിശോധിക്കുക</h2>
<p>Jannayaks-ൽ പരിചയപ്പെടുത്തിയിട്ടുള്ള ആരെയെങ്കിലും ബന്ധപ്പെടുന്നതിനോ, ജോലി നൽകുന്നതിനോ, സംഭാവന നൽകുന്നതിനോ, അവരുമായി ഏതെങ്കിലും തരത്തിലുള്ള ബിസിനസ് അല്ലെങ്കിൽ മറ്റ് ഇടപാടുകളിൽ ഏർപ്പെടുന്നതിനോ മുമ്പ് ബന്ധപ്പെട്ട വിവരങ്ങളുടെ കൃത്യത നിങ്ങൾ സ്വയം പരിശോധിക്കേണ്ടതാണ്.</p>
<p>ഒരു പ്രൊഫൈൽ വ്യക്തി നൽകിയ വിവരങ്ങളുടെ അടിസ്ഥാനത്തിൽ തയ്യാറാക്കിയ എഡിറ്റോറിയൽ അവതരണമാണ്. അത് ഒരു സാക്ഷ്യപത്രമോ ശുപാർശയോ ഔദ്യോഗിക അംഗീകാരമോ അല്ല.</p>

<h2>ഉത്തരവാദിത്തം</h2>
<p>ഓരോ പ്രൊഫൈലിലും നൽകുന്ന വിവരങ്ങളുടെ കൃത്യത ഉറപ്പുവരുത്തേണ്ടത് അതത് പ്രൊഫൈൽ ഉടമയുടെ ഉത്തരവാദിത്തമാണ്. Jannayaks ഉപയോക്താക്കൾ തമ്മിലുള്ള ഇടപാടുകളിൽ കക്ഷിയല്ല. അത്തരം ഇടപാടുകളിൽ നിന്നുണ്ടാകുന്ന കാര്യങ്ങളുടെ ഉത്തരവാദിത്തവും Jannayaks ഏറ്റെടുക്കുന്നില്ല.</p>
<p>ഒരു പ്രൊഫൈലിൽ തെറ്റായ വിവരങ്ങളോ വസ്തുതാപരമായ പിശകുകളോ ഉള്ളതായി ശ്രദ്ധയിൽപ്പെട്ടാൽ, ഞങ്ങളുടെ <a href="{{ route('legal.grievance') }}">Grievance Redressal പേജ്</a> വഴി അറിയിക്കാവുന്നതാണ്.</p>
@endsection
