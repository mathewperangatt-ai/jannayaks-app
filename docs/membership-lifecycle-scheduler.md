# Membership lifecycle scheduler (Phase 15)

## Application schedule

`routes/console.php` registers:

```text
php artisan membership:process-lifecycle
```

daily at `config('jannayaks.membership_lifecycle.scheduler.daily_at')` (default `01:15`)
in `config('jannayaks.membership_lifecycle.business_timezone')` (default `Asia/Kolkata`),
with `withoutOverlapping(120)`.

Membership calendar boundaries (expiry, grace, retention, reminders) use that same
business timezone. `config('app.timezone')` remains UTC for the rest of the app.

## Production requirement

Laravel’s scheduler only runs when an **external** system cron (or platform scheduler) invokes:

```text
* * * * * php /path/to/artisan schedule:run
```

Having the application schedule defined does **not** mean cron is running on Railway or any host.
Verify the deployment-side scheduler separately before relying on automatic deactivation/reminders.

## Railway deployment (required process)

A Railway deployment built by Nixpacks/Railpack runs **one** start process for the web
service (php-fpm + Caddy). A `Procfile` cannot add extra long-running processes: the
builders read at most a single `web`/`worker` start command and ignore other process
types. The scheduler therefore needs a **second Railway service** (Railway's official
Laravel pattern, per docs.railway.com → Laravel guide "Cron Service"):

1. Railway dashboard → the jannayaks project → **+ New → GitHub Repo** → select this
   same repo (`mathewperangatt-ai/jannayaks-app`), same branch (`main`).
2. Name the service `scheduler` (or `cron`).
3. In the service's **Settings → Deploy → Start Command**, set exactly:
   `php artisan schedule:work`
4. Copy the same **environment variables** as the web service (at minimum: `APP_ENV`,
   `APP_KEY` (same value), `APP_URL`, database variables, `CACHE_STORE`,
   `MEMBERSHIP_LIFECYCLE_DAILY_AT` if set). Railway "Shared Variables"/environment
   copies avoid drift.
5. Do **not** attach a public domain to this service. It needs no inbound traffic.
   Keep its builder identical to the web service (do not add a Dockerfile for only
   one of the two — both services must build the same image).
6. Deploy, then verify: the scheduler service logs should show
   `schedule:work` output; the day after deployment,
   `membership_lifecycle_events` gets rows when memberships cross boundaries, or run
   `php artisan membership:process-lifecycle` manually once against production to
   confirm DB connectivity from that service.

`schedule:work` runs `schedule:run` every minute internally and terminates alongside
maintenance mode. Combined with the `withoutOverlapping(120)` mutex on the lifecycle
command, overlapping invocations are safe.

No queue worker service is required today: the application dispatches nothing to
queues (`app/Jobs` does not exist); `QUEUE_CONNECTION=database` is idle.
