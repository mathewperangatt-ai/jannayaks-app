{{-- Decorative tier marker: three slanted coloured lines in the top-right
     corner. Tier names are never rendered as text here; the tier stays in
     the underlying application data. --}}
@php
    $tierKey = strtolower((string) ($tier ?? ''));
    $tierColours = [
        'emerging' => ['#5d7f62', '#9eb69f'],
        'accomplished' => ['#2e6078', '#78a4b7'],
        'distinguished' => ['#554f79', '#8d87ad'],
        'in_memoriam' => ['#8b5b3f', '#c49a7b'],
    ];
    $colours = $tierColours[$tierKey] ?? null;
@endphp
@if($colours)
    <span class="tier-mark tier-mark--{{ $tierKey }}" style="--tier-c1:{{ $colours[0] }};--tier-c2:{{ $colours[1] }}" aria-hidden="true"><i></i><i></i><i></i></span>
@endif
