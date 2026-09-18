@php
    /** @var \App\Models\Profile $item */
    $name = $presentation->displayName($item);
    $descriptor = $presentation->conciseDescriptor($item);
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
        @if($descriptor)
            <p class="card-meta">{{ $descriptor }}</p>
        @endif
        @if($location)
            <p class="card-meta">{{ $location }}</p>
        @endif
    </div>
</a>
