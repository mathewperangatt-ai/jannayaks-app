@php
    /** @var \App\Models\Profile $item */
    $name = $presentation->displayName($item);
    $descriptor = $presentation->conciseDescriptor($item);
    $location = $presentation->publicLocationLabel($item);
    $photo = $presentation->publicProfilePhoto($item);
    $isDemo = str_ends_with(strtolower((string) $item->user?->email), \App\Services\SitemapService::DEMO_EMAIL_DOMAIN);
    $url = app(\App\Services\ProfileUrlService::class)->publicListingUrl(
        $item,
        isset($cardLanguage) ? (string) $cardLanguage : null,
    );
@endphp
<a class="card-link" href="{{ $url }}">
    <div class="card-photo" aria-hidden="{{ $photo ? 'false' : 'true' }}">
        @if($photo)
            <img src="{{ route('profiles.public.photo', [$item, $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$name) }}" width="480" height="360" loading="lazy">
        @else
            <span class="placeholder">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        @endif
        @if($isDemo)
            <span class="demo-badge" style="position:absolute;top:10px;left:10px;z-index:2;background:#214d68;color:#fff;font:700 10px/1 var(--jk-ui,var(--fb,sans-serif));letter-spacing:.14em;text-transform:uppercase;padding:5px 10px;border-radius:4px;">Demo</span>
        @endif
    </div>
    <div class="card-body">
        <h2>{{ $name }}</h2>
        @if($descriptor)
            <p class="card-meta">{{ $descriptor }}</p>
        @endif
        @if($location)
            <p class="card-meta">{{ $location }}</p>
        @endif
    </div>
</a>
