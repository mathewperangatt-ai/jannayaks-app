# Retire obsolete short living demos (production)

This procedure removes **only** the seven retired Recognised/Acclaimed short living demos from public listings. It does **not** run as part of a normal code deploy. Do not use `DemoProfilesSeeder` on production for this purpose unless you intentionally intend to reseed the full demo corpus.

## Targets (exact identities)

| Email | Full name |
| --- | --- |
| `demo.fr.joseph.mathew@jannayaks.internal` | Fr. Joseph Mathew |
| `demo.p.rajeev.menon@jannayaks.internal` | P. Rajeev Menon |
| `demo.s.beena.kumari@jannayaks.internal` | S. Beena Kumari |
| `demo.k.shafiq.rahman@jannayaks.internal` | K. Shafiq Rahman |
| `demo.r.leelamma@jannayaks.internal` | R. Leelamma |
| `demo.c.manoj.kumar@jannayaks.internal` | C. Manoj Kumar |
| `demo.a.mariamma@jannayaks.internal` | A. Mariamma |

A profile is changed only when **both** the demo email and the full/display name match. Other demo accounts, In Memoriam demos, and real members are never updated.

## Retained public demos

Living: T. Gopalakrishnan, P. Sreedharan, V. Suresh Babu, Arjun Menon, Nanditha Das, Fahim Yousuf, Meera Krishnan  
In Memoriam: K. V. Mathew (Late), Dr. Saroja Nair (Late)

## Execution (after this branch is deployed; dry-run first)

On the production application host (Railway shell / one-off command), with the deployed release that includes `jannayaks:retire-obsolete-living-demos`:

```bash
php artisan jannayaks:retire-obsolete-living-demos --dry-run
```

Confirm the command lists only the seven targets and reports which published profiles would be retired. Then:

```bash
php artisan jannayaks:retire-obsolete-living-demos
```

The command is idempotent: already-inactive matches are skipped.

## Verification

1. `GET /demo-profiles` must not list any of the seven names above.
2. `GET /gallery` must not list those seven names for demo-domain owners.
3. The seven retained living demos and both memorials remain listed.
4. Re-run with `--dry-run`; expect “already retired” / no further would-retire rows for those seven.

## Notes

- Status becomes `inactive` with `unpublished_at` set (same shape as membership deactivation for public visibility).
- No production environment variables, Razorpay settings, `noindex`, or migrations are required for this operation.
