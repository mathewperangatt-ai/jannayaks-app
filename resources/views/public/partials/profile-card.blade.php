@php
    /** @var \App\Models\Profile $item */
    $name = $presentation->displayName($item);
    $descriptor = $presentation->conciseDescriptor($item);
    $activity = filled($item->current_activity) ? (string) $item->current_activity : null;
    $tier = $presentation->tierLabel($item);
    $location = $presentation->locationLabel($item->geography);
    $photo = $presentation->publicProfilePhoto($item);
    $url = route('profiles.public', $item->slug);
@endphp
<a class="card-link" href="{{ $url }}">
    <div class="card-photo" aria-hidden="{{ $photo ? 'false' : 'true' }}">
        @if($photo)
            <img src="{{ route('profiles.public.photo', [$item, $photo]) }}" alt="{{ $photo->alt_text ?: ('Portrait of '.$name) }}" width="480" height="360" loading="lazy">
        @else
            <span class="placeholder">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        @endif
    </div>
    <div class="card-body">
        <h2>{{ $name }}</h2>
        @if($activity)
            <p class="card-meta"><strong>Current activity:</strong> {{ $activity }}</p>
        @elseif($descriptor)
            <p class="card-meta">{{ $descriptor }}</p>
        @endif
        <p class="card-meta" style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{{ $tier }}</p>
        @if($location)
            <p class="card-meta">{{ $location }}</p>
        @endif
    </div>
</a>
