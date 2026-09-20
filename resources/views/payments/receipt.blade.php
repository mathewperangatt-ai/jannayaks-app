@extends('layouts.app')

@section('content')
@php
    $fmt = fn (?int $paise) => $paise !== null ? \App\Support\PricingAmounts::formatMoneyInr($paise) : '—';
@endphp
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag ok">PAYMENT RECEIPT</span>
            <h1 style="margin-top:8px">{{ $document['document_title'] }}</h1>
            <p class="sub">This is a payment receipt. It is distinct from a GST tax invoice.</p>
        </div>
        <div style="text-align:right">
            <div class="note-safe">Document No.</div>
            <div style="font-weight:700;font-variant-numeric:tabular-nums">{{ $document['document_number'] ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
        <div>
            <div class="note-safe">Billed to</div>
            <div style="font-weight:600">{{ $document['billing_name'] ?? '—' }}</div>
            <div class="note-safe">{{ $document['billing_email'] ?? '' }}</div>
            <div class="note-safe">{{ $document['billing_mobile'] ?? '' }}</div>
        </div>
        <div>
            <div class="note-safe">Payment date</div>
            <div style="font-weight:600">{{ optional($document['payment_date'])->format('j M Y, H:i') ?? '—' }}</div>
            <div class="note-safe">Gateway ref: {{ $document['gateway_payment_id'] ?? $document['transaction_reference'] ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="row" style="justify-content:space-between">
        <div>
            <div style="font-weight:600">{{ $document['package_description'] }}</div>
            @if (!empty($document['includes_distinguished_addon']))
                <div class="note-safe">Includes optional Distinguished in-person interview add-on</div>
            @endif
        </div>
        <div style="font-weight:700;font-variant-numeric:tabular-nums">{{ $document['total_amount_formatted'] }}</div>
    </div>

    <div class="stack" style="gap:6px;margin-top:10px">
        <div class="row" style="justify-content:space-between"><span class="note-safe">Taxable value</span><span>{{ $fmt($document['base_amount_paise'] ?? null) }}</span></div>
        <div class="row" style="justify-content:space-between"><span class="note-safe">GST ({{ $document['gst_rate_percent'] ?? '—' }}%)</span><span>{{ $fmt($document['gst_amount_paise'] ?? null) }}</span></div>
        <div class="row" style="justify-content:space-between"><span style="font-weight:700">Total paid</span><span style="font-weight:800;color:var(--brand)">{{ $document['total_amount_formatted'] }}</span></div>
    </div>

    <div class="actions" style="margin-top:18px">
        @if ($payment->application_id)
            <a class="btn ghost" href="{{ route('applications.payment', ['application' => $payment->application_id]) }}">← Payment page</a>
        @elseif ($payment->profile_id)
            <a class="btn ghost" href="{{ route('membership.show', $payment->profile_id) }}">← Membership</a>
        @else
            <a class="btn ghost" href="{{ route('home') }}">← Home</a>
        @endif
        <a class="btn" href="{{ route('payments.tax-invoice', $payment) }}">View GST Tax Invoice</a>
    </div>
</section>
@endsection
