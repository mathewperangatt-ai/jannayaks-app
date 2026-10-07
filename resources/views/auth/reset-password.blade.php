<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password — {{ config('app.name', 'Jannayaks') }}</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#FDFDFC;color:#1B1B18}
        .wrap{max-width:420px;margin:0 auto;padding:40px 18px}
        .card{background:#fff;border:1px solid #e7e5df;border-radius:14px;padding:24px}
        h1{font-size:22px;margin:0 0 8px}
        .sub{color:#4b4b48;font-size:14px;margin-bottom:18px}
        label{display:block;font-size:13px;font-weight:600;margin:12px 0 4px}
        input{width:100%;box-sizing:border-box;min-height:44px;padding:10px 12px;border:1px solid #e7e5df;border-radius:10px;font-size:14px}
        .btn{display:flex;width:100%;align-items:center;justify-content:center;min-height:44px;padding:12px 16px;border-radius:10px;border:1px solid transparent;background:linear-gradient(180deg,#C4202A,#8A1A1A);color:#fff;font-weight:600;margin-top:16px;cursor:pointer;font-size:14px}
        .errors{background:#fbe9e9;border-left:4px solid #7a1414;padding:10px 12px;margin-bottom:14px;font-size:14px}
        .back{font-size:13px;text-align:center;margin-top:16px}
        .back a{color:#1B1B18}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Set a new password</h1>
        <p class="sub">Choose a new password for your Jannayaks account.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="email">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            <label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            <button type="submit" class="btn">Reset password</button>
        </form>

        <div class="back"><a href="{{ route('login') }}">← Back to sign in</a></div>
    </div>
</div>
</body>
</html>
