# Security headers & the CSP report-only decision

Implemented by `app/Http/Middleware/SetSecurityHeaders.php` (applied globally in
`bootstrap/app.php`). Policy values live in `config/jannayaks.php` →
`security.headers`.

## Always-on headers

| Header | Value |
|---|---|
| Strict-Transport-Security | `max-age=31536000; includeSubDomains` |
| X-Content-Type-Options | `nosniff` |
| X-Frame-Options | `DENY` |
| Referrer-Policy | `strict-origin-when-cross-origin` |
| Permissions-Policy | `camera=(), microphone=(), geolocation=(), usb=(), magnetometer=(), accelerometer=(), gyroscope=(), interest-cohort=()` |

`X-Powered-By` is stripped via `header_remove()` because `expose_php` is a
system-level INI directive that cannot be changed at runtime. Removing it in the
runtime image as well (`expose_php=Off`) is still worthwhile if the Nixpacks
build is ever replaced by a custom image; until then the middleware covers every
PHP-served response.

`payment=()` is deliberately NOT denied in Permissions-Policy so hosted payment
flows are never constrained.

## CSP: shipped as Content-Security-Policy-Report-Only

The policy was derived from what the templates actually load:

- `default-src 'self'` — app assets are self-hosted (`/css`, `/js`, `/branding`);
  public profile photos stream through same-origin routes, never bucket URLs.
- `style-src 'self' 'unsafe-inline' https://fonts.googleapis.com` — Google Fonts
  stylesheets plus genuine inline `<style>` blocks (auth pages, public layout).
- `font-src 'self' https://fonts.gstatic.com` — Google Fonts font files.
- `script-src 'self' 'unsafe-inline' https://translate.google.com https://translate.googleapis.com`
  — five templates ship real inline `<script>` blocks (home ×2, tier-select,
  upload, interview, profile-url) and the homepage loads the Google Translate
  widget; no template uses `eval`/`new Function`, so `'unsafe-eval'` is absent.
- `connect-src 'self' https://translate.googleapis.com` — Translate widget fetches.
- `frame-src https://translate.googleapis.com` — Translate injects its frames.
- `img-src 'self' data:` — QR codes are served as same-origin PNG responses;
  `data:` is kept for potential inline icons only.
- `base-uri 'self'`, `object-src 'none'`, `frame-ancestors 'none'` (mirrors
  X-Frame-Options), `form-action 'self'` (payments redirect to hosted Razorpay
  links; no cross-origin form posts exist).

### Enforcement (S2, current state)

The policy is now ENFORCED by default (`Content-Security-Policy` header).
Findings that made enforcement safe without a nonce refactor:

- Public/member pages use no Livewire and no Alpine; every inline `<script>` /
  `<style>` block is allowed by `'unsafe-inline'`, and all external resources
  (Google Fonts stylesheet + font files, homepage Google Translate widget) are
  in the allowlist derived from actual template usage.
- The Filament admin panel (Livewire + Alpine) needs one additional directive:
  Alpine evaluates expressions through the `Function` constructor, which an
  enforced CSP blocks unless `script-src` gains `'unsafe-eval'`. The admin
  policy (`security.headers.csp_admin`) is identical to the member policy
  except for that token; `SetSecurityHeaders` selects it by request path
  (`admin`, `admin/*`).
- MSG91/OTP hosts (`verify.msg91.com`) were removed from every directive when
  OTP authentication was retired; the widget is no longer loaded anywhere.

`JANNAYAKS_CSP_ENFORCE=false` still switches the header back to
`Content-Security-Policy-Report-Only` (debugging escape hatch, not the
production default). If a real violation surfaces (e.g. a future embed), fix
the policy — do not silently widen `default-src`.
