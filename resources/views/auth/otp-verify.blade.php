<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify OTP — {{ config('app.name', 'Jannayaks') }}</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#FDFDFC;color:#1B1B18}
        .wrap{max-width:420px;margin:0 auto;padding:40px 18px}
        .card{background:#fff;border:1px solid #e7e5df;border-radius:14px;padding:24px}
        h1{font-size:20px;margin:0 0 8px}
        .sub{color:#4b4b48;font-size:14px;margin-bottom:18px}
        label{font-weight:600;font-size:14px;display:block;margin-bottom:6px}
        input{width:100%;padding:12px;border-radius:10px;border:1px solid #d8d5cc;font-size:15px;box-sizing:border-box;min-height:44px}
        .btn{display:inline-flex;min-height:44px;padding:12px 16px;border-radius:10px;border:0;background:linear-gradient(180deg,#C4202A,#8A1A1A);color:#fff;font-weight:600;margin-top:14px;cursor:pointer}
        .errors{background:#fbe9e9;border-left:4px solid #7a1414;padding:10px 12px;margin-bottom:14px;font-size:14px}
        .ok{background:#eef7f1;border-left:4px solid #1d6b3a;padding:10px 12px;margin-bottom:14px;font-size:14px}
        a{color:#8A1A1A}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Enter OTP</h1>
        <p class="sub">Code sent to {{ \App\Support\IndiaMobile::display($mobile) }}</p>

        @if (session('status'))
            <div class="ok">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="post" action="{{ route('auth.otp.verify') }}">
            @csrf
            <input type="hidden" name="mobile" value="{{ $mobile }}">
            <label for="otp">6-digit OTP</label>
            <input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required>
            <button class="btn" type="submit">Verify and continue</button>
        </form>
        <p class="sub" style="margin-top:16px"><a href="{{ route('auth.otp.request.show') }}">Request a new code</a></p>
    </div>
</div>
</body>
</html>
