@extends('layouts.app')

@php
    $ml = ($language ?? 'ml') === 'ml';
@endphp

@section('content')
<section class="card stack">
    <div>
        <span class="tag">STEP 1 OF 3</span>
        <span class="list-pill">{{ $ml ? 'പ്രൊഫൈൽ' : 'Standard profile' }}</span>
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
    @php($quote = \App\Support\PricingAmounts::forStandardProfile())
    <h1>{{ $ml ? 'നിങ്ങളുടെ പ്രൊഫൈൽ' : 'Your Jannayaks profile' }}</h1>
    <p class="sub">
        {{ $ml ? 'ഒരു സ്റ്റാൻഡേർഡ് പ്രൊഫൈൽ. തിരഞ്ഞെടുക്കാനുള്ള പാക്കേജ് ശ്രേണികളില്ല.' : 'One standard profile. There is no package tier to choose.' }}
        {{ $ml ? 'പ്രൊഫൈൽ തയ്യാറാക്കലും ആദ്യ വർഷത്തെ പ്രസിദ്ധീകരണവും ഈ തുകയിൽ ഉൾപ്പെടുന്നു.' : 'This payment includes profile creation and the first year of publication and hosting.' }}
    </p>

    <div class="warnbox" role="note" aria-label="Pricing note">
        <div class="price" style="font-size:22px">
            @if($quote['compare_at_base_formatted'])
                <s style="color:var(--ink-soft);font-weight:500;margin-right:8px">{{ $quote['compare_at_base_formatted'] }}</s>
            @endif
            {{ $quote['base_formatted'] }}
            <span style="font-size:13px;font-weight:600">+ GST</span>
        </div>
        <p style="margin:8px 0 0">
            {{ $ml ? 'പേയ്‌മെന്റ് അടുത്ത ഘട്ടമാണ്. ഓൺലൈൻ അഭിമുഖവും രേഖകളും പണമടച്ച ശേഷം തുറക്കും.' : 'Payment comes next. The Online Interview and source-material uploads unlock after payment is settled.' }}
            {{ $ml ? 'വാർഷിക പുതുക്കൽ' : 'Annual renewal is' }} {{ \App\Support\PricingAmounts::forTierRenewal(\App\Support\PricingAmounts::STANDARD_INTERNAL_TIER)['amount_incl_formatted'] }}
            {{ $ml ? '(ബാധകമായ GST ഉൾപ്പെടെ).' : 'inclusive of applicable GST.' }}
        </p>
    </div>

    <form method="POST" action="{{ route('apply.intent') }}" novalidate>
        @csrf
        <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">

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
                <input id="preferred_slug" name="preferred_slug" type="text" pattern="^[a-z0-9]+(?:[.\-_][a-z0-9]+)*$" maxlength="128" value="{{ old('preferred_slug') }}" placeholder="e.g. arun.kumar">
                <div class="hint">{{ $ml ? 'ഇത് ഓപ്ഷണലാണ്. നിങ്ങളുടെ പേര് രേഖപ്പെടുത്തിയ ശേഷം, arun.kumar പോലുള്ള ലഭ്യമായ പേരിനെ അടിസ്ഥാനമാക്കിയുള്ള URL-കൾ Jannayaks നിർദ്ദേശിക്കും. നിങ്ങൾ സ്വയം ഒരു username സൃഷ്ടിക്കേണ്ടതില്ല.' : 'Optional. After your name is on file, Jannayaks will suggest available name-based URLs such as arun.kumar. You do not invent a username.' }}</div>
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
        </div>

        <div class="missbox" role="note" id="contactNote" style="margin-top:14px">
            {{ $ml ? 'വിശ്വസനീയമായ ഒരു ബന്ധപ്പെടൽ മാർഗമെങ്കിലും (ഇമെയിൽ അല്ലെങ്കിൽ മൊബൈൽ നമ്പർ) നൽകുക. എഡിറ്റോറിയൽ പരിശോധനയ്ക്കും തയ്യാറാക്കിയ പ്രൊഫൈൽ സ്ഥിരീകരിക്കുന്നതിനുമായി Jannayaks നിങ്ങളുമായി ബന്ധപ്പെടും.' : 'Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.' }}
        </div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('apply') }}">← {{ $ml ? 'പിന്നോട്ട്' : 'Back' }}</a>
            <button class="btn primary" type="submit">{{ !empty($guest) ? 'Continue to sign in →' : ($ml ? 'പണമടയ്ക്കുന്നതിലേക്ക് തുടരുക →' : 'Continue to payment →') }}</button>
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
})();
</script>
@endpush
