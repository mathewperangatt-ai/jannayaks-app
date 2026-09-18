@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between">
        <div>
            <span class="tag">MEMBERSHIP</span>
        </div>
        <div>
            @if($phase === 'active')
                <span class="tag ok">ACTIVE</span>
            @elseif($phase === 'grace')
                <span class="tag warn">GRACE PERIOD</span>
            @elseif($phase === 'lapsed_retained')
                <span class="tag warn">DEACTIVATED (RETAINED)</span>
            @else
                <span class="tag">RETENTION ENDED</span>
            @endif
        </div>
    </div>

    <h1>{{ $profile->display_name ?: $profile->full_name }}</h1>
    <p class="lead" style="margin:0">
        Annual membership status for your published profile. Membership calendar dates use
        {{ config('jannayaks.membership_lifecycle.business_timezone', 'Asia/Kolkata') }}
        (application timestamps may still use {{ config('app.timezone') }}).
    </p>

    <h2 style="margin-top:10px;font-size:16px">Lifecycle</h2>
    <div class="sub">
        <span class="list-pill">Status: <b>{{ $membership->status }}</b></span>
        <span class="list-pill">Starts: <b>{{ $membership->starts_on?->toDateString() ?? '—' }}</b></span>
        <span class="list-pill">Expires: <b>{{ $membership->ends_on?->toDateString() ?? '—' }}</b></span>
        <span class="list-pill">Grace ends: <b>{{ $graceEndsOn?->toDateString() ?? '—' }}</b></span>
        <span class="list-pill">Retention until: <b>{{ $membership->retention_until?->toDateString() ?? '—' }}</b></span>
        <span class="list-pill">Public now: <b>{{ $isPublic ? 'yes' : 'no' }}</b></span>
    </div>

    @if($phase === 'grace')
        <p class="lead">Your membership has expired, but your profile remains public during the three-day grace period. Renew now to avoid deactivation.</p>
    @elseif($phase === 'lapsed_retained')
        <p class="lead">Your profile has been deactivated after the grace period. Data is retained until {{ $membership->retention_until?->toDateString() ?? '—' }}. Successful renewal will reactivate this same profile.</p>
    @elseif($phase === 'retention_ended')
        <p class="lead">The one-year retention window after expiry has ended. Post-retention policy is not decided in this release.</p>
    @endif

    @if ($errors->any())
        <div class="card" style="border-color:var(--bad)">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if (session('status'))
        <div class="card" style="border-color:var(--ok)">{{ session('status') }}</div>
    @endif

    @if($canRenew)
        <h2 style="margin-top:10px;font-size:16px">Renew</h2>
        <p class="sub">
            Annual hosting / maintenance: {{ $amounts['label'] ?? 'Annual Membership' }} —
            {{ $amounts['amount_incl_formatted'] ?? ('₹'.number_format(((int)($amounts['amount_incl_paise'] ?? 0))/100, 2)) }}
            (includes GST). Opening payment does not renew by itself — settlement is server-side.
        </p>
        <form method="post" action="{{ route('membership.renew', $profile) }}">
            @csrf
            <button type="submit" class="btn primary">Start renewal payment</button>
        </form>
    @endif

    @if($profile->application)
        <div class="row" style="margin-top:12px">
            <a class="btn" href="{{ route('applications.show', $profile->application) }}">Back to application →</a>
        </div>
    @endif
</section>
@endsection
