@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between">
        <div>
            <span class="tag">PUBLIC PROFILE URL</span>
        </div>
        <a class="btn ghost" style="padding:8px 12px;min-height:36px;font-size:14px" href="{{ route('applications.show', $application) }}">← Back to dashboard</a>
    </div>

    <h1>Your Jannayaks URL</h1>
    <p class="lead" style="margin:0">
        {{ \App\Support\PricingAmounts::isLivingTier((string) $tier) ? 'Standard Profile' : ucfirst((string) $tier) }}.
        @if($isPublished)
            Your public profile URL is active.
        @else
            A selected URL is reserved for you, but the public page stays hidden until publication.
        @endif
    </p>

    @if (session('status'))
        <p class="lead" style="color:var(--ok);margin:0">{{ session('status') }}</p>
    @endif

    @if ($errors->any())
        <div class="alert" style="color:var(--bad)">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <h2 style="margin-top:8px;font-size:16px">Current canonical URL</h2>
    @if($canonicalUrl)
        <p class="lead" style="margin:0"><code>{{ $canonicalUrl }}</code></p>
        <p class="sub" style="margin:0">Path: <code>/{{ $profile->slug }}</code></p>
    @else
        <p class="lead" style="margin:0">No canonical URL has been assigned yet.</p>
    @endif

    @php
        $selectedHosting = old('hosting_destination', $hostingDestination);
        $hostPrefix = $selectedHosting === \App\Models\Application::HOSTING_DESTINATION_ICONSHERALD
            ? 'iconsherald.com/'
            : 'jannayaks.in/';
    @endphp

    <h2 style="margin-top:14px;font-size:16px">Where should your profile be hosted?</h2>
    @if($hostingDestinationLocked)
        <p class="lead" style="margin:0">
            @if($hostingDestination === \App\Models\Application::HOSTING_DESTINATION_ICONSHERALD)
                IconsHerald — <code>iconsherald.com/{{ $profile->slug }}</code>
            @else
                Jannayaks — <code>jannayaks.in/{{ $profile->slug }}</code>
            @endif
        </p>
        <p class="sub" style="margin:0">Hosting destination is locked after publication.</p>
    @else
        <p class="sub" style="margin:0">
            Choose one public address. Your profile content, editorial and publication stay on Jannayaks either way.
        </p>
    @endif

    @if($canSelectPersonal)
        <h2 style="margin-top:14px;font-size:16px">Choose your personal URL</h2>
        <p class="sub" style="margin:0">
            Name on the profile: <b>{{ $verifiedName }}</b>.
            These suggestions are derived from that name. Arbitrary usernames are not allowed.
        </p>
        @if($personalSlugLocked)
            <p class="lead" style="margin:0">Your personal URL is permanent and cannot be changed from this page.</p>
            @unless($hostingDestinationLocked)
                <form method="post" action="{{ route('applications.profile-url.hosting', $application) }}" class="stack" style="margin-top:10px">
                    @csrf
                    @include('application.partials.hosting-destination-fields', [
                        'selectedHosting' => $selectedHosting,
                        'hostPrefix' => $hostPrefix,
                        'slugPreview' => $profile->slug,
                    ])
                    <button type="submit" class="btn primary">Save hosting destination</button>
                </form>
            @endunless
        @else
            @if(count($suggestions))
                <ul class="stack" style="margin:10px 0 0;padding-left:0;list-style:none">
                    @foreach($suggestions as $suggestion)
                        <li style="display:flex;gap:8px;align-items:center">
                            @if($suggestion['available'])
                                <span aria-hidden="true">✓</span>
                                <button type="button" class="btn ghost js-slug-choice" data-slug="{{ $suggestion['slug'] }}" style="padding:6px 10px;min-height:36px">{{ $suggestion['slug'] }}</button>
                            @else
                                <span aria-hidden="true">✗</span>
                                <span class="sub"><code>{{ $suggestion['slug'] }}</code> (already taken)</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="post" action="{{ route('applications.profile-url.update', $application) }}" class="stack" style="margin-top:10px">
                @csrf
                @include('application.partials.hosting-destination-fields', [
                    'selectedHosting' => $selectedHosting,
                    'hostPrefix' => $hostPrefix,
                    'slugPreview' => null,
                ])
                <label for="slug">Personal URL</label>
                <div class="row" style="gap:8px;align-items:center">
                    <span class="sub" id="hosting-prefix">{{ $hostPrefix }}</span>
                    <input id="slug" name="slug" type="text" maxlength="64" required
                           value="{{ old('slug', $preferredSlug ?: $profile->slug) }}"
                           pattern="[a-z0-9]+(?:[.\-][a-z0-9]+)*"
                           style="flex:1;min-height:42px;padding:8px 12px;border:1px solid var(--line);border-radius:10px"
                           autocomplete="off">
                </div>
                <p class="sub" id="slug-availability" style="margin:0" aria-live="polite"></p>
                <button type="submit" class="btn primary">Reserve this URL</button>
            </form>
        @endif
    @else
        <h2 style="margin-top:14px;font-size:16px">System-assigned URL</h2>
        <p class="lead" style="margin:0">
            This profile has a six-character system address. A personal name-based address is not available for this application.
        </p>
        @unless($hostingDestinationLocked)
            <form method="post" action="{{ route('applications.profile-url.hosting', $application) }}" class="stack" style="margin-top:10px">
                @csrf
                @include('application.partials.hosting-destination-fields', [
                    'selectedHosting' => $selectedHosting,
                    'hostPrefix' => $hostPrefix,
                    'slugPreview' => $profile->slug,
                ])
                <button type="submit" class="btn primary">Save hosting destination</button>
            </form>
        @endunless
    @endif

    @if($isPublished && $canonicalUrl)
        <h2 style="margin-top:14px;font-size:16px">Share your profile</h2>
        <p class="sub" style="margin:0">
            Your permanent profile address is <code>{{ $canonicalUrl }}</code> — copy it or share it
            directly from your public profile page.
        </p>
    @endif

    @if($history->isNotEmpty())
        <h2 style="margin-top:14px;font-size:16px">Previous URLs</h2>
        <ul class="stack" style="margin:0;padding-left:18px">
            @foreach($history as $row)
                <li><code>/{{ $row->old_slug }}</code> → <code>/{{ $row->new_slug }}</code></li>
            @endforeach
        </ul>
    @endif
</section>
<script>
(() => {
    const prefixes = {
        jannayaks: 'jannayaks.in/',
        iconsherald: 'iconsherald.com/',
    };
    const prefixEl = document.getElementById('hosting-prefix');
    const previewEl = document.getElementById('hosting-preview');
    const slugInput = document.getElementById('slug');

    const syncHostingPrefix = () => {
        const selected = document.querySelector('input[name="hosting_destination"]:checked');
        const key = selected ? selected.value : 'jannayaks';
        const prefix = prefixes[key] || prefixes.jannayaks;
        if (prefixEl) {
            prefixEl.textContent = prefix;
        }
        if (previewEl && slugInput) {
            previewEl.textContent = prefix + (slugInput.value.trim() || {{ json_encode((string) ($profile->slug ?? '')) }});
        } else if (previewEl) {
            const slug = {{ json_encode((string) ($profile->slug ?? '')) }};
            previewEl.textContent = prefix + slug;
        }
    };

    document.querySelectorAll('.js-hosting-choice').forEach((input) => {
        input.addEventListener('change', syncHostingPrefix);
    });
    syncHostingPrefix();

    @if($canSelectPersonal && ! $personalSlugLocked)
    const input = document.getElementById('slug');
    const status = document.getElementById('slug-availability');
    const endpoint = @json(route('applications.profile-url.availability', $application));
    let timer = null;

    document.querySelectorAll('.js-slug-choice').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.getAttribute('data-slug') || '';
            input.dispatchEvent(new Event('input'));
        });
    });

    input.addEventListener('input', () => {
        syncHostingPrefix();
        const value = input.value.trim();
        window.clearTimeout(timer);
        if (value === '') {
            status.textContent = '';
            return;
        }
        timer = window.setTimeout(async () => {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('slug', value);
            const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
            if (!response.ok) {
                return;
            }
            const payload = await response.json();
            status.textContent = payload.message || '';
            status.style.color = payload.available ? 'var(--ok)' : 'var(--bad)';
        }, 250);
    });
    @endif
})();
</script>
@endsection
