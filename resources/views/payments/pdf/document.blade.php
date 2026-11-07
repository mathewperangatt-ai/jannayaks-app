<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $document['document_title'] }} — Jannayaks</title>
<style>
    @page{margin:46px 48px;}
    body{font-family:'DejaVu Sans',sans-serif;color:#1f2924;font-size:12px;line-height:1.55;}
    .head{border-bottom:3px solid #214d68;padding-bottom:14px;margin-bottom:18px;}
    .brand{font-family:serif;font-size:22px;letter-spacing:.12em;color:#214d68;margin:0;font-weight:bold;}
    .brand-sub{font-size:10px;color:#6f8075;letter-spacing:.08em;margin-top:2px;}
    .doc-type{float:right;text-align:right;}
    .doc-type .t{font-size:10px;letter-spacing:.14em;color:#6f8075;text-transform:uppercase;}
    .doc-type .n{font-size:15px;font-weight:bold;font-variant-numeric:tabular-nums;color:#214d68;}
    table.meta{width:100%;border-collapse:collapse;margin-top:14px;}
    table.meta td{vertical-align:top;width:50%;padding:4px 0;font-size:11.5px;}
    table.meta .lbl{font-size:9px;letter-spacing:.12em;text-transform:uppercase;color:#6f8075;display:block;}
    table.items{width:100%;border-collapse:collapse;margin-top:20px;}
    table.items th{background:#eaf2e7;color:#214d68;font-size:9.5px;letter-spacing:.1em;text-transform:uppercase;text-align:left;padding:8px 10px;border-bottom:1px solid #d3ddd1;}
    table.items td{padding:8px 10px;border-bottom:1px solid #d3ddd1;font-size:11.5px;vertical-align:top;}
    .totals{margin-top:16px;margin-left:auto;width:52%;border-collapse:collapse;}
    .totals td{padding:5px 8px;font-size:11.5px;}
    .totals td.r{text-align:right;font-variant-numeric:tabular-nums;}
    .totals tr.grand td{border-top:2px solid #214d68;font-weight:bold;font-size:13px;color:#214d68;}
    .footer{margin-top:26px;border-top:1px solid #d3ddd1;padding-top:10px;font-size:9.5px;color:#6f8075;line-height:1.6;}
    .tagline{color:#c77e2e;}
</style>
</head>
<body>
<div class="head">
    <p class="brand">JANNAYAKS</p>
    <p class="brand-sub">A Digital Gallery of Notable People Across All Fields</p>
    <div class="doc-type">
        <span class="t">{{ strtoupper((string) $document['document_title']) }}</span><br>
        <span class="n">{{ $document['document_number'] ?? '—' }}</span>
    </div>
</div>

<table class="meta">
    <tr>
        <td>
            <span class="lbl">Billed to</span>
            {{ $document['billing_name'] ?? '—' }}<br>
            @if(!empty($document['billing_email'])){{ $document['billing_email'] }}<br>@endif
            @if(!empty($document['billing_mobile'])){{ $document['billing_mobile'] }}@endif
        </td>
        <td>
            @if(array_key_exists('seller_legal_name', $document))
                <span class="lbl">Issued by</span>
                {{ $document['seller_legal_name'] ?? '—' }}<br>
                @if(!empty($document['seller_address'])){{ $document['seller_address'] }}<br>@endif
                @if(!empty($document['seller_gstin']))GSTIN: {{ $document['seller_gstin'] }}<br>@endif
                @if(!empty($document['seller_state']))State: {{ $document['seller_state'] }}@endif
            @else
                <span class="lbl">Issued by</span>
                Aurex Network — Jannayaks
            @endif
        </td>
    </tr>
    <tr>
        <td>
            <span class="lbl">Issue date</span>
            {{ optional($document['document_issued_at'])->format('d M Y') ?? '—' }}
        </td>
        <td>
            <span class="lbl">Payment date</span>
            {{ optional($document['payment_date'])->format('d M Y') ?? '—' }}
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr><th>Service description</th><th style="text-align:right">Amount (INR)</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>
                {{ $document['package_description'] }}<br>
                <span style="color:#6f8075;font-size:10px">Payment status: {{ (string) $document['payment_status'] }} · Gateway: {{ (string) $document['payment_gateway'] }}@if(!empty($document['gateway_payment_id'])) · Ref: {{ $document['gateway_payment_id'] }}@endif</span>
            </td>
            <td class="r" style="text-align:right">{{ $document['total_amount_formatted'] }}</td>
        </tr>
        @if(isset($document['refund_amount_paise']) && $document['refund_amount_paise'] !== null)
            <tr>
                <td>Refunded against this document{{ !empty($document['refund_note']) ? ' — '.$document['refund_note'] : '' }}<br>
                    <span style="color:#6f8075;font-size:10px">Original receipt: {{ $document['original_receipt_number'] ?? '—' }} · Original tax invoice: {{ $document['original_tax_invoice_number'] ?? '—' }}</span>
                </td>
                <td class="r" style="text-align:right">{{ $document['refund_amount_formatted'] }}</td>
            </tr>
        @endif
    </tbody>
</table>

<table class="totals">
    <tr><td>Taxable value</td><td class="r">{{ isset($document['base_amount_paise']) && $document['base_amount_paise'] !== null ? \App\Support\PricingAmounts::formatMoneyInr($document['base_amount_paise']) : '—' }}</td></tr>
    @if(isset($document['cgst_amount_paise']) && $document['cgst_amount_paise'] !== null)
        <tr><td>CGST{{ isset($document['gst_rate_percent']) ? ' @ '.$document['gst_rate_percent'].'%' : '' }}</td><td class="r">{{ \App\Support\PricingAmounts::formatMoneyInr($document['cgst_amount_paise']) }}</td></tr>
    @endif
    @if(isset($document['sgst_amount_paise']) && $document['sgst_amount_paise'] !== null)
        <tr><td>SGST</td><td class="r">{{ \App\Support\PricingAmounts::formatMoneyInr($document['sgst_amount_paise']) }}</td></tr>
    @endif
    @if(isset($document['igst_amount_paise']) && $document['igst_amount_paise'] !== null)
        <tr><td>IGST</td><td class="r">{{ \App\Support\PricingAmounts::formatMoneyInr($document['igst_amount_paise']) }}</td></tr>
    @endif
    @if((isset($document['cgst_amount_paise']) && $document['cgst_amount_paise'] === null) && (isset($document['igst_amount_paise']) && $document['igst_amount_paise'] === null))
        <tr><td>GST</td><td class="r">—</td></tr>
    @endif
    <tr class="grand"><td>Total {{ isset($document['refund_amount_paise']) && $document['refund_amount_paise'] !== null ? 'refunded' : 'paid' }}</td><td class="r">{{ isset($document['refund_amount_paise']) && $document['refund_amount_paise'] !== null ? $document['refund_amount_formatted'] : $document['total_amount_formatted'] }}</td></tr>
</table>

<div class="footer">
    Jannayaks™ is used as a trademark of Aurex Network. A trademark application has been filed and is pending.<br>
    This document is generated from a payment confirmed through the payment gateway's server-side verification. It is not an independently verified statement about any profile subject.<br>
    Questions? <span class="tagline">{{ $document['seller_support_email'] ?? 'founder@jannayaks.in' }}</span>
</div>
</body>
</html>
