<x-mail::message>
# Recommendation received — {{ $recommendation->recommended_name }}

**Recommended by:** {{ $recommendation->recommender_name }} ({{ $recommendation->recommender_contact }})

**Person recommended:** {{ $recommendation->recommended_name }}
@if($recommendation->recommended_location)**Location:** {{ $recommendation->recommended_location }}@endif
@if($recommendation->recommended_role)**Role / contribution:** {{ $recommendation->recommended_role }}@endif

**Why appropriate for Jannayaks:**

{{ $recommendation->reason }}

@if($recommendation->supporting_info)
**Supporting information:**

{{ $recommendation->supporting_info }}
@endif

<x-mail::panel>
A recommendation does not guarantee inclusion; Jannayaks editorial selection applies, and inclusion is not an electoral or political endorsement.
</x-mail::panel>
</x-mail::message>
