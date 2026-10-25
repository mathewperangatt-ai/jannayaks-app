@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag">PROFILE MEDIA</span>
            <h1 style="margin-top:8px">Photographs</h1>
            <p class="lead" style="margin:0">
                @if ($count > $limit)
                    {{ $count }} photographs are on file. The public profile shows {{ $limit }}: the primary photograph, then the next photographs in their existing display order. No further photograph can be added.
                @else
                    {{ $count }} of {{ $limit }} photograph{{ $limit === 1 ? '' : 's' }} uploaded; {{ $remaining }} slot{{ $remaining === 1 ? '' : 's' }} available.
                @endif
            </p>
            <p class="sub" style="margin:0">
                @if ($limit > 1)
                    One primary photograph and up to {{ $limit - 1 }} additional photograph{{ ($limit - 1) === 1 ? '' : 's' }}. Additional photographs are optional.
                @else
                    One primary photograph.
                @endif
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

    <h2 style="font-size:16px;margin-top:8px">Photograph slots</h2>
    <p class="lead" style="margin:0">
        JPEG, PNG, or WebP only. Maximum {{ $maxKb }} KB before optimization.
        Photographs are resized server-side and stay <b>pending review</b> until Jannayaks approves them —
        they do not appear on a published profile until approved.
    </p>

    @if ($remaining > 1)
        <form method="post" action="{{ route('applications.media.store', $application) }}" enctype="multipart/form-data" class="stack">
            @csrf
            <label for="photos">Add several photographs at once</label>
            <input id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple>
            <p class="sub" style="margin:0">Up to {{ $remaining }} photographs can be added. Extra files are refused.</p>
            <button class="btn primary" type="submit">Upload selected photographs</button>
        </form>
    @elseif ($remaining === 0)
        <p class="lead" style="margin:0;color:var(--warn)">Photograph limit reached. Remove a photo to upload another.</p>
    @endif

    <div class="photo-slots">
        @foreach ($slots as $index => $photo)
            <article class="photo-slot">
                <h3>{{ $index === 0 ? 'Primary photograph' : 'Additional photograph '.$index }}</h3>
                @if ($photo === null)
                    <div class="photo-placeholder" aria-hidden="true">Empty slot</div>
                    <p class="sub">{{ $index === 0 ? 'Upload the primary photograph.' : 'Optional. Upload an additional photograph.' }}</p>
                    <form method="post" action="{{ route('applications.media.store', $application) }}" enctype="multipart/form-data" class="stack">
                        @csrf
                        <label for="photo-{{ $index }}">Upload photograph</label>
                        <input id="photo-{{ $index }}" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                        @if ($index === 0)
                            <label for="alt_text">Alt text (optional)</label>
                            <input id="alt_text" name="alt_text" type="text" maxlength="255" value="{{ old('alt_text') }}" placeholder="Short description for accessibility">
                            <label class="row" style="gap:8px;align-items:center">
                                <input type="checkbox" name="make_primary" value="1" @checked(old('make_primary', true))>
                                Request as primary photograph
                            </label>
                        @endif
                        <button class="btn primary" type="submit">Upload photograph</button>
                    </form>
                @else
                    <img src="{{ route('applications.media.preview', [$application, $photo]) }}" alt="{{ $photo->alt_text ?: 'Uploaded photograph '.$photo->id }}">
                    <p>
                        @if ($photo->is_primary)
                            <span class="tag ok">Primary photograph</span>
                        @endif
                        @if ($photo->review_status === \App\Models\MediaItem::REVIEW_APPROVED)
                            <span class="tag ok">Approved</span>
                        @elseif ($photo->review_status === \App\Models\MediaItem::REVIEW_REJECTED)
                            <span class="tag">Rejected</span>
                        @else
                            <span class="tag">Pending review</span>
                        @endif
                    </p>
                    <div class="row">
                        @if ($photo->review_status !== \App\Models\MediaItem::REVIEW_REJECTED && ! $photo->is_primary)
                            <form method="post" action="{{ route('applications.media.primary', [$application, $photo]) }}">
                                @csrf
                                <button class="btn" type="submit">Set primary</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('applications.media.destroy', [$application, $photo]) }}" onsubmit="return confirm('Remove this photograph?');">
                            @csrf
                            @method('DELETE')
                            <button class="btn" type="submit" style="color:var(--bad)">{{ $photo->review_status === \App\Models\MediaItem::REVIEW_REJECTED ? 'Remove and replace' : 'Remove' }}</button>
                        </form>
                    </div>
                    @if ($photo->review_status === \App\Models\MediaItem::REVIEW_REJECTED)
                        <form method="post" action="{{ route('applications.media.store', $application) }}" enctype="multipart/form-data" class="stack">
                            @csrf
                            <label for="replace-{{ $photo->id }}">Upload a replacement</label>
                            <input id="replace-{{ $photo->id }}" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                            <button class="btn" type="submit">Upload photograph</button>
                        </form>
                    @endif
                @endif
            </article>
        @endforeach
    </div>

    @if ($extraRejected->isNotEmpty())
        <h2 style="font-size:16px">Earlier rejected photographs</h2>
        <ul class="stack" style="list-style:none;padding:0;margin:0">
            @foreach ($extraRejected as $photo)
                <li class="card" style="padding:14px;margin:0">
                    <span class="tag">Rejected</span>
                    <form method="post" action="{{ route('applications.media.destroy', [$application, $photo]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn" type="submit">Remove and replace</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <style>
        .photo-slots{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:8px}
        .photo-slot{border:1px solid #d3ddd1;background:#f7faf5;padding:14px;min-width:0}
        .photo-slot h3{margin:0 0 10px;font-size:15px}
        .photo-slot img{width:100%;height:auto;max-height:240px;object-fit:contain;background:#eaf2e7;display:block}
        .photo-placeholder{min-height:140px;display:flex;align-items:center;justify-content:center;border:1px dashed #b8c9b5;background:#eaf2e7;color:#6f8075;margin-bottom:10px}
        @media(max-width:800px){.photo-slots{grid-template-columns:1fr}}
    </style>

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
