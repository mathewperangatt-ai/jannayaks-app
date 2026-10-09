@extends('layouts.app')

@section('content')
@php
    $ml = ($language ?? 'ml') === 'ml';
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
    $lang = $ml ? 'ml' : 'en';
    $sourceMethodLabels = [
        'online_interview'  => ['en' => 'Online Interview',  'ml' => 'ഓൺലൈൻ അഭിമുഖം'],
        'direct_submission' => ['en' => 'Direct Submission', 'ml' => 'നേരിട്ടുള്ള സമർപ്പണം'],
    ];
@endphp

<div class="card">
    <div class="row" style="margin-bottom:14px">
        <div class="bilingual" style="flex:1">
            <h1 style="margin:0 0 2px 0;font-size:22px">{{ $ml ? 'പേയ്‌മെൻ്റ് & ബില്ലിംഗ് — അപ്ലിക്കേഷൻ' : 'Payment & Billing — Application' }} #{{ $application->id }}</h1>
        </div>
        <span role="navigation" aria-label="Language">
            @if($ml)
                <a class="list-pill" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" lang="en">English</a>
                <span class="list-pill" aria-current="true" style="color:var(--ink)">മലയാളം</span>
            @else
                <span class="list-pill" aria-current="true" style="color:var(--ink)">English</span>
                <a class="list-pill" style="text-decoration:none" href="{{ request()->fullUrlWithQuery(['lang' => 'ml']) }}" lang="ml">മലയാളം</a>
            @endif
        </span>
        <span class="{{ $tierBadgeColor }}">{{ $package['label'] ?? strtoupper($tier) }}</span>
    </div>

    @if (session('payment_callback_message_en'))
        <div class="warnbox" role="status">
            <div><b>{{ $ml && session('payment_callback_message_ml') ? session('payment_callback_message_ml') : session('payment_callback_message_en') }}</b></div>
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
    <h2 style="margin-top:0;margin-bottom:6px">{{ $ml ? 'ചെലവ് വിഭജനം' : 'Package Price Breakdown' }}</h2>
    <div class="sub" style="margin-bottom:14px">
        {{ $ml ? 'അടിസ്ഥാന നിരക്ക് + ജിഎസ്ടി (പണമടയ്ക്കുമ്പോൾ ചേർക്കും)' : 'Base price + GST (added at payment)' }}</div>

    <div class="stack" style="gap:10px">
        <div class="row" style="justify-content:space-between">
            <div>
                <div class="bilingual">
                    <span style="font-weight:600">{{ ($package['package']['label'] ?? $package['label']) ?? ($ml ? 'പാക്കേജ്' : 'Package') }}</span>
                </div>
                <span class="note-safe">{{ $package['description'] ?? '' }}</span>
            </div>
            <div style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">{{ ($package['package']['amount_incl_formatted'] ?? null) ?: ($package['amount_incl_formatted'] ?? '') }}</div>
        </div>

        @if (!empty($package['addon']))
        <div class="row" style="justify-content:space-between">
            <div>
                <div style="font-weight:600">{{ $package['addon']['label'] }}</div>
                <span class="note-safe">{{ $ml ? 'ഓപ്ഷണൽ അധിക സേവനം (അടിസ്ഥാന Distinguished പാക്കേജിന്റെ ഭാഗമല്ല)' : 'Optional add-on (not part of base Distinguished package)' }}</span>
            </div>
            <div style="text-align:right;font-variant-numeric:tabular-nums;font-weight:600">{{ $package['addon']['amount_incl_formatted'] }}</div>
        </div>
        @endif

        @if (is_string($package['cgst_formatted'] ?? null))
        <div class="row" style="justify-content:space-between">
            <div class="note-safe">{{ $ml ? 'സിജിഎസ്ടി (ചേർത്തത്)' : 'CGST (added)' }}</div>
            <div style="text-align:right;font-variant-numeric:tabular-nums">{{ $package['cgst_formatted'] }}</div>
        </div>
        @endif

        @if (is_string($package['sgst_formatted'] ?? null))
        <div class="row" style="justify-content:space-between">
            <div class="note-safe">{{ $ml ? 'എസ്ജിഎസ്ടി (ചേർത്തത്)' : 'SGST (added)' }}</div>
            <div style="text-align:right;font-variant-numeric:tabular-nums">{{ $package['sgst_formatted'] }}</div>
        </div>
        @endif

        <div class="divider"></div>

        <div class="row" style="justify-content:space-between">
            <div>
            <div style="font-weight:700">{{ $ml ? 'ആകെ (ജിഎസ്ടി ഉൾപ്പെടെ)' : 'Total (incl. GST)' }}</div>
            <div class="note-safe">{{ $ml ? $package['gst_rate_percent'].'% ജിഎസ്ടി പണമടയ്ക്കുമ്പോൾ ചേർക്കും' : $package['gst_rate_percent'].'% GST added at payment' }}</div>
        </div>
            <div style="font-size:22px;font-weight:800;color:var(--brand);font-variant-numeric:tabular-nums">{{ $package['amount_incl_formatted'] ?? '' }}</div>
        </div>
    </div>
</div>
@endif

<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">{{ $ml ? 'പേയ്‌മെൻ്റ് സ്റ്റാറ്റസ്' : 'Payment Status' }}</h2>
    <div class="sub" style="margin-bottom:14px">{{ $ml ? 'നിങ്ങളുടെ നിലവിലെ പേയ്‌മെൻ്റ് സ്ഥിതി ഇവിടെ കാണാം.' : 'Track your current payment state.' }}</div>

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
            <span class="{{ $statusRow['cls'] }}">{{ $statusRow[$lang] }}</span>
        @else
            <span class="tag">{{ $ml ? 'ആരംഭിച്ചിട്ടില്ല' : 'Not started' }}</span>
        @endif
        <div style="flex:1"></div>
        @if ($application->payment_status)
            <span class="note-safe">{{ $ml ? 'പേയ്‌മെൻ്റ് നില:' : 'App payment_status:' }} <b>{{ $application->payment_status }}</b></span>
        @endif
    </div>

    @if ($settledPayment)
        <div class="warnbox" style="border-left-color:#2e8b57;background:#f1faf4">
            <div style="color:#0d4721"><b>{{ $ml ? 'പേയ്‌മെൻ്റ് വിജയകരമായി സ്വീകരിച്ചു. അടുത്ത ഘട്ടം: എഡിറ്റോറിയൽ അവലോകനം.' : 'Payment received successfully. Next step: editorial review.' }}</b></div>
            @if ($settledPayment->invoice_number)
                <div style="margin-top:8px;color:#0d4721;font-size:13px">
                    {{ $ml ? 'റിസീറ്റ്' : 'Receipt' }}: <b style="font-variant-numeric:tabular-nums">{{ $settledPayment->invoice_number }}</b>
                </div>
            @endif
            @if ($settledPayment->tax_invoice_number)
                <div style="margin-top:2px;color:#0d4721;font-size:13px">
                    {{ $ml ? 'ജിഎസ്ടി ടാക്സ് ഇൻവോയ്സ്' : 'GST Tax Invoice' }}: <b style="font-variant-numeric:tabular-nums">{{ $settledPayment->tax_invoice_number }}</b>
                </div>
            @endif
            @if ($settledPayment->paid_at)
                <div style="margin-top:2px;color:#0d4721;font-size:13px">
                    {{ $ml ? 'പണമടച്ച തീയതി' : 'Paid on' }}: <b>{{ $settledPayment->paid_at->format('j M Y, H:i') }}</b>
                </div>
            @endif
        </div>
        <div class="actions">
            <a class="btn" href="{{ route('payments.receipt', $settledPayment) }}">{{ $ml ? 'പേയ്‌മെൻ്റ് റിസീറ്റ്' : 'Payment Receipt' }}</a>
            <a class="btn" href="{{ route('payments.tax-invoice', $settledPayment) }}">{{ $ml ? 'ജിഎസ്ടി ടാക്സ് ഇൻവോയ്സ്' : 'GST Tax Invoice' }}</a>
            @if ($settledPayment->isRefunded() && $settledPayment->credit_note_number)
                <a class="btn" href="{{ route('payments.credit-note', $settledPayment) }}">{{ $ml ? 'ക്രെഡിറ്റ് നോട്ട്' : 'Credit Note' }}</a>
            @endif
            <a class="btn block" href="{{ route('applications.show', ['application' => $application->id]) }}">
                {{ $ml ? 'ഡാഷ്‌ബോർഡിലേക്ക് തിരികെ പോകുക' : 'Back to Dashboard' }}
            </a>
        </div>
    @elseif ($activePayment && $activePayment->razorpay_link_url)
        <div class="warnbox">
            <div><b>{{ $ml ? 'പേയ്‌മെൻ്റ് പുരോഗമിക്കുന്നു.' : 'Payment in progress.' }}</b></div>
            <div style="margin-top:4px;font-size:13px;color:#7a5100">{{ $ml ? 'താഴെയുള്ള ലിങ്ക് ഉപയോഗിച്ച് ഇടപാട് പൂർത്തിയാക്കുക. നിങ്ങൾ ഇതിനകം പണമടച്ചിട്ടുണ്ടെങ്കിൽ, സ്ഥിരീകരണം പുരോഗമിക്കുകയാണ്.' : 'Complete the transaction using the link below. If you already paid, verification is in progress.' }}</div>
        </div>
        <div class="actions">
            {{-- Razorpay launch: live payment temporarily unavailable. --}}
            <button class="btn primary block" type="button" disabled aria-disabled="true">
                {{ $ml ? 'പേയ്‌മെൻ്റ് തുടരുക' : 'Continue Payment' }}
            </button>
            <form id="pay-initiate-form" method="POST" action="{{ route('applications.payment.initiate', ['application' => $application->id]) }}" style="flex:1 1 260px;margin:0">
                @csrf
                <button class="btn block" type="submit" disabled aria-disabled="true">{{ $ml ? 'വീണ്ടും ശ്രമിക്കുക / പുതിയ ലിങ്ക്' : 'Retry / New Link' }}</button>
            </form>
            <div class="sub" style="flex:1 1 100%">
                @if (!empty($paymentsTestingMode))
                    {{ $ml ? 'ടെസ്റ്റിംഗ് കാലത്ത് ഓൺലൈൻ പേയ്‌മെൻ്റ് താൽക്കാലികമായി ലഭ്യമല്ല.' : 'Online payments are temporarily unavailable during testing.' }}
                @else
                    {{ $ml ? 'ഓൺലൈൻ പേയ്‌മെൻ്റ് ഉടൻ ലഭ്യമാകും.' : 'Online payment will be available shortly.' }}
                @endif
            </div>
        </div>
    @else
        @if ($activePayment && !$activePayment->razorpay_link_url)
            <div class="warnbox">
                <div><b>{{ $ml ? 'പേയ്‌മെൻ്റ് ആരംഭിച്ചെങ്കിലും ഗേറ്റ്വേ ലിങ്ക് ഇതുവരെ തയ്യാറായിട്ടില്ല. പുതിയ ലിങ്കിനായി ശ്രമിക്കുക.' : 'Payment was initiated but the gateway link is not ready yet.' }}</b></div>
            </div>
        @else
            <div class="sub">{{ $ml ? 'എഡിറ്റോറിയൽ അവലോകനത്തിന് പോകാൻ പേയ്‌മെൻ്റ് പൂർത്തിയാക്കുക.' : 'Complete payment to proceed to editorial review.' }}</div>
        @endif
        <div class="actions" style="margin-top:16px">
            {{-- Razorpay launch: live payment temporarily unavailable. --}}
            <form id="pay-initiate-form" method="POST" action="{{ route('applications.payment.initiate', ['application' => $application->id]) }}" style="flex:1 1 100%;margin:0">
                @csrf
                <button class="btn primary block" type="submit" disabled aria-disabled="true">
                    @if ($activePayment)
                        {{ $ml ? 'പേയ്‌മെൻ്റ് വീണ്ടും ആരംഭിക്കുക' : 'Retry Payment' }}
                    @else
                        {{ $ml ? 'ഇപ്പോൾ പേയ്‌മെൻ്റ് ചെയ്യുക' : 'Pay Now' }}
                    @endif
                </button>
            </form>
            <div class="sub" style="flex:1 1 100%">
                @if (!empty($paymentsTestingMode))
                    {{ $ml ? 'ടെസ്റ്റിംഗ് കാലത്ത് ഓൺലൈൻ പേയ്‌മെൻ്റ് താൽക്കാലികമായി ലഭ്യമല്ല. പേയ്‌മെൻ്റ് ഇല്ലാതെ തന്നെ അപേക്ഷയുടെ എല്ലാ ഘട്ടങ്ങളും നിങ്ങൾക്ക് പരീക്ഷിക്കാം.' : 'Online payments are temporarily unavailable during testing. You can complete every application step without payment.' }}
                @else
                    {{ $ml ? 'ഓൺലൈൻ പേയ്‌മെൻ്റ് ഉടൻ ലഭ്യമാകും.' : 'Online payment will be available shortly.' }}
                @endif
            </div>
        </div>
    @endif
</div>

@if ($payments->count() > 0)
<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">{{ $ml ? 'പേയ്‌മെൻ്റ് ചരിത്രം' : 'Payment History' }}</h2>
    <div class="sub" style="margin-bottom:12px">{{ $ml ? 'സമീപകാല പേയ്‌മെൻ്റ് പ്രവർത്തനങ്ങൾ.' : 'Recent payment activity.' }}</div>

    <div class="stack">
        @foreach ($payments as $p)
            @php
                $row = $statusLabelMap[$p->status] ?? ['en' => ucfirst($p->status), 'ml' => ucfirst($p->status), 'cls' => 'tag'];
            @endphp
            <div class="mat-row">
                <div class="mat-meta">
                    <div class="row" style="gap:8px">
                        <span class="{{ $row['cls'] }}">{{ $row[$lang] }}</span>
                        <span class="note-safe">{{ $p->created_at->format('j M Y, H:i') }}</span>
                        @if ($p->invoice_number)
                            <span class="list-pill">{{ $p->invoice_number }}</span>
                        @endif
                    </div>
                    <div class="row" style="gap:12px;margin-top:2px">
                        <span class="note-safe">{{ $ml ? 'തുക' : 'Amount' }}: <b>{{ $p->currency }} {{ number_format((float)$p->amount, 2, '.', ',') }}</b></span>
                        <span class="note-safe">{{ $ml ? 'ഗേറ്റ്വേ' : 'Gateway' }}: {{ strtoupper($p->gateway ?? '') }}</span>
                    </div>
                    @if ($p->error_message)
                        <span class="note-safe" style="margin-top:2px;color:var(--bad)">{{ $ml ? 'കുറിപ്പ്' : 'Note' }}: {{ $p->error_message }}</span>
                    @endif
                </div>
                @if ($p->razorpay_link_url && $p->isActiveAttempt())
                    <a class="btn" style="padding:8px 12px;font-size:13px;min-height:36px" target="_blank" rel="noopener" href="{{ $p->razorpay_link_url }}">{{ $ml ? 'തുറക്കുക' : 'Open' }}</a>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif

<div class="card">
    <h2 style="margin-top:0;margin-bottom:6px">{{ $ml ? 'അപ്ലിക്കേഷൻ സംഗ്രഹം' : 'Application Summary' }}</h2>
    <div class="grid" style="grid-template-columns:1fr 1fr">
        <div>
            <div class="note-safe">{{ $ml ? 'പേര്' : 'Name' }}</div>
            <div style="font-weight:600">{{ $application->full_name ?? '—' }}</div>
        </div>
        <div>
            <div class="note-safe">{{ $ml ? 'ഉള്ളടക്കം സമർപ്പിക്കുന്ന രീതി' : 'Source Method' }}</div>
            <div style="font-weight:600">{{ $sourceMethodLabels[$application->source_method][$lang] ?? str_replace('_', ' ', \Illuminate\Support\Str::title($application->source_method)) }}</div>
        </div>
        <div>
            <div class="note-safe">{{ $ml ? 'ഇമെയിൽ' : 'Email' }}</div>
            <div style="font-weight:600">{{ $application->preferred_contact_email ?? '—' }}</div>
        </div>
        <div>
            <div class="note-safe">{{ $ml ? 'മൊബൈൽ' : 'Mobile' }}</div>
            <div style="font-weight:600">{{ $application->preferred_contact_mobile ?? '—' }}</div>
        </div>
    </div>
    <div class="actions" style="margin-top:18px">
        <a class="btn ghost" href="{{ route('applications.show', ['application' => $application->id]) }}">{{ $ml ? 'ഡാഷ്‌ബോർഡ്' : 'Dashboard' }}</a>
        @php
            $uploadsUnlocked = app(\App\Services\ApplicationPaymentStateService::class)->unlocksInterviewOrUploads($application);
        @endphp
        @if($uploadsUnlocked)
            <a class="btn" href="{{ route('applications.upload.show', ['application' => $application->id]) }}">{{ $ml ? 'അപ്‌ലോഡുകൾ' : 'Uploads' }}</a>
            @if($application->source_method === 'online_interview')
                <a class="btn" href="{{ route('online-interview.show', ['application' => $application->id]) }}">{{ $ml ? 'ഓൺലൈൻ അഭിമുഖം' : 'Online Interview' }}</a>
            @endif
        @else
            <span class="btn" aria-disabled="true" style="opacity:.55;cursor:not-allowed">{{ $ml ? 'പേയ്‌മെൻ്റിന് ശേഷം അപ്‌ലോഡുകൾ ലഭ്യമാകും' : 'Uploads unlock after payment' }}</span>
        @endif
    </div>
</div>
@endsection
