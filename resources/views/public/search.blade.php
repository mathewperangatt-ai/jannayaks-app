@extends('layouts.public')

@section('title', ($q !== '' ? 'Search: '.$q.' — ' : '').'Search — Jannayaks')
@section('seoDescription', 'Search published Jannayaks profiles by name, profession, field of work, or place.')
@section('seoCanonical', route('search.index'))

@section('content')
<p class="eyebrow">Public search</p>
<h1 style="font-family:var(--serif);font-size:clamp(1.8rem,4vw,2.4rem);line-height:1.15;margin:0 0 10px;font-weight:700">Find people</h1>
<p class="lede">Search published profiles by ordinary words — names, places, fields, and public roles.</p>

<section class="search-panel" aria-label="Search published profiles">
    <form method="get" action="{{ route('search.index') }}" role="search">
        <label for="q">Search</label>
        <div class="search-row">
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Name, profession, field of work, or place" maxlength="200" autocomplete="off" autofocus>
            <button type="submit">Search</button>
        </div>
    </form>
</section>

@if($q !== '')
    <p class="lede" style="margin-top:-8px">Results for “{{ $q }}” — published profiles only.</p>
@endif

@if($profiles->isEmpty())
    <p class="empty">{{ $q !== '' ? 'No published profiles matched that search.' : 'Enter a search to discover published profiles.' }}</p>
@else
    <div class="grid">
        @foreach($profiles as $item)
            @include('public.partials.profile-card', ['item' => $item, 'presentation' => $presentation])
        @endforeach
    </div>
    <div class="pager">{{ $profiles->links() }}</div>
@endif
@endsection
