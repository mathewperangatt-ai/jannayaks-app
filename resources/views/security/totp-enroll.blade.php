<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Jannayaks — Two-factor enrollment</title>
  <style>
    body{margin:0;background:#faf6ee;font:16px/1.6 "DM Sans",system-ui,sans-serif;color:#1f2937;display:grid;place-items:center;min-height:100vh}
    .card{background:#fff;border:1.5px solid #1f5f8b;border-radius:12px;padding:32px;max-width:460px;width:calc(100% - 32px)}
    h1{font-size:20px;margin:0 0 12px;color:#0f1f3d}
    code,.mono{font-family:ui-monospace,monospace;background:#f1f5f9;padding:2px 6px;border-radius:4px;word-break:break-all}
    ol{padding-left:20px;margin:12px 0}
    .codes{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin:12px 0;font-family:ui-monospace,monospace;font-size:14px;word-spacing:10px}
    input{width:100%;box-sizing:border-box;padding:12px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:18px;letter-spacing:.35em;text-align:center;margin:10px 0}
    button{width:100%;padding:12px;background:#0f1f3d;color:#fff;border:0;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer}
    .warn{font-size:12.5px;color:#7c2d12;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:8px 10px;margin:12px 0}
    form{margin-top:16px}
  </style>
</head>
<body>
  <div class="card">
    <h1>Two-factor enrollment required</h1>
    <p>Set up your authenticator app for the <strong>Jannayaks Security Administrator</strong> account. This is mandatory before you can access the administration panel.</p>
    <ol>
      <li>Open your authenticator app (Aegis, 1Password, Google Authenticator, Proton Authenticator…).</li>
      <li>Choose <em>Enter a setup key</em> and paste this secret:</li>
    </ol>
    <p><code>{{ $secret }}</code></p>
    <p style="font-size:12px;color:#64748b">Account: <span class="mono">{{ $uri }}</span></p>
    <div class="warn"><strong>Recovery codes — store them in your password manager now. They are shown only this once and each works a single time.</strong>
      <div class="codes">{{ implode(' ', $recoveryCodes) }}</div>
    </div>
    <form method="post" action="{{ route('security.totp.confirm') }}">
      @csrf
      <label for="code">Enter the 6-digit code to confirm</label>
      <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
      <button type="submit">Confirm and continue</button>
    </form>
  </div>
</body>
</html>
