<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create an account — {{ config('app.name', 'Jannayaks') }}</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#FDFDFC;color:#1B1B18}
        .wrap{max-width:460px;margin:0 auto;padding:40px 18px}
        .card{background:#fff;border:1px solid #e7e5df;border-radius:14px;padding:24px}
        h1{font-size:22px;margin:0 0 8px}
        .sub{color:#4b4b48;font-size:14px;margin-bottom:18px}
        label{display:block;font-size:13px;font-weight:600;margin:12px 0 4px}
        input{width:100%;box-sizing:border-box;min-height:44px;padding:10px 12px;border:1px solid #e7e5df;border-radius:10px;font-size:14px}
        .btn{display:flex;width:100%;align-items:center;justify-content:center;min-height:44px;padding:12px 16px;border-radius:10px;border:1px solid transparent;background:linear-gradient(180deg,#C4202A,#8A1A1A);color:#fff;font-weight:600;margin-top:16px;cursor:pointer;font-size:14px}
        .errors{background:#fbe9e9;border-left:4px solid #7a1414;padding:10px 12px;margin-bottom:14px;font-size:14px}
        .hint{font-size:12px;color:#4b4b48;margin:4px 0 0}
        .login{font-size:14px;text-align:center;margin-top:16px}
        .login a{font-weight:600;color:#1B1B18}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Create your Jannayaks account</h1>
        <p class="sub">Your username and password are your Jannayaks sign-in for the customer dashboard.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <label for="name">Full name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="{{ old('username') }}" required autocomplete="username">
            <p class="hint">3–32 characters. Letters, numbers, dots, dashes and underscores.</p>
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            <button type="submit" class="btn">Create account</button>
        </form>

        <div class="login">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
    </div>
</div>
</body>
</html>
