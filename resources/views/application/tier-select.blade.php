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
        Your choice only controls the scope of the Online Interview and the editorial treatment.
    </p>

    <div class="warnbox" role="note" aria-label="Pricing note">
        Payment is handled as a <b>separate workflow</b> after your profile moves into editorial preparation.
        The prices below are inclusive of GST and will not change.
    </div>

    <form method="POST" action="{{ route('applications.store') }}" novalidate>
        @csrf
        <input type="hidden" name="honey_bot" value="" maxlength="0" autocomplete="off" tabindex="-1" aria-hidden="true">

        <div class="grid tiers" role="radiogroup" aria-label="Package tier selection">
            @php
                $packages = config('jannayaks.tier_pricing.packages', []);
                $tiers = ['emerging' => [
                    'title' => 'Emerging Leader',
                    'sections' => ['About You', 'Your Journey', 'Looking Back', 'Closing'],
                    'include' => ['14-question backbone (Emerging scoped)', '1 photo slot', 'Documentary editorial treatment', 'Review + approval before publication'],
                ], 'accomplished' => [
                    'title' => 'Accomplished Leader',
                    'sections' => ['Emerging content', '+ Your Contribution', '+ Experiences & Challenges', '+ 3 photo slots', '+ video link'],
                    'include' => ['11 unlocked questions', '3 photo slots', 'Video link', 'Expanded contribution editorial'],
                ], 'distinguished' => [
                    'title' => 'Distinguished Leader',
                    'sections' => ['Full interview: Responsibilities & Recognition', 'The Person Behind the Public Life', '5 photo slots', 'In-person journalist interview optional'],
                    'include' => ['All 14 backbone questions + closing', '5 photo slots', 'Video link', 'Senior journalist editorial pass'],
                ]];
            @endphp
            @foreach (['emerging', 'accomplished', 'distinguished'] as $tier)
                @php $pkg = $packages[$tier] ?? []; $meta = $tiers[$tier]; $amt = number_format((int)($pkg['base_amount'] ?? 0)); @endphp
                <label class="tier">
                    <input type="radio" name="package_tier" value="{{ $tier }}" @if(old('package_tier')===$tier) checked @endif required>
                    <h3>
                        <span>{{ $meta['title'] }}</span>
                    </h3>
                    <div class="price">₹{{ $amt }}<span style="font-size:12px;color:var(--ink-soft);font-weight:600;margin-left:6px">incl. GST</span></div>
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
                <label for="preferred_slug">Preferred short URL slug (optional)</label>
                <input id="preferred_slug" name="preferred_slug" type="text" pattern="^[a-z0-9]+(?:[-_][a-z0-9]+)*$" maxlength="128" value="{{ old('preferred_slug') }}" placeholder="e.g. vijayan-k-m">
                <div class="hint">Letters, numbers, single hyphens/underscores. Editorial review will confirm before publication.</div>
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
            <button class="btn primary" type="submit">Create application →</button>
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
    function sync(){
        var ok = (email && email.value.trim() !== '') || (mobile && mobile.value.trim() !== '');
        if (!note) return;
        note.style.borderLeftColor = ok ? '#2e8b57' : '#c0392b';
        note.style.background = ok ? '#eef7f1' : '#fbefee';
        note.style.color = ok ? '#0e4a23' : '#5a0b0b';
        note.textContent = ok
            ? 'Contact information captured. You can add more or update it later in the application dashboard.'
            : 'Provide at least one reliable contact method (email or mobile). Jannayaks will contact you for editorial review and to confirm the finished profile.';
    }
    if (email) email.addEventListener('input', sync);
    if (mobile) mobile.addEventListener('input', sync);
    sync();
})();
</script>
@endpush
