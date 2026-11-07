# Jannayaks — Your payment documents

Hello,

Your payment to Jannayaks has been confirmed. The following documents are attached to this email:

@foreach ($attachments as $attachment)
- {{ $attachment['filename'] }}
@endforeach

Receipt number: {{ $payment->invoice_number }}
@if($payment->tax_invoice_number)GST tax invoice: {{ $payment->tax_invoice_number }}@endif

You can also download your documents any time from your Jannayaks dashboard.

For questions, write to founder@jannayaks.in.

Jannayaks™ is used as a trademark of Aurex Network.
