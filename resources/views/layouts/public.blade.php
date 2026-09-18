<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $htmlLang ?? 'en') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'Jannayaks'))</title>
    <style>
        :root{
            --brand:#8A1A1A;
            --brand-dark:#5c0d0d;
            --ink:#1B1B18;
            --ink-soft:#4b4b48;
            --paper:#FDFDFC;
            --paper-alt:#F7F6F3;
            --line:#e7e5df;
            --serif:"Iowan Old Style","Palatino Linotype",Palatino,Georgia,"Noto Serif",serif;
            --sans:"Noto Sans Malayalam","Instrument Sans",ui-sans-serif,system-ui,sans-serif;
        }
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;background:var(--paper);color:var(--ink);font-family:var(--sans);line-height:1.6;-webkit-font-smoothing:antialiased}
        a{color:var(--brand);text-decoration-thickness:1px;text-underline-offset:3px}
        a:hover{color:var(--brand-dark)}
        :focus-visible{outline:3px solid #f5b7b1;outline-offset:2px;border-radius:6px}
        .site{max-width:920px;margin:0 auto;padding:22px 20px 72px}
        .top{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:28px;padding-bottom:16px;border-bottom:1px solid var(--line)}
        .brand{display:flex;align-items:center;gap:10px;font-weight:700;letter-spacing:.02em;color:var(--ink);text-decoration:none}
        .brand .mark{width:36px;height:36px;border-radius:999px;background:linear-gradient(135deg,#8A1A1A 0%,#C4202A 100%);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:800}
        .nav{display:flex;flex-wrap:wrap;gap:14px;align-items:center}
        .nav a{color:var(--ink-soft);text-decoration:none;font-size:14px;font-weight:600}
        .nav a[aria-current="page"],.nav a:hover{color:var(--brand)}
        .eyebrow{font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-soft);font-weight:700;margin:0 0 10px}
        .lede{font-size:15px;color:var(--ink-soft);margin:0 0 22px;max-width:42rem}
        .search-panel{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin-bottom:28px}
        .search-panel label{display:block;font-size:13px;font-weight:700;margin-bottom:8px}
        .search-row{display:flex;gap:10px;flex-wrap:wrap}
        .search-row input[type=search],.search-row select{flex:1;min-width:180px;min-height:46px;padding:10px 14px;border:1px solid var(--line);border-radius:12px;background:var(--paper);font:inherit}
        .search-row button{min-height:46px;padding:10px 18px;border:0;border-radius:12px;background:var(--brand);color:#fff;font-weight:700;cursor:pointer}
        .search-row button:hover{background:var(--brand-dark)}
        .filters{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
        .filters select{min-height:40px;padding:8px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font:inherit;font-size:14px}
        .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px}
        .card-link{display:block;color:inherit;text-decoration:none;background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;transition:border-color .15s ease}
        .card-link:hover{border-color:#d6cfc4}
        .card-photo{aspect-ratio:4/3;background:linear-gradient(145deg,#f3efe8,#e7e1d6);display:flex;align-items:center;justify-content:center;overflow:hidden}
        .card-photo img{width:100%;height:100%;object-fit:cover}
        .card-photo .placeholder{font-size:28px;font-weight:700;color:#b7aea1;font-family:var(--serif)}
        .card-body{padding:16px 16px 18px}
        .card-body h2{margin:0 0 6px;font-size:18px;line-height:1.25;font-family:var(--serif);font-weight:700}
        .card-meta{margin:0;font-size:14px;color:var(--ink-soft)}
        .pager{margin-top:28px;display:flex;justify-content:center}
        .pager nav{display:flex;gap:8px;flex-wrap:wrap}
        .empty{padding:36px 8px;color:var(--ink-soft);text-align:center}
        @media (max-width:640px){
            .site{padding:16px 14px 56px}
            .top{flex-direction:column;align-items:flex-start}
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="site">
        <header class="top">
            <a class="brand" href="{{ route('gallery.index') }}">
                <span class="mark" aria-hidden="true">JN</span>
                <span>Jannayaks</span>
            </a>
            <nav class="nav" aria-label="Public">
                <a href="{{ route('gallery.index') }}" @if(($nav ?? '') === 'gallery') aria-current="page" @endif>Gallery</a>
                <a href="{{ route('search.index') }}" @if(($nav ?? '') === 'search') aria-current="page" @endif>Search</a>
                <a href="{{ route('apply') }}">Apply</a>
            </nav>
        </header>
        <main id="main">
            @yield('content')
        </main>
    </div>
</body>
</html>
