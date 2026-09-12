<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Jannayaks') }} — Online Interview</title>
    <style>
        <?php
            $path = base_path('resources/css/app.css');
            if (is_file($path) && is_readable($path)) {
                $contents = @file_get_contents($path);
                if (is_string($contents)) {
                    echo $contents;
                }
            }
            unset($path, $contents);
        ?>
        :root{
            --brand:#8A1A1A;
            --brand-dark:#5c0d0d;
            --ink:#1B1B18;
            --ink-soft:#4b4b48;
            --paper:#FDFDFC;
            --paper-alt:#F7F6F3;
            --line:#e7e5df;
            --ok:#1d6b3a;
            --warn:#7a5100;
            --bad:#7a1414;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:var(--paper);color:var(--ink);font-family:Inter,"Noto Sans Malayalam","Instrument Sans",ui-sans-serif,system-ui,sans-serif;line-height:1.55;-webkit-font-smoothing:antialiased}
        a{color:var(--brand)}
        :focus-visible{outline:3px solid #f5b7b1;outline-offset:2px;border-radius:6px}
        .wrap{max-width:720px;margin:0 auto;padding:20px 18px 80px}
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:22px;padding-bottom:14px;border-bottom:1px solid var(--line)}
        .logo{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.2px}
        .logo .pill{width:34px;height:34px;border-radius:999px;background:linear-gradient(135deg,#8A1A1A 0%,#C4202A 100%);display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px;box-shadow:0 1px 0 rgba(0,0,0,.06)}
        .card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 1px 0 rgba(18,18,18,.03), 0 1px 2px rgba(18,18,18,.04);padding:22px 20px;margin-bottom:18px}
        .card h1,.card h2,.card h3{margin:0 0 10px;color:var(--ink)}
        .card h1{font-size:22px;line-height:1.25}
        .card h2{font-size:18px}
        .sub{color:var(--ink-soft);font-size:14px;margin-bottom:14px}
        .lead{font-size:15px;color:var(--ink)}
        .stack > * + *{margin-top:14px}
        .tag{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:4px 10px;border-radius:999px;background:var(--paper-alt);color:var(--ink-soft);border:1px solid var(--line);font-weight:600}
        .tag.ok{background:#eef7f1;color:var(--ok);border-color:#d4e8da}
        .tag.warn{background:#fff6e2;color:var(--warn);border-color:#f2dfaa}
        .tag.bad{background:#fbe9e9;color:var(--bad);border-color:#f2c7c7}
        .progress{display:flex;flex-direction:column;gap:8px;margin:14px 0 6px}
        .bar{position:relative;height:10px;border-radius:999px;background:#efeee8;overflow:hidden;border:1px solid var(--line)}
        .bar > span{position:absolute;inset:0;width:0;background:linear-gradient(90deg,#C4202A 0%,#8A1A1A 100%);border-radius:999px;transition:width .3s ease}
        .progress-meta{display:flex;flex-wrap:wrap;gap:8px 18px;font-size:13px;color:var(--ink-soft)}
        .progress-meta b{color:var(--ink)}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;font-weight:600;padding:12px 16px;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);cursor:pointer;text-decoration:none;font-size:15px;min-height:44px}
        .btn:hover{border-color:#cbc9c0;background:#fbfaf7}
        .btn:active{background:#f4f2ec}
        .btn.primary{background:linear-gradient(180deg,#C4202A,#8A1A1A);border-color:transparent;color:#fff;box-shadow:0 1px 0 rgba(0,0,0,.08), inset 0 1px 0 rgba(255,255,255,.12)}
        .btn.primary:hover{filter:brightness(1.03)}
        .btn.ghost{background:transparent}
        .btn.block{display:flex;width:100%}
        .btn[disabled],.btn[aria-disabled="true"]{opacity:.55;cursor:not-allowed}
        .row{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
        .grid{display:grid;gap:12px}
        .tiers{grid-template-columns:1fr}
        @media(min-width:640px){.tiers{grid-template-columns:repeat(3,1fr)}}
        .tier{display:flex;flex-direction:column;gap:10px;padding:18px;border:1px solid var(--line);border-radius:14px;background:#fff;position:relative;cursor:pointer}
        .tier input{position:absolute;opacity:0;inset:0;width:100%;height:100%;cursor:pointer;margin:0}
        .tier:has(input:checked){outline:3px solid #f3b7ae;outline-offset:2px;border-color:#C4202A;background:#fff8f7}
        .tier h3{margin:0;font-size:16px;display:flex;align-items:center;justify-content:space-between}
        .tier .price{font-size:18px;font-weight:700;color:var(--brand)}
        .tier ul{margin:0;padding-left:18px;color:var(--ink-soft);font-size:14px;line-height:1.6}
        .field{display:flex;flex-direction:column;gap:8px}
        .field label{font-weight:600;font-size:14px;color:var(--ink)}
        .field .hint{color:var(--ink-soft);font-size:13px}
        .field input[type=text],.field input[type=email],.field input[type=tel],.field input[type=url],.field textarea,.field select{width:100%;padding:12px 12px;border-radius:10px;border:1px solid #d8d5cc;background:#fff;font-size:15px;color:var(--ink);min-height:44px}
        .field textarea{min-height:160px;line-height:1.65;resize:vertical;font-family:inherit}
        .field input:focus,.field textarea:focus,.field select:focus{border-color:#C4202A;box-shadow:0 0 0 3px #f6cfc8;outline:none}
        .q-meta{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:6px}
        .q-sec{font-size:12px;letter-spacing:.8px;text-transform:uppercase;color:var(--brand);font-weight:700}
        .q-num{font-weight:700;color:var(--ink-soft);font-size:13px}
        .req{color:var(--bad);font-weight:700}
        .bilingual{display:flex;flex-direction:column;gap:4px}
        .bilingual .en{font-size:16px;font-weight:600;color:var(--ink)}
        .bilingual .ml{font-size:15px;color:var(--ink-soft)}
        .actions{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;margin-top:20px}
        .actions .primary{flex:1 1 260px}
        .saved-toast{position:fixed;left:50%;bottom:22px;transform:translateX(-50%) translateY(12px);opacity:0;pointer-events:none;background:#11171b;color:#fff;padding:10px 14px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.25);transition:.25s ease;z-index:30}
        .saved-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
        .divider{height:1px;background:var(--line);margin:18px 0}
        .list-pill{display:inline-flex;align-items:center;gap:6px;font-size:12px;padding:4px 10px;border-radius:999px;background:#f5f3ed;border:1px solid var(--line);color:var(--ink-soft);font-weight:600}
        .list-pill + .list-pill{margin-left:6px}
        .warnbox{border-left:4px solid #e7b24c;background:#fff7e6;padding:12px 14px;border-radius:0 10px 10px 0;font-size:14px;margin:12px 0;color:#5a3b00}
        .missbox{border-left:4px solid #c0392b;background:#fbefee;padding:12px 14px;border-radius:0 10px 10px 0;font-size:14px;margin:12px 0;color:#5a0b0b}
        details{border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-bottom:12px;background:#fff}
        details summary{cursor:pointer;font-weight:600;color:var(--ink)}
        details + details{margin-top:-1px}
        .mat-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;margin-bottom:8px}
        .mat-meta{display:flex;flex-direction:column;font-size:13px;color:var(--ink-soft)}
        .mat-meta b{color:var(--ink);font-size:14px}
        .bytes{font-variant-numeric:tabular-nums;font-size:12px;color:var(--ink-soft)}
        .sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        .readonly{background:#fbfaf7}
        .readonly .field textarea{background:#f5f3ec;color:#3a3a37;cursor:default}
        .note-safe{color:var(--ink-soft);font-size:13px}
        .skip{position:fixed;top:8px;left:8px;z-index:40}.skip a{background:#fff;border:1px solid var(--line);padding:6px 10px;border-radius:8px;text-decoration:none;font-weight:600}
        @media(min-width:720px){.wrap{padding:28px 24px 100px}}
    </style>
</head>
<body>
<div class="skip"><a href="#main">Skip to content</a></div>
<div id="main" class="wrap">
    <header class="topbar">
        <a href="{{ route('home') }}" class="logo" aria-label="Jannayaks home">
            <span class="pill">ജ</span>
            <span>{{ config('app.name', 'Jannayaks') }}</span>
        </a>
        <div>
            @auth
                <a class="btn ghost" style="padding:8px 12px;font-size:14px;min-height:36px" href="{{ route('home') }}">{{ auth()->user()->name ?? 'Dashboard' }}</a>
            @endauth
            @guest
                <a class="btn ghost" style="padding:8px 12px;font-size:14px;min-height:36px" href="{{ route('filament.admin.auth.login') }}">Log in</a>
                <a class="btn" style="padding:8px 12px;font-size:14px;min-height:36px;margin-left:6px" href="{{ route('filament.admin.auth.login') }}">Register</a>
            @endguest
        </div>
    </header>

    @yield('content')
</div>
@stack('modals')
@stack('scripts')
</body>
</html>
