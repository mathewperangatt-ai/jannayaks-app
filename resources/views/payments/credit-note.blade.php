@extends('layouts.app')

@section('content')
<section class="card stack">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <span class="tag">CREDIT NOTE</span>
            <h1 style="margin-top:8px">{{ $document['document_title'] }}</h1>
            <p class="sub">Refund / credit documentation linked to the original payment documents. Gateway refund execution is not performed in this phase.</p>
        </div>
        <div style="text-align:right">
            <div class="note-safe">Credit Note No.</div>
            <div style="font-weight:700;font-variant-numeric:tabular-nums">{{ $document['document_number'] ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px">
        <div>
            <div class="note-safe">Customer</div>
            <div style="font-weight:600">{{ $document['billing_name'] ?? '—' }}</div>
            <div class="note-safe">{{ $document['billing_email'] ?? '' }}</div>
        </div>
        <div>
            <div class="note-safe">Linked documents</div>
            <div class="note-safe">Receipt: <b>{{ $document['original_receipt_number'] ?? '—' }}</b></div>
            <div class="note-safe">Tax invoice: <b>{{ $document['original_tax_invoice_number'] ?? '—' }}</b></div>
            <div class="note-safe">Refunded: {{ optional($document['refunded_at'])->format('j M Y, H:i') ?? '—' }}</div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="row" style="justify-content:space-between">
        <div>
            <div style="font-weight:600">{{ $document['package_description'] }}</div>
            <div class="note-safe">Credit against original payment · basis {{ $document['refund_percent_basis'] ?? '—' }}% (configurable / provisional)</div>
            @if (!empty($document['refund_note']))
                <div class="note-safe" style="margin-top:6px">Note: {{ $document['refund_note'] }}</div>
            @endif
        </div>
        <div style="font-weight:800;color:var(--brand);font-variant-numeric:tabular-nums">{{ $document['refund_amount_formatted'] }}</div>
    </div>

    <div class="actions" style="margin-top:18px">
        <a class="btn ghost" href="{{ route('applications.payment', ['application' => $payment->application_id]) }}">← Payment page</a>
        @if (!empty($document['original_receipt_number']))
            <a class="btn" href="{{ route('payments.receipt', $payment) }}">Original receipt</a>
        @endif
    </div>
</section>
@endsection
