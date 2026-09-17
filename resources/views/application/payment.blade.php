@extends('layouts.app')

@section('content')
@php
    $tier = $application->package_tier;
    $tierBadgeColor = match ($tier) {
        'emerging'      => 'tag',
        'accomplished'  => 'tag warn',
        'distinguished' => 'tag ok',
        default         => 'tag',
    };
    $statusLabelMap = [
        'pending'           => ['en' => 'Pending',        'ml' => 'പെൻഡിംഗ്',        'cls' => 'tag'],
        'initiated'         => ['en' => 'Initiated',      'ml' => 'ആരംഭിച്ചു',       'cls' => 'tag warn'],
        'paid'              => ['en' => 'Paid',             'ml' => 'പേയ്‌മെൻ്റ് സാധിച്ചു', 'cls' => 'tag ok'],
        'captured'          => ['en' => 'Paid',             'ml' => 'പേയ്‌മെൻ്റ് സാധിച്ചു', 'cls' => 'tag ok'],
        'success'           => ['en' => 'Paid',             'ml' => 'പേയ്‌മെൻ്റ് സാധിച്ചു', 'cls' => 'tag ok'],
        'failed'            => ['en' => 'Failed',           'ml' => 'പരാജയപ്പെട്ടു',     'cls' => 'tag bad'],
        'cancelled'         => ['en' => 'Cancelled',      'ml' => 'റദ്ദാക്കി',        'cls' => 'tag bad'],
        'expired'           => ['en' => 'Expired',          'ml' => 'കാലഹരണപ്പെട്ടു',   'cls' => 'tag bad'],
        'refunded'          => ['en' => 'Refunded',       'ml' => 'റീഫണ്ട് നൽകി',     'cls' => 'tag'],
        'partially_refunded'=> ['en' => 'Partially Refunded', 'ml' => 'ഭാഗിക റീഫണ്ട്',    'cls' => 'tag warn'],
    ];
@endphp

<div class="card">
    <div class="row" style="margin-bottom:14px">
        <div class="bilingual" style="flex:1">
            <h1 style="margin:0 0 2px 0;font-size:22px">Payment &amp; Billing — Application #{{ $application->id }}</h1>
            <div class="sub" style="margin:0">പേയ്‌മെൻ്റ് &amp; ബില്ലിംഗ് — അപ്ലിക്കേഷൻ #{{ $application->id }}</div>
        </div>
        <span class="{{ $tierBadgeColor }}">{{ $package['label'] ?? strtoupper($tier) }}</span>
    </div>

    @if (session('payment_callback_message_en'))
        <div class="warnbox" role="status">
            <div><b>{{ session('payment_callback_message_en') }}</div>
            @if (session('payment_callback_message_ml'))
                <div style="margin-top:6px;color:#7a5100;font-size:13px">{{ session('payment_callback_message_ml') }}</div>
            @endif
        </div>
    @endif

    @if ($errors->any())
        <div class="missbox" role="alert">
        @foreach ($errors->all() as $err)
            <div>{{ $err }}</div>
        @endforeach
    </div>
    @endif
</div>

@if ($package)
<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">Package Price Breakdown / ചെലവ് വിഭജനം</h2>
    <div class="sub" style="margin-bottom:14px">
        GST-inclusive package pricing / ജിഎസ്ടി ഉൾപ്പെട്ട പാക്കേജ് വില</div>

    <div class="stack" style="gap:10px">
        <div class="row" style="justify-content:space-between">
            <div>
                <div class="bilingual">
                    <span style="font-weight:600">{{ ($package['package']['label'] ?? $package['label']) ?? 'Package' }} / പാക്കേജ്</span>
                </div>
                <span class="note-safe">{{ $package['description'] ?? '' }}</span>
            </div>
            <div style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">{{ ($package['package']['amount_incl_formatted'] ?? null) ?: ($package['amount_incl_formatted'] ?? '') }}</div>
        </div>

        @if (!empty($package['addon']))
        <div class="row" style="justify-content:space-between">
            <div>
                <div style="font-weight:600">{{ $package['addon']['label'] }}</div>
                <span class="note-safe">Optional add-on (not part of base Distinguished package)</span>
            </div>
            <div style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">{{ $package['addon']['amount_incl_formatted'] }}</div>
        </div>
        @endif

        @if (is_string($package['cgst_formatted'] ?? null))
        <div class="row" style="justify-content:space-between">
            <div class="note-safe">CGST (included)</div>
            <div style="text-align:right;font-variant-numeric:tabular-nums">{{ $package['cgst_formatted'] }}</div>
        </div>
        @endif

        @if (is_string($package['sgst_formatted'] ?? null))
        <div class="row" style="justify-content:space-between">
            <div class="note-safe">SGST (included)</div>
            <div style="text-align:right;font-variant-numeric:tabular-nums">{{ $package['sgst_formatted'] }}</div>
        </div>
        @endif

        <div class="divider"></div>

        <div class="row" style="justify-content:space-between">
            <div>
            <div style="font-weight:700">Total (incl. GST) / ആകെ (ജിഎസ്ടി ഉൾപ്പെടെ)</div>
            <div class="note-safe">{{ $package['gst_rate_percent'] }}% GST included / ജിഎസ്ടി ഉൾപ്പെടുത്തി</div>
        </div>
            <div style="font-size:22px;font-weight:800;color:var(--brand);font-variant-numeric:tabular-nums">{{ $package['amount_incl_formatted'] ?? '' }}</div>
        </div>
    </div>

    @if ($application->package_tier === 'distinguished' && ! $isSettled)
        @php
            $addonCfg = config('jannayaks.tier_pricing.addons.distinguished_in_person_interview', []);
            $addonAmt = number_format((int) ($addonCfg['base_amount'] ?? 10000));
        @endphp
        <div class="divider"></div>
        <label class="row" style="gap:10px;align-items:flex-start;cursor:pointer">
            <input form="pay-initiate-form" type="hidden" name="distinguished_interview_addon" value="0">
            <input form="pay-initiate-form" type="checkbox" name="distinguished_interview_addon" value="1" @checked($application->distinguished_interview_addon) style="margin-top:4px">
            <span>
                <span style="font-weight:700">Include optional in-person interview (+₹{{ $addonAmt }})</span>
                <span class="note-safe" style="display:block;margin-top:4px">Applied when you click Pay Now. Changing this replaces any pending payment link.</span>
            </span>
        </label>
    @endif
</div>
@endif

<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">Payment Status / പേയ്‌മെൻ്റ് സ്റ്റാറ്റസ്</h2>
    <div class="sub" style="margin-bottom:14px">Track your current payment state.</div>

    @php
        $statusRow = null;
        $settlementStatus = 'not_started';
        if ($settledPayment) {
            $statusRow = $statusLabelMap[$settledPayment->status] ?? null;
            $settlementStatus = 'settled';
        } elseif ($activePayment) {
            $statusRow = $statusLabelMap[$activePayment->status] ?? null;
            $settlementStatus = 'active';
        }
    @endphp

    <div class="row" style="margin-bottom:16px">
        @if ($statusRow)
            <span class="{{ $statusRow['cls'] }}">{{ $statusRow['en'] }} / {{ $statusRow['ml'] }}</span>
        @else
            <span class="tag">Not started / ആരംഭിച്ചിട്ടില്ല</span>
        @endif
        <div style="flex:1"></div>
        @if ($application->payment_status)
            <span class="note-safe">App payment_status: <b>{{ $application->payment_status }}</b></span>
        @endif
    </div>

    @if ($settledPayment)
        <div class="warnbox" style="border-left-color:#2e8b57;background:#f1faf4">
            <div style="color:#0d4721"><b>Payment received successfully. Next step: editorial review.</b></div>
            <div style="color:#0d4721;font-size:13px;margin-top:4px">പേയ്‌മെൻ്റ് വിജയകരമായി സ്വീകരിച്ചു. അടുത്ത ഘട്ടം: എഡിറ്റോറിയൽ അവലോകനം.</div>
            @if ($settledPayment->invoice_number)
                <div style="margin-top:8px;color:#0d4721;font-size:13px">
                    Receipt / റിസീറ്റ്: <b style="font-variant-numeric:tabular-nums">{{ $settledPayment->invoice_number }}</b>
                </div>
            @endif
            @if ($settledPayment->tax_invoice_number)
                <div style="margin-top:2px;color:#0d4721;font-size:13px">
                    GST Tax Invoice / ജിഎസ്ടി ടാക്സ് ഇൻവോയ്സ്: <b style="font-variant-numeric:tabular-nums">{{ $settledPayment->tax_invoice_number }}</b>
                </div>
            @endif
            @if ($settledPayment->paid_at)
                <div style="margin-top:2px;color:#0d4721;font-size:13px">
                    Paid on / തീയതി: <b>{{ $settledPayment->paid_at->format('j M Y, H:i') }}</b>
                </div>
            @endif
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('payments.receipt', $settledPayment) }}">Payment Receipt</a>
            <a class="btn" href="{{ route('payments.tax-invoice', $settledPayment) }}">GST Tax Invoice</a>
            @if ($settledPayment->isRefunded() && $settledPayment->credit_note_number)
                <a class="btn" href="{{ route('payments.credit-note', $settledPayment) }}">Credit Note</a>
            @endif
            <a class="btn block" href="{{ route('applications.show', ['application' => $application->id]) }}">
                Back to Dashboard / ഡാഷ്‌ബോർഡിലേക്ക് തിരികെ പോകുക
            </a>
        </div>
    @elseif ($activePayment && $activePayment->razorpay_link_url)
        <div class="warnbox">
            <div><b>Payment in progress.</b></div>
            <div style="margin-top:4px;font-size:13px;color:#7a5100">Complete the transaction using the link below. If you already paid, verification is in progress.</div>
        </div>
        <div class="actions">
            <a class="btn primary block" target="_blank" rel="noopener" href="{{ $activePayment->razorpay_link_url }}">
                Continue Payment / പേയ്‌മെൻ്റ് തുടരുക
            </a>
            <form id="pay-initiate-form" method="POST" action="{{ route('applications.payment.initiate', ['application' => $application->id]) }}" style="flex:1 1 260px;margin:0">
                @csrf
                <button class="btn block" type="submit">Retry / New Link / വീണ്ടും ശ്രമിക്കുക / പുതിയ ലിങ്ക്</button>
            </form>
        </div>
    @else
        @if ($activePayment && !$activePayment->razorpay_link_url)
            <div class="warnbox">
                <div><b>Payment was initiated but the gateway link is not ready yet.</b></div>
                <div style="font-size:13px;color:#7a5100;margin-top:4px">പേയ്‌മെൻ്റ് ആരംഭിച്ചെങ്കിലും ഗേറ്റ്വേ ലിങ്ക് ഇതുവരെ തയ്യാറായിട്ടില്ല. പുതിയ ലിങ്കിനായി ശ്രമിക്കുക.</div>
            </div>
        @else
            <div class="sub">Complete payment to proceed to editorial review.</div>
            <div class="note-safe" style="margin-top:4px">എഡിറ്റോറിയൽ അവലോകനത്തിന് പോയാൻ പേയ്‌മെൻ്റ് പൂർത്തിയാക്കുക.</div>
        @endif
        <div class="actions" style="margin-top:16px">
            <form id="pay-initiate-form" method="POST" action="{{ route('applications.payment.initiate', ['application' => $application->id]) }}" style="flex:1 1 100%;margin:0">
                @csrf
                <button class="btn primary block" type="submit">
                    @if ($activePayment)
                        Retry Payment / പേയ്‌മെൻ്റ് വീണ്ടും ആരംഭിക്കുക
                    @else
                        Pay Now / ഇപ്പോൾ പേയ്‌മെൻ്റ് ചെയ്യുക
                    @endif
                </button>
            </form>
        </div>
    @endif
</div>

@if ($payments->count() > 0)
<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">Payment History / പേയ്‌മെൻ്റ് ചരിത്രം</h2>
    <div class="sub" style="margin-bottom:12px">Recent payment activity / സമീപകാല പേയ്‌മെൻ്റ് പ്രവർത്തനങ്ങൾ.</div>

    <div class="stack">
        @foreach ($payments as $p)
            @php
                $row = $statusLabelMap[$p->status] ?? ['en' => ucfirst($p->status), 'ml' => '', 'cls' => 'tag'];
            @endphp
            <div class="mat-row">
                <div class="mat-meta">
                    <div class="row" style="gap:8px">
                        <span class="{{ $row['cls'] }}">{{ $row['en'] }}</span>
                        <span class="note-safe">{{ $p->created_at->format('j M Y, H:i') }}</span>
                        @if ($p->invoice_number)
                            <span class="list-pill">{{ $p->invoice_number }}</span>
                        @endif
                    </div>
                    <div class="row" style="gap:12px;margin-top:2px">
                        <span class="note-safe">Amount: <b>{{ $p->currency }} {{ number_format((float)$p->amount, 2, '.', ',') }}</b></span>
                        <span class="note-safe">Gateway: {{ strtoupper($p->gateway ?? '') }}</span>
                    </div>
                    @if ($p->error_message)
                        <span class="note-safe" style="margin-top:2px;color:var(--bad)">Note: {{ $p->error_message }}</span>
                    @endif
                </div>
                @if ($p->razorpay_link_url && $p->isActiveAttempt())
                    <a class="btn" style="padding:8px 12px;font-size:13px;min-height:36px" target="_blank" rel="noopener" href="{{ $p->razorpay_link_url }}">Open / തുറക്കുക</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">Application Summary / അപ്ലിക്കേഷൻ സംഗ്രഹം</h2>
    <div class="grid" style="grid-template-columns:1fr 1fr">
        <div>
            <div class="note-safe">Name / പേര്</div>
            <div style="font-weight:600">{{ $application->full_name ?? '—' }}</div>
        </div>
        <div>
            <div class="note-safe">Source Method / സോൾസ് മെത്തഡ്</div>
            <div style="font-weight:600">{{ str_replace('_', ' ', \Illuminate\Support\Str::title($application->source_method)) }}</div>
        </div>
        <div>
            <div class="note-safe">Email / ഇമെയിൽ</div>
            <div style="font-weight:600">{{ $application->preferred_contact_email ?? '—' }}</div>
        </div>
        <div>
            <div class="note-safe">Mobile / മൊബൈൽ</div>
            <div style="font-weight:600">{{ $application->preferred_contact_mobile ?? '—' }}</div>
        </div>
    </div>
    <div class="actions" style="margin-top:18px">
        <a class="btn ghost" href="{{ route('applications.show', ['application' => $application->id]) }}">Dashboard / ഡാഷ്‌ബോർഡ്</a>
        @php
            $uploadsUnlocked = app(\App\Services\ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application);
        @endphp
        @if($uploadsUnlocked)
            <a class="btn" href="{{ route('applications.upload.show', ['application' => $application->id]) }}">Uploads / അപ്‌ലോഡുകൾ</a>
            @if($application->source_method === 'online_interview')
                <a class="btn" href="{{ route('online-interview.show', ['application' => $application->id]) }}">Online Interview / ഓൺലൈൻ അഭിമുഖം</a>
            @endif
        @else
            <span class="btn" aria-disabled="true" style="opacity:.55;cursor:not-allowed">Uploads unlock after payment</span>
        @endif
    </div>
</div>
@endsection
