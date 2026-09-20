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
        Tier: <b>{{ ucfirst($tier) }}</b>.
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

    @if($canSelectPersonal)
        <h2 style="margin-top:14px;font-size:16px">Choose your Jannayaks URL</h2>
        <p class="sub" style="margin:0">
            Verified name: <b>{{ $verifiedName }}</b>.
            These suggestions are derived from that name. Arbitrary usernames are not allowed.
        </p>
        @if($personalSlugLocked)
            <p class="lead" style="margin:0">Your personal URL is permanent and cannot be changed from this page.</p>
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
                <label for="slug">Personal URL</label>
                <div class="row" style="gap:8px;align-items:center">
                    <span class="sub">jannayaks.in/</span>
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
            Emerging profiles use a six-character system URL. A personal name-based URL is not available on this tier.
        </p>
    @endif

    @if($isPublished && $canonicalUrl)
        <h2 style="margin-top:14px;font-size:16px">QR code</h2>
        <p class="sub" style="margin:0">Encodes your current public profile URL only.</p>
        <div style="margin-top:10px">
            <img src="{{ route('applications.profile-qr', $application) }}" alt="Profile QR code" width="220" height="220" style="border:1px solid var(--line);border-radius:12px;background:#fff">
        </div>
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
@if($canSelectPersonal && ! $personalSlugLocked)
<script>
(() => {
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
})();
</script>
@endif
@endsection
