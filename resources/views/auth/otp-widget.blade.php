<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in with mobile OTP — {{ config('app.name', 'Jannayaks') }}</title>
    <style>
        body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;background:#FDFDFC;color:#1B1B18}
        .wrap{max-width:420px;margin:0 auto;padding:40px 18px}
        .card{background:#fff;border:1px solid #e7e5df;border-radius:14px;padding:24px}
        h1{font-size:22px;margin:0 0 8px}
        .sub{color:#4b4b48;font-size:14px;margin-bottom:18px}
        .btn{display:flex;width:100%;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:12px 16px;border-radius:10px;border:1px solid #e7e5df;background:#fff;font-weight:600;text-decoration:none;color:#1B1B18;margin-top:10px;box-sizing:border-box}
        .errors{background:#fbe9e9;border-left:4px solid #7a1414;padding:10px 12px;margin-bottom:14px;font-size:14px}
        #otp-container{margin-top:8px}
        .back{margin-top:16px;text-align:center;font-size:14px}
        .back a{color:#8A1A1A}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>Sign in with Indian mobile OTP</h1>
        <p class="sub">Enter your Indian mobile number, then the one-time password sent to it. You will be signed in automatically once it is verified.</p>

        @if ($errors->any())
            <div class="errors">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (session('status'))
            <div class="errors" style="background:#eef7f1;border-color:#2e7d4f">{{ session('status') }}</div>
        @endif

        @if ($widgetConfigured)
            <div id="otp-container"></div>

            <form id="otp-token-form" method="POST" action="{{ route('auth.otp.verify') }}" style="display:none">
                @csrf
                <input type="hidden" name="access_token" id="otp-access-token" value="">
            </form>

            <script src="https://verify.msg91.com/otp-provider.js"
                    onload="initSendOTP(otpWidgetConfig())"></script>
            <script>
                function otpWidgetConfig() {
                    return {
                        widgetId: @js($widgetId),
                        tokenAuth: @js($widgetToken),
                        success: function (data) {
                            // The widget hands over the verified access token on
                            // success; submit it to Jannayaks for server-side
                            // verification before any authentication happens.
                            var token = (data && (data.accessToken || data.token || (data.message && typeof data.message === 'string' && data.message.split(' ').length === 1 ? data.message : null))) || null;
                            if (!token) {
                                window.location.reload();
                                return;
                            }
                            document.getElementById('otp-access-token').value = token;
                            document.getElementById('otp-token-form').submit();
                        },
                        failure: function () {
                            window.location.reload();
                        }
                    };
                }
            </script>
        @else
            <p class="sub" style="color:#7a1414">Mobile OTP sign-in is temporarily unavailable. Please use Google sign-in.</p>
            <a class="btn" href="{{ route('login') }}">Back to sign-in options</a>
        @endif

        <div class="back"><a href="{{ route('login') }}">&larr; Other sign-in options</a></div>
    </div>
</div>
</body>
</html>
