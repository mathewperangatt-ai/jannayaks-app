@if($galleryEntries->isNotEmpty())
    <div class="jk-gallery jk-gallery--cols-{{ $galleryEntries->count() }}">
        @foreach($galleryEntries as $entry)
            @if($entry['photo'])
                <a class="jk-gallery-slot" data-photo-position="additional-{{ $entry['position'] }}" href="{{ route('profiles.public.photo', [$profile, $entry['photo']]) }}">
                    <img src="{{ route('profiles.public.photo', [$profile, $entry['photo']]) }}" alt="{{ $entry['photo']->alt_text ?: ($displayName.' photograph') }}" loading="lazy">
                </a>
            @else
                <div class="jk-position-copy" data-photo-position="additional-{{ $entry['position'] }}">
                    <p>{{ $entry['copy'] }}</p>
                </div>
            @endif
        @endforeach
    </div>
@endif
