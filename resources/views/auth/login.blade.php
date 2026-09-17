<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — {{ config('app.name', 'Jannayaks') }}</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#FDFDFC;color:#1B1B18}
        .wrap{max-width:420px;margin:0 auto;padding:40px 18px}
        .card{background:#fff;border:1px solid #e7e5df;border-radius:14px;padding:24px}
        h1{font-size:22px;margin:0 0 8px}
        .sub{color:#4b4b48;font-size:14px;margin-bottom:18px}
        .btn{display:flex;width:100%;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:12px 16px;border-radius:10px;border:1px solid #e7e5df;background:#fff;font-weight:600;text-decoration:none;color:#1B1B18;margin-top:10px;box-sizing:border-box}
        .btn.primary{background:linear-gradient(180deg,#C4202A,#8A1A1A);border-color:transparent;color:#fff}
        .errors{background:#fbe9e9;border-left:4px solid #7a1414;padding:10px 12px;margin-bottom:14px;font-size:14px}
        .divider{text-align:center;color:#4b4b48;font-size:13px;margin:16px 0}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Sign in to Jannayaks</h1>
        <p class="sub">Google is the primary sign-in. Indian members may use mobile OTP as a fallback.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <a class="btn primary" href="{{ route('auth.google') }}">Continue with Google</a>
        <div class="divider">or</div>
        <a class="btn" href="{{ route('auth.otp.request.show') }}">Sign in with Indian mobile OTP</a>
    </div>
</div>
</body>
</html>
