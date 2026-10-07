@extends('layouts.app')

@section('content')
<section class="card stack">
    <div>
        <span class="tag">STEP 1 OF 3</span>
        <span class="list-pill">Select package tier</span>
    </div>
    <h1>Choose how deep we go.</h1>
    <p class="sub">
        Tier selection is <b>unrestricted</b>. Jannayaks does not check profession, public-office status, age, geography or achievements to decide your eligibility.
        Every tier answers the same complete Online Interview; your choice controls the editorial depth and treatment.
    </p>

    <div class="warnbox" role="note" aria-label="Pricing note">
        After you choose a tier and sign in, <b>payment comes next</b>. The Online Interview and source-material uploads unlock after payment is settled.
        The prices below are exclusive of GST; 18% GST is added at payment. Tiers renew annually at the same tier price.
    </div>

    <form method="POST" action="{{ route('apply.intent') }}" novalidate>
        @csrf
        <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">

        <div class="grid tiers" role="radiogroup" aria-label="Package tier selection">
            @php
                $packages = config('jannayaks.tier_pricing.packages', []);
                $tierNamesMl = [
                    'emerging' => 'ജനകീയർ',
                    'accomplished' => 'ജനസമ്മതർ',
                    'distinguished' => 'പ്രമുഖർ',
                ];
                $tiers = ['emerging' => [
                    'title' => \App\Support\TierLabels::label('emerging'),
                    'sections' => ['Full Online Interview — all questions', 'Concise but complete editorial portrait (EN + ML)', '1 photo slot', 'Human editorial review before publication'],
                    'include' => ['Same complete Online Interview as every tier', '1 photo slot', 'Documentary editorial treatment', 'Review + approval before publication'],
                ], 'accomplished' => [
                    'title' => \App\Support\TierLabels::label('accomplished'),
                    'sections' => ['Full Online Interview — all questions', 'Substantially developed editorial feature (EN + ML)', '3 photo slots', 'Video link'],
                    'include' => ['Same complete Online Interview as every tier', '3 photo slots', 'Video link', 'Expanded contribution editorial'],
                ], 'distinguished' => [
                    'title' => \App\Support\TierLabels::label('distinguished'),
                    'sections' => ['Full Online Interview — all questions', 'Deeply developed long-form editorial profile (EN + ML)', '5 photo slots', 'Optional direct personal interview via external professional service (add-on)'],
                    'include' => ['Same complete Online Interview as every tier', '5 photo slots', 'Video link'],
                ]];
            @endphp
            @foreach (['emerging', 'accomplished', 'distinguished'] as $tier)
                @php $pkg = $packages[$tier] ?? []; $meta = $tiers[$tier]; $amt = number_format((int)($pkg['base_amount'] ?? 0)); @endphp
                <label class="tier">
                    <input type="radio" name="package_tier" value="{{ $tier }}" @if(old('package_tier')===$tier) checked @endif required>
                    <h3>
                        <span>{{ $meta['title'] }}</span>
                        <span class="tier-name-ml" style="display:block;font-size:12px;font-weight:500;color:var(--ink-soft)">{{ $tierNamesMl[$tier] }}</span>
                    </h3>
                    <div class="price">₹{{ $amt }}<span style="font-size:12px;color:var(--ink-soft);font-weight:600;margin-left:6px">+ GST / year</span></div>
                    <ul>
                        @foreach ($meta['sections'] as $s)
                            <li>{{ $s }}</li>
                        @endforeach
                    </ul>
                </label>
            @endforeach
        </div>

        <div class="divider"></div>

        <h2 style="font-size:16px">Intake source</h2>
        <p class="sub">Choose how we will receive the content for this application.</p>
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
            <label class="tier" style="padding:14px">
                <input type="radio" name="source_method" value="online_interview" checked>
                <h3 style="font-size:15px">Online Interview (recommended)</h3>
                <p class="note-safe" style="margin:4px 0 0">Answer the interview directly here. Save and continue anytime.</p>
            </label>
            <label class="tier" style="padding:14px">
                <input type="radio" name="source_method" value="direct_submission">
                <h3 style="font-size:15px">Direct submission</h3>
                <p class="note-safe" style="margin:4px 0 0">Upload résumés, articles, notes separately. You may still add a note.</p>
            </label>
        </div>

        <div class="divider"></div>

        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
            <div class="field">
                <label for="full_name">Full name (as you would like it to appear on the editorial brief)</label>
                <input id="full_name" name="full_name" type="text" minlength="2" maxlength="255" required value="{{ old('full_name') }}" autocomplete="name">
                <div class="hint">Used internally and to seed the preferred display name. Final public name is confirmed later in editorial review.</div>
                @error('full_name')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="preferred_slug">Preferred personal URL (optional hint)</label>
                <input id="preferred_slug" name="preferred_slug" type="text" pattern="^[a-z0-9]+(?:[.\-_][a-z0-9]+)*$" maxlength="128" value="{{ old('preferred_slug') }}" placeholder="e.g. arun.kumar">
                <div class="hint">Optional. After your verified name is on file, Jannayaks will suggest available name-based URLs such as arun.kumar. You do not invent a username.</div>
                @error('preferred_slug')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_email">Contact email</label>
                <input id="contact_email" name="contact_email" type="email" maxlength="255" value="{{ old('contact_email') }}" autocomplete="email">
                @error('contact_email')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="contact_mobile">Contact mobile (optional)</label>
                <input id="contact_mobile" name="contact_mobile" type="tel" maxlength="32" value="{{ old('contact_mobile') }}" autocomplete="tel">
                @error('contact_mobile')<div class="hint" style="color:#7a1414">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="missbox" role="note" id="contactNote" style="margin-top:14px">
            Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.
        </div>

        <div class="actions">
            <a class="btn ghost" href="{{ route('apply') }}">← Back</a>
            <button class="btn primary" type="submit">{{ !empty($guest) ? 'Continue to sign in →' : 'Continue to payment →' }}</button>
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
            ? 'Contact information captured. You can add more or update them later in the application dashboard.'
            : 'Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.';
    }
    if (email) email.addEventListener('input', syncContact);
    if (mobile) mobile.addEventListener('input', syncContact);
    syncContact();
})();
</script>
@endpush
