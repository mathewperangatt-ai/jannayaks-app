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
