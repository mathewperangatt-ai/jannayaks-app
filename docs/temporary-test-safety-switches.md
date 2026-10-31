# Temporary test safety switches

Three temporary environment switches exist for pre-launch / synthetic testing.
They are **default-off**. This document describes operator-facing intent only.

**Important:** documentation, `.env.example` comments, and code defaults are
**not** a runtime production-environment guard. There is no `APP_ENV=production`
refusal that forces these flags off. Operators must keep production values safe.

Compare in code: `config/jannayaks.php`, `ApplicationPaymentStateService`,
`EditorialTestBypass`, `PublicationTestBypass`.

---

## Scope at a glance

| Switch | Scope | Safe production setting |
|--------|--------|-------------------------|
| `JANNAYAKS_PAYMENTS_TESTING_MODE` | **Global** — every application | `false` or unset |
| `JANNAYAKS_EDITORIAL_TEST_BYPASS_ENABLED` (+ references) | **Allowlist only** | `false` / unset, empty references |
| `JANNAYAKS_PUBLICATION_TEST_BYPASS_ENABLED` (+ references) | **Allowlist only** | `false` / unset, empty references |

Payment testing mode has a **global** blast radius. The editorial and
publication bypasses are narrower: they apply only when enabled **and** the
linked profile `reference_code` is uniquely allowlisted (and not already
publicly listed). An empty allowlist is fail-closed even if the enabled flag
is mistakenly true.

---

## 1. `JANNAYAKS_PAYMENTS_TESTING_MODE`

**Config:** `jannayaks.payments.testing_mode`
**Default (verified):** `false` via `env(..., false)`.

### Purpose
Suspend the payment requirement so testers can complete interview/uploads
workflows without paying, before (or without) a live gateway.

### Safe production setting
`false`, or omit the variable entirely.

### Scope / blast radius
**Global.** While true, every application is treated as unlocked for
interview/uploads. Payment initiation endpoints are refused. Member UI shows
testing notices. If `JANNAYAKS_AI_ENHANCEMENT_ENABLED` is unset, photo
enhancement follows this mode.

### Allowlist
None. There is no per-reference restriction.

### Still enforced when enabled
- No fake payment settlement is written by this flag alone.
- Real Razorpay settlement paths are unchanged; initiation is simply refused.
- This flag alone never activates the Razorpay gateway
  (`services.razorpay.enabled` remains separate).
- It does not by itself publish profiles.

### Why it must not be casually enabled in production
One mistaken `true` suspends payment for **all** applicants and blocks real
payment initiation site-wide.

### Safe verification (no secrets)
On the host (or via Railway UI variable list, names/values of booleans only):

1. Confirm `JANNAYAKS_PAYMENTS_TESTING_MODE` is absent, empty, or `false`.
2. After any env change, clear/rebuild config cache if the deploy uses
   `config:cache` (`php artisan config:clear` / redeploy cache rebuild).
3. Spot-check: a pending unpaid application must **not** unlock interview
   solely because of this mode; payment initiate must not return the testing
   “unavailable” path intended only for mode-on.

Do not paste API keys, webhook secrets, or full env dumps into tickets.

---

## 2. `JANNAYAKS_EDITORIAL_TEST_BYPASS_ENABLED`

**Companion:** `JANNAYAKS_EDITORIAL_TEST_BYPASS_REFERENCES` (comma-separated
profile reference codes).
**Config:** `jannayaks.editorial_test_bypass.enabled` /
`jannayaks.editorial_test_bypass.reference_codes`
**Defaults (verified):** `false` and `''`.

### Purpose
Allow editorial staff to release an English draft to a **private customer
preview** for a synthetic allowlisted profile without settled payment, without
marking that draft editorially approved.

### Safe production setting
- `JANNAYAKS_EDITORIAL_TEST_BYPASS_ENABLED=false` (or unset)
- `JANNAYAKS_EDITORIAL_TEST_BYPASS_REFERENCES=` empty (or unset)

### Scope / blast radius
**Narrow / allowlist-based.** Applies only when enabled **and** the
application’s profile `reference_code` is in the allowlist **and** that code
identifies exactly one profile **and** the profile is not publicly listed.
Otherwise `appliesTo` is false (fail-closed).

### Allowlist restrictions
- Explicit comma-separated references required.
- Empty list → bypass never applies.
- Duplicate / non-unique `reference_code` in the database → refuse.
- Already publicly listed profiles → refuse.

### Still enforced when enabled
- Editorial staff capability still required for release.
- Open maintenance / live-published paths remain refused as in normal code.
- Payment stays pending; no settlement/invoice/webhook is written.
- An unapproved draft still **cannot** be published.
- Draft status is not marked approved merely because bypass released preview.

### Why it must not be casually enabled in production
A wrong allowlisted reference can expose unpaid preview behaviour for a real
profile. Prefer empty references in production even if the flag is false.

### Safe verification (no secrets)
1. Confirm enabled is false/unset and references are empty.
2. Confirm Filament does not show “Test bypass active …” for real applications.
3. Do not put production reference codes into the allowlist “just in case”.

---

## 3. `JANNAYAKS_PUBLICATION_TEST_BYPASS_ENABLED`

**Companion:** `JANNAYAKS_PUBLICATION_TEST_BYPASS_REFERENCES`.
**Config:** `jannayaks.publication_test_bypass.enabled` /
`jannayaks.publication_test_bypass.reference_codes`
**Defaults (verified):** `false` and `''`.

### Purpose
Allow admin publication of one (or listed) synthetic non-public profile(s)
**without a settled payment**, for layout verification.

### Safe production setting
- `JANNAYAKS_PUBLICATION_TEST_BYPASS_ENABLED=false` (or unset)
- `JANNAYAKS_PUBLICATION_TEST_BYPASS_REFERENCES=` empty (or unset)

### Scope / blast radius
**Narrow / allowlist-based**, but **higher impact than editorial bypass**: a
matching profile can become publicly listed without payment. Still only those
references; not every application.

### Allowlist restrictions
Same pattern as editorial: enabled + unique allowlisted `reference_code` + not
already publicly listed. Empty allowlist is fail-closed.

### Still enforced when enabled
- Admin publish path only.
- Customer approval of the **exact** approved English version remains required;
  the exceptional admin path cannot use this bypass.
- Payment stays pending; no settlement is written.
- Disabling the flag restores the payment gate for later publication but does
  **not** unpublish a profile already made public through the bypass — an admin
  must unpublish explicitly after verification.
- Audited as `application.publication_test_bypass_used`.

### Why it must not be casually enabled in production
Allowlisting a real reference can publish that profile without payment. Treat
more strictly than the editorial preview bypass; keep references empty in
production.

### Safe verification (no secrets)
1. Confirm enabled is false/unset and references are empty.
2. Confirm no Filament “Publication test active …” banner on real apps.
3. If a test publish was ever used, confirm that profile was unpublished
   afterwards (flag off alone does not hide it).

---

## Operator checklist (production)

- [ ] `JANNAYAKS_PAYMENTS_TESTING_MODE` is `false` or unset.
- [ ] `JANNAYAKS_EDITORIAL_TEST_BYPASS_ENABLED` is `false` or unset.
- [ ] `JANNAYAKS_EDITORIAL_TEST_BYPASS_REFERENCES` is empty or unset.
- [ ] `JANNAYAKS_PUBLICATION_TEST_BYPASS_ENABLED` is `false` or unset.
- [ ] `JANNAYAKS_PUBLICATION_TEST_BYPASS_REFERENCES` is empty or unset.
- [ ] Remember: docs and defaults are not a hard production guard — verify the
      live host variables after every env change.
