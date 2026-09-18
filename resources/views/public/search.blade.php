@extends('layouts.public')

@section('title', ($q !== '' ? 'Search: '.$q.' — ' : '').'Search — Jannayaks')

@section('content')
<p class="eyebrow">Public search</p>
<h1 style="font-family:var(--serif);font-size:clamp(1.8rem,4vw,2.4rem);line-height:1.15;margin:0 0 10px;font-weight:700">Find people</h1>
<p class="lede">Search published profiles by ordinary words — names, places, fields, and public roles.</p>

<section class="search-panel" aria-label="Search published profiles">
    <form method="get" action="{{ route('search.index') }}" role="search">
        <label for="q">Search</label>
        <div class="search-row">
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="e.g. doctor Kerala, artist Trivandrum, Wyoming" maxlength="200" autocomplete="off" autofocus>
            <button type="submit">Search</button>
        </div>
        <div class="filters" aria-label="Optional filters">
            <label class="visually-hidden" for="district_id">Kerala district</label>
            <select id="district_id" name="district_id">
                <option value="">All districts</option>
                @foreach($districts as $district)
                    <option value="{{ $district['id'] }}" @selected((int)($filters['district_id'] ?? 0) === (int)$district['id'])>{{ $district['name'] }}</option>
                @endforeach
            </select>
            <label class="visually-hidden" for="state">State / UT</label>
            <select id="state" name="state">
                <option value="">All states</option>
                @foreach($states as $state)
                    <option value="{{ $state['name'] }}" @selected(($filters['state'] ?? '') === $state['name'])>{{ $state['name'] }}</option>
                @endforeach
            </select>
            <label class="visually-hidden" for="country">Country</label>
            <input id="country" name="country" type="text" value="{{ $filters['country'] ?? '' }}" placeholder="Country (optional)" maxlength="64" style="min-height:40px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:14px;min-width:140px">
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
<style>.visually-hidden{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}</style>
@endsection
