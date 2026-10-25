@extends('layouts.app')

@php
    $ml = ($language ?? 'ml') === 'ml';
@endphp

@section('content')
<section class="card stack">
    <div>
        <span class="tag">STEP 1 OF 3</span>
        <span class="list-pill">Standard Profile</span>
        <span style="float:right" role="navigation" aria-label="Language">
            @if($ml)
                <a class="list-pill" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" lang="en">English</a>
                <span class="list-pill" aria-current="true" style="color:var(--ink)">മലയാളം</span>
            @else
                <span class="list-pill" aria-current="true" style="color:var(--ink)">English</span>
                <a class="list-pill" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['lang' => 'ml']) }}" lang="ml">മലയാളം</a>
            @endif
        </span>
    </div>
    <h1>{{ $ml ? 'നിങ്ങളുടെ പ്രൊഫൈൽ അപേക്ഷ ആരംഭിക്കുക' : 'Begin your profile application' }}</h1>
    <p class="sub">
        {{ $ml ? 'ഒരു Standard Profile മാത്രമേയുള്ളൂ. എല്ലാവരും ഒരേ ഓൺലൈൻ അഭിമുഖത്തിന് ഉത്തരം നൽകുന്നു.' : 'There is one Standard Profile. Everyone answers the same Online Interview.' }}
    </p>

    @php($standardQuote = \App\Support\PricingAmounts::forStandardProfile())
    @php($renewalQuote = \App\Support\PricingAmounts::forTierRenewal(\App\Support\PricingAmounts::STANDARD_INTERNAL_TIER))
    <div class="warnbox" role="note" aria-label="Pricing note">
        {{ $ml ? 'Standard Profile നിരക്ക്' : 'The Standard Profile fee is' }}
        <b>{{ $standardQuote['base_formatted'] }} + GST</b>.
        {{ $ml ? 'വാർഷിക പുതുക്കൽ' : 'Annual renewal is' }}
        <b>{{ $renewalQuote['base_formatted'] }} + GST</b>.
        {{ $ml ? 'ഓൺലൈൻ അഭിമുഖം തിരഞ്ഞെടുത്താൽ ചോദ്യാവലി സമർപ്പിച്ച ശേഷമേ പേയ്‌മെന്റ് വരൂ. നേരിട്ടുള്ള സമർപ്പണത്തിൽ ആദ്യം രേഖകൾ അപ്‌ലോഡ് ചെയ്യാം.' : 'If you choose Online Interview, payment comes after the questionnaire is submitted. Direct submission starts with document upload.' }}
    </div>

    <form method="POST" action="{{ route('apply.intent') }}" novalidate>
        @csrf
        <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">

        <div class="divider"></div>

        <h2 style="font-size:16px">{{ $ml ? 'ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി' : 'Intake source' }}</h2>
        <p class="sub">{{ $ml ? 'ഈ അപേക്ഷയ്ക്കുള്ള ഉള്ളടക്കം എങ്ങനെ ഞങ്ങൾക്ക് ലഭ്യമാക്കണമെന്ന് തിരഞ്ഞെടുക്കുക.' : 'Choose how we will receive the content for this application.' }}</p>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <label class="tier" style="padding:14px">
                <input type="radio" name="source_method" value="online_interview" @if(old('source_method', 'online_interview') === 'online_interview') checked @endif>
                <h3 style="font-size:15px">{{ $ml ? 'ഓൺലൈൻ അഭിമുഖം (ശുപാർശ ചെയ്യുന്നത്)' : 'Online Interview (recommended)' }}</h3>
                <p class="note-safe" style="margin:4px 0 0">{{ $ml ? 'ഇവിടെത്തന്നെ അഭിമുഖ ചോദ്യങ്ങൾക്ക് ഉത്തരം നൽകാം. എപ്പോൾ വേണമെങ്കിലും വിവരങ്ങൾ സേവ് ചെയ്ത് പിന്നീട് തുടരാം.' : 'Answer the interview directly here. Save and continue anytime.' }}</p>
            </label>
            <label class="tier" style="padding:14px">
                <input type="radio" name="source_method" value="direct_submission" @if(old('source_method') === 'direct_submission') checked @endif>
                <h3 style="font-size:15px">{{ $ml ? 'നേരിട്ടുള്ള സമർപ്പണം' : 'Direct submission' }}</h3>
                <p class="note-safe" style="margin:4px 0 0">{{ $ml ? 'ബയോഡാറ്റ, ലേഖനങ്ങൾ, കുറിപ്പുകൾ എന്നിവ പ്രത്യേകം അപ്‌ലോഡ് ചെയ്യാം. ആവശ്യമെങ്കിൽ ഒരു കുറിപ്പും ചേർക്കാം.' : 'Upload résumés, articles, notes separately. You may still add a note.' }}</p>
            </label>
        </div>
        @error('source_method')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror

        <div class="divider"></div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <div class="field">
                <label for="full_name">{{ $ml ? 'പൂർണ്ണനാമം (എഡിറ്റോറിയൽ പ്രൊഫൈലിൽ പ്രദർശിപ്പിക്കേണ്ടതുപോലെ)' : 'Full name (as you would like it to appear on the editorial brief)' }}</label>
                <input id="full_name" name="full_name" type="text" minlength="2" maxlength="255" required value="{{ old('full_name') }}" autocomplete="name">
                <div class="hint">{{ $ml ? 'ഇത് ആഭ്യന്തര ആവശ്യങ്ങൾക്കും നിങ്ങൾ ആഗ്രഹിക്കുന്ന പ്രദർശനനാമം തയ്യാറാക്കുന്നതിനുമായി ഉപയോഗിക്കും. അന്തിമമായി പ്രദർശിപ്പിക്കുന്ന പേര് എഡിറ്റോറിയൽ പരിശോധനയ്ക്കിടെ സ്ഥിരീകരിക്കും.' : 'Used internally and to seed the preferred display name. Final public name is confirmed later in editorial review.' }}</div>
                @error('full_name')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="preferred_slug">{{ $ml ? 'ആഗ്രഹിക്കുന്ന വ്യക്തിഗത URL (ഓപ്ഷണൽ നിർദ്ദേശം)' : 'Preferred personal URL (optional hint)' }}</label>
                <input id="preferred_slug" name="preferred_slug" type="text" maxlength="128" value="{{ old('preferred_slug') }}" placeholder="e.g. arun.kumar">
                <div class="hint">{{ $ml ? 'ഇത് ഓപ്ഷണലാണ്. ശൂന്യമായി വിട്ടാൽ അപേക്ഷ തടയില്ല. നൽകുകയാണെങ്കിൽ, പൂർണ്ണനാമത്തിൽ നിന്നുള്ള ചെറിയക്ഷര രൂപം മാത്രം — ഉദാഹരണം sree അല്ലെങ്കിൽ arun.kumar. വലിയ അക്ഷരം സ്വയം ചെറുതാക്കും. മറ്റൊരു പേര് സ്വീകരിക്കില്ല.' : 'Optional. Leave it empty and the application still continues. A hint must come from the full name, such as sree or arun.kumar. Capitals are accepted and stored in lowercase. An unrelated alias is not.' }}</div>
                @error('preferred_slug')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_email">{{ $ml ? 'ബന്ധപ്പെടാനുള്ള ഇമെയിൽ' : 'Contact email' }}</label>
                <input id="contact_email" name="contact_email" type="email" maxlength="255" value="{{ old('contact_email') }}" autocomplete="email">
                @error('contact_email')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_mobile">{{ $ml ? 'ബന്ധപ്പെടാനുള്ള മൊബൈൽ നമ്പർ (ഓപ്ഷണൽ)' : 'Contact mobile (optional)' }}</label>
                <input id="contact_mobile" name="contact_mobile" type="tel" maxlength="32" value="{{ old('contact_mobile') }}" autocomplete="tel">
                @error('contact_mobile')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_whatsapp">{{ $ml ? 'വാട്ട്സ്ആപ്പ് നമ്പർ (ഓപ്ഷണൽ)' : 'WhatsApp number (optional)' }}</label>
                <input id="contact_whatsapp" name="contact_whatsapp" type="tel" maxlength="32" value="{{ old('contact_whatsapp') }}" autocomplete="tel">
                @error('contact_whatsapp')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="missbox" role="note" id="contactNote" style="margin-top:14px">
            {{ $ml ? 'വിശ്വസനീയമായ ഒരു ബന്ധപ്പെടൽ മാർഗമെങ്കിലും (ഇമെയിൽ അല്ലെങ്കിൽ മൊബൈൽ നമ്പർ) നൽകുക. എഡിറ്റോറിയൽ പരിശോധനയ്ക്കും തയ്യാറാക്കിയ പ്രൊഫൈൽ സ്ഥിരീകരിക്കുന്നതിനുമായി Jannayaks നിങ്ങളുമായി ബന്ധപ്പെടും.' : 'Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.' }}
        </div>

        <div class="divider"></div>
        <fieldset style="border:0;padding:0;margin:0">
            <legend style="font-weight:600;margin-bottom:8px">{{ $ml ? 'വിശാലമായ സ്ഥലം' : 'Broad location' }}</legend>
            <p class="hint" style="margin-top:0">{{ $ml ? 'ഇത് ഒരിക്കൽ മാത്രം ചോദിക്കുന്നു. വീട്ടുവിലാസം ആവശ്യമില്ല.' : 'This is collected once, with the other basic information. A home address is not required.' }}</p>
            <label style="display:block;margin:8px 0">
                <input type="radio" name="location_scope" value="kerala" @checked(old('location_scope', 'kerala') === 'kerala')>
                {{ $ml ? 'ഈ പ്രൊഫൈൽ കേരളവുമായി ബന്ധപ്പെട്ടതാണ്' : 'This profile is associated with Keralam' }}
            </label>
            <label style="display:block;margin:8px 0 14px">
                <input type="radio" name="location_scope" value="elsewhere" @checked(old('location_scope') === 'elsewhere')>
                {{ $ml ? 'മറ്റെവിടെയെങ്കിലും' : 'Somewhere else' }}
            </label>
            <div id="locationKerala" @if(old('location_scope') === 'elsewhere') hidden @endif>
                <div class="field">
                    <label for="district_id">{{ $ml ? 'ജില്ല' : 'District' }}</label>
                    <select id="district_id" name="district_id">
                        <option value="">{{ $ml ? 'ജില്ല തിരഞ്ഞെടുക്കുക' : 'Choose a district' }}</option>
                        @foreach(($districts ?? []) as $district)
                            <option value="{{ $district->id }}" @selected((string) old('district_id') === (string) $district->id)>{{ $district->name }}</option>
                        @endforeach
                    </select>
                    @error('district_id')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
                </div>
            </div>
            <div id="locationElsewhere" @if(old('location_scope', 'kerala') !== 'elsewhere') hidden @endif>
                <div class="field">
                    <label for="broad_location">{{ $ml ? 'പട്ടണം, നഗരം അല്ലെങ്കിൽ മറ്റൊരു സ്ഥലം' : 'Town, city or other place' }}</label>
                    <input id="broad_location" name="broad_location" type="text" maxlength="120" value="{{ old('broad_location') }}">
                    @error('broad_location')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="location_state">{{ $ml ? 'സംസ്ഥാനം അല്ലെങ്കിൽ പ്രവിശ്യ' : 'State or province' }}</label>
                    <input id="location_state" name="location_state" type="text" maxlength="120" value="{{ old('location_state') }}">
                    @error('location_state')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="location_country">{{ $ml ? 'രാജ്യം' : 'Country' }}</label>
                    <input id="location_country" name="location_country" type="text" maxlength="80" value="{{ old('location_country') }}">
                    @error('location_country')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
                </div>
            </div>
            @error('location_scope')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
        </fieldset>

        <div class="actions">
            <a class="btn ghost" href="{{ route('apply') }}">← {{ $ml ? 'പിന്നോട്ട്' : 'Back' }}</a>
            @php($interviewAction = $ml ? 'ഓൺലൈൻ അഭിമുഖത്തിലേക്ക് തുടരുക →' : 'Continue to Online Interview →')
            @php($directAction = $ml ? 'രേഖകൾ സമർപ്പിക്കുന്നതിലേക്ക് തുടരുക →' : 'Continue to document submission →')
            @php($signedInAction = old('source_method', 'online_interview') === 'direct_submission' ? $directAction : $interviewAction)
            <button class="btn primary" type="submit" id="applyNext" @if(empty($guest)) data-interview="{{ $interviewAction }}" data-direct="{{ $directAction }}" @endif>{{ !empty($guest) ? 'Continue to sign in →' : $signedInAction }}</button>
        </div>
    </form>
</section>
@endsection

@push('scripts')
<script>
(function(){
    var email = document.getElementById('contact_email');
    var mobile = document.getElementById('contact_mobile');
    var note = document.getElementById('contactNote');
    function syncContact(){
        var ok = (email && email.value.trim() !== '') || (mobile && mobile.value.trim() !== '');
        if (!note) return;
        note.style.borderLeftColor = ok ? '#2e8b57' : '#c0392b';
        note.style.background = ok ? '#eef7f1' : '#fbefee';
        note.style.color = ok ? '#0e4a23' : '#5a0b0b';
        note.textContent = ok
            ? @json($ml ? 'ബന്ധപ്പെടാനുള്ള വിവരങ്ങൾ രേഖപ്പെടുത്തി. എഡിറ്റോറിയൽ പരിശോധനയ്ക്കും തയ്യാറാക്കിയ പ്രൊഫൈൽ സ്ഥിരീകരിക്കുന്നതിനുമായി Jannayaks നിങ്ങളുമായി ബന്ധപ്പെടും.' : 'Contact information captured. You can add more or update them later in the application dashboard.')
            : @json($ml ? 'വിശ്വസനീയമായ ഒരു ബന്ധപ്പെടൽ മാർഗമെങ്കിലും (ഇമെയിൽ അല്ലെങ്കിൽ മൊബൈൽ നമ്പർ) നൽകുക. എഡിറ്റോറിയൽ പരിശോധനയ്ക്കും തയ്യാറാക്കിയ പ്രൊഫൈൽ സ്ഥിരീകരിക്കുന്നതിനുമായി Jannayaks നിങ്ങളുമായി ബന്ധപ്പെടും.' : 'Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.');
    }
    if (email) email.addEventListener('input', syncContact);
    if (mobile) mobile.addEventListener('input', syncContact);
    syncContact();

    var next = document.getElementById('applyNext');
    if (next && next.dataset.interview) {
        var sources = document.querySelectorAll('input[name="source_method"]');
        function syncAction() {
            var chosen = document.querySelector('input[name="source_method"]:checked');
            next.textContent = chosen && chosen.value === 'direct_submission' ? next.dataset.direct : next.dataset.interview;
        }
        sources.forEach(function (input) { input.addEventListener('change', syncAction); });
        syncAction();
    }

    var kerala = document.getElementById('locationKerala');
    var elsewhere = document.getElementById('locationElsewhere');
    var scopes = document.querySelectorAll('input[name="location_scope"]');
    function syncLocation() {
        var chosen = document.querySelector('input[name="location_scope"]:checked');
        var away = chosen && chosen.value === 'elsewhere';
        if (kerala) kerala.hidden = !!away;
        if (elsewhere) elsewhere.hidden = !away;
    }
    scopes.forEach(function (input) { input.addEventListener('change', syncLocation); });
    syncLocation();
})();
</script>
@endpush
