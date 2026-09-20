@extends('layouts.app')

@section('content')
@php
    $fmt = fn (?int $paise) => $paise !== null ? \App\Support\PricingAmounts::formatMoneyInr($paise) : '—';
@endphp
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag warn">GST TAX INVOICE</span>
            <h1 style="margin-top:8px">{{ $document['document_title'] }}</h1>
            <p class="sub">This GST tax invoice is distinct from the payment receipt.</p>
        </div>
        <div style="text-align:right">
            <div class="note-safe">Invoice No.</div>
            <div style="font-weight:700;font-variant-numeric:tabular-nums">{{ $document['document_number'] ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
        <div>
            <div class="note-safe">Supplier (Jannayaks)</div>
            @if (!empty($document['seller_details_configured']))
                <div style="font-weight:600">{{ $document['seller_legal_name'] ?? '—' }}</div>
                <div class="note-safe">GSTIN: {{ $document['seller_gstin'] ?? 'Pending configuration' }}</div>
                <div class="note-safe">{{ $document['seller_address'] ?? '' }}</div>
                <div class="note-safe">{{ $document['seller_state'] ?? '' }}</div>
            @else
                <div class="warnbox" style="margin:0">
                    Supplier GST / legal registration details are not configured yet.
                    Document number and payment amounts are recorded; GSTIN will appear once set in configuration.
                </div>
            @endif
        </div>
        <div>
            <div class="note-safe">Bill to</div>
            <div style="font-weight:600">{{ $document['billing_name'] ?? '—' }}</div>
            <div class="note-safe">{{ $document['billing_email'] ?? '' }}</div>
            <div class="note-safe">{{ $document['billing_mobile'] ?? '' }}</div>
            @if (!empty($document['place_of_supply']))
                <div class="note-safe" style="margin-top:8px">Place of supply: {{ $document['place_of_supply'] }}</div>
            @endif
            <div class="note-safe">Linked receipt: {{ $document['receipt_reference'] ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="row" style="justify-content:space-between">
        <div>
            <div style="font-weight:600">{{ $document['package_description'] }}</div>
            <div class="note-safe">Service / profile package</div>
        </div>
        <div style="font-weight:700;font-variant-numeric:tabular-nums">{{ $document['total_amount_formatted'] }}</div>
    </div>

    <div class="stack" style="gap:6px;margin-top:12px">
        <div class="row" style="justify-content:space-between"><span class="note-safe">Taxable value</span><span>{{ $fmt($document['base_amount_paise'] ?? null) }}</span></div>
        @if (($document['cgst_amount_paise'] ?? null) !== null)
            <div class="row" style="justify-content:space-between"><span class="note-safe">CGST</span><span>{{ $fmt($document['cgst_amount_paise']) }}</span></div>
        @endif
        @if (($document['sgst_amount_paise'] ?? null) !== null)
            <div class="row" style="justify-content:space-between"><span class="note-safe">SGST</span><span>{{ $fmt($document['sgst_amount_paise']) }}</span></div>
        @endif
        @if (($document['igst_amount_paise'] ?? null) !== null)
            <div class="row" style="justify-content:space-between"><span class="note-safe">IGST</span><span>{{ $fmt($document['igst_amount_paise']) }}</span></div>
        @endif
        <div class="row" style="justify-content:space-between"><span style="font-weight:700">Invoice total</span><span style="font-weight:800;color:var(--brand)">{{ $document['total_amount_formatted'] }}</span></div>
    </div>

    <div class="note-safe" style="margin-top:12px">
        Payment date: {{ optional($document['payment_date'])->format('j M Y, H:i') ?? '—' }}
        · Gateway: {{ strtoupper((string) ($document['payment_gateway'] ?? '')) }}
        · Ref: {{ $document['gateway_payment_id'] ?? $document['transaction_reference'] ?? '—' }}
    </div>

    <div class="actions" style="margin-top:18px">
        @if ($payment->application_id)
            <a class="btn ghost" href="{{ route('applications.payment', ['application' => $payment->application_id]) }}">← Payment page</a>
        @elseif ($payment->profile_id)
            <a class="btn ghost" href="{{ route('membership.show', $payment->profile_id) }}">← Membership</a>
        @else
            <a class="btn ghost" href="{{ route('home') }}">← Home</a>
        @endif
        <a class="btn" href="{{ route('payments.receipt', $payment) }}">View Payment Receipt</a>
    </div>
</section>
@endsection
