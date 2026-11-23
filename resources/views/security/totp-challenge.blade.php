<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jannayaks — Security verification</title>
  <style>
    body{margin:0;background:#faf6ee;font:16px/1.6 "DM Sans",system-ui,sans-serif;color:#1f2937;display:grid;place-items:center;min-height:100vh}
    .card{background:#fff;border:1.5px solid #1f5f8b;border-radius:12px;padding:32px;max-width:460px;width:calc(100% - 32px)}
    h1{font-size:20px;margin:0 0 12px;color:#0f1f3d}
    input{width:100%;box-sizing:border-box;padding:12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:18px;letter-spacing:.35em;text-align:center;margin:10px 0}
    button{width:100%;padding:12px;background:#0f1f3d;color:#fff;border:0;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer}
    .hint{font-size:12.5px;color:#64748b;margin-top:10px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Security verification</h1>
    <p>Enter the 6-digit code from your authenticator app, or one of your single-use recovery codes.</p>
    <form method="post" action="{{ route('security.totp.verify') }}">
      @csrf
      <label for="code">Verification code</label>
      <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
      <button type="submit">Verify</button>
    </form>
    <p class="hint">Signed in as {{ auth()->user()->email }}. Session-bound: you will verify again at your next sign-in.</p>
  </div>
</body>
</html>
