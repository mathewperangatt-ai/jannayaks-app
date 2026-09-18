<x-mail::message>
# [PROVISIONAL COPY — NOT FINAL]

This is a **draft / placeholder** membership lifecycle email for Jannayaks Phase 15.

Final wording will be decided later. Do not treat this text as approved product copy.

@php
    $profile = $membership->profile;
    $endsOn = $membership->ends_on?->toDateString() ?? '—';
    $graceEnds = $membership->ends_on
        ? $membership->ends_on->copy()->addDays((int) config('jannayaks.membership_lifecycle.grace_period_days', 3))->toDateString()
        : '—';
    $retentionUntil = $membership->retention_until?->toDateString() ?? '—';
@endphp

**Profile:** {{ $profile?->display_name ?? $profile?->full_name ?? '—' }}  
**Membership ends on:** {{ $endsOn }}  
**Grace period ends on (inclusive):** {{ $graceEnds }}  
**Retention until:** {{ $retentionUntil }}  
**Reminder type:** {{ $eventType }} (offset {{ $offsetDays }} day(s))

Please renew your membership to keep your public profile active. After the grace period, the profile is deactivated but retained for one year from the expiry date.

@if($profile)
<x-mail::button :url="route('membership.show', $profile)">
Renew membership (provisional CTA)
</x-mail::button>
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
