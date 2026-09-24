# Production launch — manual steps (Railway / Cloudflare R2)

These are the operator steps the code changes depend on. Nothing here touches
secrets in the repository. Do these AFTER deploying the Wave 1 + Wave 2 commits.

## 1. Private R2 bucket for applicant source materials

Applicant uploads previously landed on the container-local disk, which is erased
on every Railway redeploy. The `source_materials` disk (config/filesystems.php)
now points at a dedicated PRIVATE R2 bucket.

1. Cloudflare dashboard → R2 → **Create bucket** → name: `jannayaks-source-materials`
   (or any name; set `R2_SOURCE_BUCKET` to match).
2. **Leave the bucket entirely private.** Do not enable public access, custom
   domain, or a public.dev subdomain. Files are read only through the staff
   download route (`/staff/source-materials/{id}/download`), which enforces
   role + purge checks. Never attach this bucket to the public site.
3. API token: the existing `R2_ACCESS_KEY_ID` / `R2_SECRET_ACCESS_KEY` used for
   `jannayaks-media` can be reused IF that token has write permission on the new
   bucket. Otherwise create a dedicated token scoped to only this bucket and use
   `R2_SOURCE_ACCESS_KEY_ID` / `R2_SOURCE_SECRET_ACCESS_KEY` instead.

### Railway environment variables (web service)

| Variable | Value |
|---|---|
| `SOURCE_MATERIALS_DISK` | `source_materials` |
| `R2_SOURCE_BUCKET` | `jannayaks-source-materials` |
| `R2_SOURCE_ENDPOINT` | `https://<account_id>.r2.cloudflarestorage.com` (same endpoint as the media bucket) |
| `R2_SOURCE_REGION` | `auto` |
| `R2_SOURCE_ACCESS_KEY_ID` | optional; omit to reuse `R2_ACCESS_KEY_ID` |
| `R2_SOURCE_SECRET_ACCESS_KEY` | optional; omit to reuse `R2_SECRET_ACCESS_KEY` |

Notes:
- Until these are set, uploads keep going to the legacy local `private_uploads`
  disk — still functional, still ephemeral. Set them before accepting real
  applicants.
- Existing rows keep `storage_disk = private_uploads`; downloads of legacy
  files keep working unchanged (per-row disk resolution).
- The bucket is referenced by code but its contents are never exposed by URL.

## 2. Railway scheduler service (membership lifecycle + AI run sweeper)

A Railway web service runs ONE process. The Laravel scheduler needs its own
long-running service (Railpack/Nixpacks ignore extra Procfile process types, so
a Procfile alone cannot provide this).

1. Railway project → **+ New → GitHub Repo** → same repo, same branch (`main`).
2. Name: `scheduler`.
3. Service **Settings → Deploy → Start Command**:
   `php artisan schedule:work`
4. Copy the same environment variables as the web service (at minimum `APP_ENV`,
   `APP_KEY` (identical value), `APP_URL`, all `DB_*`/`DATABASE_URL`,
   `CACHE_STORE`, `SESSION_DRIVER`, and both `MEMBERSHIP_*` and
   `JANNAYAKS_OPENAI_TIMEOUT` if customized). Shared variables are safest.
5. Do NOT attach a public domain. Keep the builder identical to the web service.
6. Verify after deploy: scheduler service logs show heartbeat output; the
   membership command is registered (`01:15` Asia/Kolkata daily) and the AI
   sweeper every 15 minutes (`routes/console.php`).

`schedule:work` plus the `withoutOverlapping` mutexes make overlapping or
restarted services safe. No queue worker is needed: the app dispatches nothing
to queues today.

## 3. Trusted proxies (client-IP hardening)

`bootstrap/app.php` now reads `TRUSTED_PROXIES`. **Unset = current behavior
(`*`, trust all)**, so deploying is zero-risk; then tighten in production:

| Variable | Value |
|---|---|
| `TRUSTED_PROXIES` | Cloudflare CIDR list (https://www.cloudflare.com/ips/ , both IPv4+IPv6) **plus** your Railway edge/internal ranges. Comma-separated, no spaces required. |

Until Cloudflare's list changes, copies are commented in `.env.example`.
After setting: `php artisan config:clear` is enough (config is not cached by
default); if you enable `config:cache` during deploys, rebuild the cache.

Verify: `request()->ip()` in a logged context should show the true client IP
(Cloudflare `CF-Connecting-IP`-resolved), not a 172.7x.x.x / 10.x.x.x hop, and
spoofed `X-Forwarded-For` from outsiders must be ignored.

## 4. OTP login disabled until an SMS provider exists

`MobileOtpService` still has no SMS transport: requesting an "OTP" logs it and
nothing is delivered. Wave 2 adds an honest kill switch.

| Variable | Value |
|---|---|
| `JANNAYAKS_OTP_LOGIN_ENABLED` | `false` |

Effect: OTP routes redirect to login with "Mobile OTP sign-in is currently
unavailable", and the login page hides the OTP entry point. Google sign-in is
unaffected. When a real SMS provider is implemented later, remove the variable
(defaults to enabled).

## 5. Post-deploy verification checklist

- [ ] Web service: `/up` returns 200; login page shows Google only (OTP hidden).
- [ ] Scheduler service: running; logs show `schedule:work` output.
- [ ] Upload a source material as a test applicant; confirm the object appears
      in the R2 `jannayaks-source-materials` bucket (Cloudflare dashboard) and
      downloads via the staff route.
- [ ] `SOURCE_MATERIALS_DISK` left unset in any environment that should keep
      legacy local behavior (none in production).
- [ ] Suspended-account check: temporarily suspend a test member; confirm they
      are redirected to login with the suspension message on next request.
- [ ] Admin panel: an application moved to `awaiting_publication` increments
      the Applications nav badge.
