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
            A URL may be reserved, but the public page stays hidden until publication.
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
        <p class="sub" style="margin:0">Path: <code>/p/{{ $profile->slug }}</code></p>
    @else
        <p class="lead" style="margin:0">No canonical URL has been assigned yet.</p>
    @endif

    @if($canSelectPersonal)
        <h2 style="margin-top:14px;font-size:16px">Choose a personal URL</h2>
        <p class="sub" style="margin:0">Use lowercase letters, numbers, and hyphens. Example: <code>mathew-perangatt</code></p>
        <form method="post" action="{{ route('applications.profile-url.update', $application) }}" class="stack" style="margin-top:10px">
            @csrf
            <label for="slug">Personal URL slug</label>
            <div class="row" style="gap:8px;align-items:center">
                <span class="sub">/p/</span>
                <input id="slug" name="slug" type="text" maxlength="64" required
                       value="{{ old('slug', $preferredSlug ?: $profile->slug) }}"
                       pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                       style="flex:1;min-height:42px;padding:8px 12px;border:1px solid var(--line);border-radius:10px">
            </div>
            <button type="submit" class="btn primary">Save personal URL</button>
        </form>
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
                <li><code>/p/{{ $row->old_slug }}</code> → <code>/p/{{ $row->new_slug }}</code></li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
