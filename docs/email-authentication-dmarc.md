# Email authentication hardening — DMARC determination (S2/S3)

Status as of the S2/S3 pass (2026-10-08). DNS changes are production
infrastructure and are deliberately NOT made from the application repo; this
document is the determination the DNS owner executes.

## Current public state (verified via DNS, 2026-10-08)

| Record | Value | Assessment |
|---|---|---|
| `jannayaks.in` TXT (SPF) | `v=spf1 include:_spf.google.com ~all` | Google Workspace authorized. **Resend's sending infrastructure is NOT authorized in SPF.** |
| `resend._domainkey.jannayaks.in` TXT | RSA public key | Resend DKIM signing key published. |
| `_dmarc.jannayaks.in` TXT | `v=DMARC1; p=none; rua=mailto:founder@jannayaks.in` | Monitor-only: no enforcement, reports to founder mailbox. |
| MX | `smtp.google.com` | Inbound mail via Google Workspace. |
| `send.jannayaks.in` CNAME | `send.forge.rmta.net` (+ its SPF) | Return-path subdomain points at a NON-Resend provider (rmta.net). Resend-sent mail therefore uses Resend's own envelope sender, which the current SPF does not cover. |

## Determined hardening path

1. **Prerequisite (Resend alignment):** authorize Resend in SPF and give
   Resend-aligned bounce mail its own aligned return-path:
   - Add `include:amazonses.com` to the apex SPF (Resend sends via SES), OR
     scope it to a dedicated sending subdomain.
   - Provision Resend's custom return-path (e.g. `bounce.jannayaks.in` CNAME to
     `feedback-smtp.<region>.amazonses.com` with the matching SPF record) so
     the envelope-from aligns with the From: domain.
   - Keep `resend._domainkey` as the DKIM selector; confirm "Verified" in the
     Resend dashboard.
2. **Monitor (now → 2+ weeks):** keep `p=none` with `rua=` and review aggregate
   reports. Everything legitimate should already align via Google (calendar/
   human mail) and — after step 1 — via Resend (transactional mail).
3. **Ramp enforcement:** move to
   `v=DMARC1; p=quarantine; pct=25; rua=mailto:founder@jannayaks.in; sp=none`
   → raise `pct` 50 → 100 over two weeks as reports stay clean.
4. **End state:**
   `v=DMARC1; p=reject; sp=quarantine; adkim=s; aspf=r; rua=mailto:founder@jannayaks.in`
   - `adkim=s` (strict DKIM alignment) is safe because Resend and Google both
     sign with the jannayaks.in d= domain.
   - `aspf=r` stays relaxed because Google's and SES's envelope-from domains
     differ from the header From: domain.
   - `sp=quarantine` protects non-sending subdomains without breaking any
     current subdomain use (`send.jannayaks.in` sends its own SPF in the
     envelope, not under DMARC of the subdomain label).

Do not jump to `p=reject` without step 1: legitimate Resend mail would fail
SPF alignment and, once DKIM is also misaligned on any envelope change, land
in quarantine.
