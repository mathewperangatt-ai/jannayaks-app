@extends('layouts.public')

@section('title', 'Gallery — Jannayaks')
@section('seoDescription', 'Browse published Jannayaks profiles.')
@section('seoCanonical', route('gallery.index'))
@section('schemaJson', app(\App\Services\StructuredDataService::class)->encode(app(\App\Services\StructuredDataService::class)->webPageGraph(route('gallery.index'), 'Gallery — Jannayaks')))

@section('content')
<p class="eyebrow">Public gallery</p>
<h1 lang="ml" style="font-family:var(--serif);font-size:clamp(1.6rem,3.4vw,2.2rem);line-height:1.35;margin:0 0 10px;font-weight:700">വിവിധ മേഖലകളിൽ മികവ് തെളിയിച്ച വ്യക്തികളുടെ ഡിജിറ്റൽ ഗാലറി.</h1>
<p class="lede">Published profiles you can browse and search.</p>

<section class="search-panel" aria-label="Find people">
    <form method="get" action="{{ route('search.index') }}" role="search">
        <label for="q">Search by name, place, field, or public role</label>
        <div class="search-row">
            <input id="q" name="q" type="search" value="" placeholder="Name, profession, field of work, or place" maxlength="200" autocomplete="off">
            <button type="submit">Search</button>
        </div>
    </form>
</section>

@if($profiles->isEmpty())
    <p class="empty">No published profiles to show yet.</p>
@else
    <div class="grid">
        @foreach($profiles as $item)
            @include('public.partials.profile-card', ['item' => $item, 'presentation' => $presentation])
        @endforeach
    </div>
    <div class="pager">{{ $profiles->links() }}</div>
@endif
<style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>
@endsection
