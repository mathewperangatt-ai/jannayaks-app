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
        {{ \App\Support\PricingAmounts::isLivingTier((string) $tier) ? 'Jannayaks Profile' : ucfirst((string) $tier) }}.
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
        <h2 style="margin-top:14px;font-size:16px">Choose your personal URL</h2>
        <p class="sub" style="margin:0">
            Preferred name supplied by the applicant: <b>{{ $verifiedName }}</b>.
            These suggestions are derived from that name. Arbitrary usernames are not allowed.
        </p>
        <p class="sub" style="margin:4px 0 0">
            Once your profile is published, its URL cannot be changed. Please choose carefully.
        </p>
        @if($personalSlugLocked)
            <p class="lead" style="margin:0">Your personal URL is permanent and cannot be changed from this page.</p>
        @else
            @if(count($suggestions))
                <p class="sub" style="margin:10px 0 0">Suggested profile addresses:</p>
                <ul class="stack" style="margin:10px 0 0;padding-left:0;list-style:none">
                    @foreach($suggestions as $suggestion)
                        <li style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">
                            @if($suggestion['available'])
                                <span aria-hidden="true">✓</span>
                                <div style="display:flex;flex-direction:column;gap:2px;flex:1;min-width:200px">
                                    <button type="button" class="btn ghost js-slug-choice" data-slug="{{ $suggestion['slug'] }}" style="padding:6px 10px;min-height:36px">{{ $suggestion['slug'] }}</button>
                                    <span class="sub js-domain-preview" data-slug="{{ $suggestion['slug'] }}" data-domain="jannayaks">https://jannayaks.in/{{ $suggestion['slug'] }}</span>
                                </div>
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
                    <span class="sub" id="hosting-prefix">jannayaks.in/</span>
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

@endsection
