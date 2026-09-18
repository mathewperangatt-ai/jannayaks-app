@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag">PROFILE MEDIA</span>
            <h1 style="margin-top:8px">Photographs &amp; video</h1>
            <p class="lead" style="margin:0">
                Package: <b>{{ ucfirst($tier) }}</b> —
                {{ $count }} of {{ $limit }} photograph{{ $limit === 1 ? '' : 's' }} used.
            </p>
        </div>
        <a class="btn" href="{{ route('applications.show', $application) }}">← Application</a>
    </div>

    @if (session('status'))
        <p class="lead" style="color:var(--ok);margin:0">{{ session('status') }}</p>
    @endif
    @if (session('error'))
        <p class="lead" style="color:var(--bad);margin:0">{{ session('error') }}</p>
    @endif
    @if ($errors->any())
        <ul style="color:var(--bad);margin:0;padding-left:18px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <h2 style="font-size:16px;margin-top:8px">Upload a photograph</h2>
    <p class="lead" style="margin:0">
        JPEG, PNG, or WebP only. Maximum {{ $maxKb }} KB before optimization.
        Photographs are resized server-side and stay <b>pending review</b> until Jannayaks approves them —
        they do not appear on a published profile until approved.
    </p>

    @if ($count < $limit)
        <form method="post" action="{{ route('applications.media.store', $application) }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label for="photo">Photograph <span class="req">*</span></label>
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
            <label for="alt_text">Alt text (optional)</label>
            <input id="alt_text" name="alt_text" type="text" maxlength="255" value="{{ old('alt_text') }}" placeholder="Short description for accessibility">
            <label class="row" style="gap:8px;align-items:center">
                <input type="checkbox" name="make_primary" value="1" @checked(old('make_primary'))>
                Make this the primary portrait
            </label>
            <button class="btn primary" type="submit">Upload photograph</button>
        </form>
    @else
        <p class="lead" style="margin:0;color:var(--warn)">Photograph limit reached for this package. Remove a photo to upload another.</p>
    @endif

    <h2 style="font-size:16px;margin-top:18px">Your photographs</h2>
    @if ($photos->isEmpty())
        <p class="lead" style="margin:0">No photographs uploaded yet.</p>
    @else
        <ul class="stack" style="list-style:none;padding:0;margin:0">
            @foreach ($photos as $photo)
                <li class="card" style="padding:14px;margin:0">
                    <div class="row" style="justify-content:space-between;align-items:center">
                        <div>
                            <div>
                                <b>{{ $photo->alt_text ?: 'Photograph #'.$photo->id }}</b>
                                @if ($photo->is_primary)
                                    <span class="tag ok" aria-label="Requested primary photograph">PRIMARY REQUEST</span>
                                @endif
                                <span class="tag">{{ str_replace('_', ' ', $photo->review_status) }}</span>
                            </div>
                            <div class="sub">{{ $photo->mime_type }} · {{ number_format((int) $photo->size_bytes) }} bytes
                                @if($photo->width && $photo->height)
                                    · {{ $photo->width }}×{{ $photo->height }}
                                @endif
                            </div>
                        </div>
                        <div class="row">
                            @unless ($photo->is_primary)
                                <form method="post" action="{{ route('applications.media.primary', [$application, $photo]) }}">
                                    @csrf
                                    <button class="btn" type="submit">Set primary</button>
                                </form>
                            @endunless
                            <form method="post" action="{{ route('applications.media.destroy', [$application, $photo]) }}" onsubmit="return confirm('Remove this photograph?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn" type="submit" style="color:var(--bad)">Remove</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($allowsVideo)
        <h2 style="font-size:16px;margin-top:22px">External video link</h2>
        <p class="lead" style="margin:0">
            Jannayaks does not host video. Submit an HTTPS link (for example YouTube or Vimeo).
            After publication, changes require Jannayaks approval and do not replace the live link until approved.
        </p>
        <form method="post" action="{{ route('applications.media.video.store', $application) }}" class="stack">
            @csrf
            <label for="url">Video URL <span class="req">*</span></label>
            <input id="url" name="url" type="url" required maxlength="2048" value="{{ old('url') }}" placeholder="https://">
            <label for="label">Label (optional)</label>
            <input id="label" name="label" type="text" maxlength="255" value="{{ old('label') }}" placeholder="Interview clip">
            <button class="btn primary" type="submit">Submit video link</button>
        </form>

        @if ($videoLinks->isNotEmpty())
            <ul class="stack" style="list-style:none;padding:0;margin:0">
                @foreach ($videoLinks as $link)
                    <li class="card" style="padding:14px;margin:0">
                        <div><b>{{ $link->label ?: 'Video link' }}</b>
                            <span class="tag">{{ str_replace('_', ' ', $link->status) }}</span>
                            @if ($link->is_publicly_active)
                                <span class="tag ok">PUBLIC</span>
                            @endif
                        </div>
                        <div class="sub" style="word-break:break-all">{{ $link->url }}</div>
                        @if ($link->replaces_link_id)
                            <div class="sub">Change request for link #{{ $link->replaces_link_id }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
@endsection
