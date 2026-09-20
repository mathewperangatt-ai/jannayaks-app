@extends('layouts.public')

@section('title', 'Gallery — Jannayaks')

@section('content')
<p class="eyebrow">Public gallery</p>
<h1 style="font-family:var(--serif);font-size:clamp(1.8rem,4vw,2.4rem);line-height:1.15;margin:0 0 10px;font-weight:700">People in public life</h1>
<p class="lede">A digital gallery of people's leaders from all walks of life, documenting their lives and contributions.</p>

<section class="search-panel" aria-label="Find people">
    <form method="get" action="{{ route('search.index') }}" role="search">
        <label for="q">Search by name, place, field, or public role</label>
        <div class="search-row">
            <input id="q" name="q" type="search" value="" placeholder="e.g. Mohanlal, social worker, Kattakkada, Wyoming" maxlength="200" autocomplete="off">
            <button type="submit">Search</button>
        </div>
        <div class="filters" aria-label="Optional filters">
            <label class="visually-hidden" for="district_id">{{ config('jannayaks.geography.current_state_name') }} district</label>
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
