# Demo Professional Profiles — Workflow Progress

Six fictional professional demo profiles processed through the REAL Jannayaks workflow
(application → interview → AI editorial → human review → photographs → customer approval →
payment waiver → publication) via `DemoProfessionalsSeeder` (idempotent, resumable).

Run command (production): `php artisan db:seed --class=DemoProfessionalsSeeder --force`
Local test run: `JANNAYAKS_AI_PROVIDER=fake php artisan db:seed --class=DemoProfessionalsSeeder --force`

## Status table — LOCAL smoke run (fake AI provider): ALL STAGES PASSED

| Applicant | Account/application | Interview | EN gen | ML gen | 3 photos | Review | Approval | Waiver | Published | Layout |
|---|---|---|---|---|---|---|---|---|---|---|
| Dr. Anjali Menon | ✔ | ✔ 22/22 | ✔ | ✔ | ✔ 3/3 | ✔ | ✔ | ✔ audited | ✔ anjali.menon | ✔ |
| Rajeev Nair | ✔ | ✔ 18/22 (4 skipped) | ✔ | ✔ | ✔ 3/3 | ✔ | ✔ | ✔ audited | ✔ rajeev.nair | ✔ |
| Adv. Meera Krishnan | ✔ | ✔ 20/22 (Q20/Q21 skipped) | ✔ | ✔ | ✔ 3/3 | ✔ promo stripped | ✔ | ✔ audited | ✔ meerakrishnan | ✔ |
| Prof. Suresh Varma | ✔ | ✔ 16/22 (6 skipped) | ✔ | ✔ | ✔ 3/3 | ✔ | ✔ | ✔ audited | ✔ suresh.varma | ✔ |
| Nisha Thomas | ✔ | ✔ 19/22 (3 skipped) | ✔ | ✔ | ✔ 3/3 | ✔ | ✔ | ✔ audited | ✔ nisha.thomas | ✔ |
| S. Harikrishnan | ✔ | ✔ 15/22 (7 skipped) | ✔ | ✔ | ✔ 3/3 | ✔ | ✔ | ✔ audited | ✔ s.harikrishnan | ✔ |

## Production run (jannayaks.in, ba8ba76, 2026-11-22): COMPLETE — all six PUBLISHED

| Applicant | Account/application | Interview | EN gen | ML gen | 3 photos | Review | Approval | Waiver | Published | Layout |
|---|---|---|---|---|---|---|---|---|---|---|
| Dr. Anjali Menon | ✔ anjali_menon.demo@jannayaks.internal | ✔ 22/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ | ✔ version-pinned | ✔ audited | ✔ /anjali.menon | ✔ EN+ML, 3 photos live |
| Rajeev Nair | ✔ rajeev_nair.demo@… | ✔ 18/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ | ✔ | ✔ audited | ✔ /rajeev.nair | ✔ |
| Adv. Meera Krishnan | ✔ meera_krishnan.demo@… | ✔ 20/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ promo stripped (0 markers live) | ✔ | ✔ audited | ✔ /meerakrishnan | ✔ |
| Prof. Suresh Varma | ✔ suresh_varma.demo@… | ✔ 16/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ | ✔ | ✔ audited | ✔ /suresh.varma | ✔ |
| Nisha Thomas | ✔ nisha_thomas.demo@… | ✔ 19/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ | ✔ | ✔ audited | ✔ /nisha.thomas | ✔ |
| S. Harikrishnan | ✔ s_harikrishnan.demo@… | ✔ 15/22 | ✔ gpt-4.1-mini | ✔ | ✔ 3/3 live | ✔ | ✔ | ✔ audited | ✔ /s.harikrishnan | ✔ |

Production verification (2026-11-22): demo listing shows all six new profiles alongside the
existing 14; each live page returns 200 with the jk presentation, exactly 3 distinct photos,
the DEMO — Fictional Profile label and the AI-generated-photograph disclosure; EN and ML
(?lang=ml) versions render; Adv. Meera Krishnan's live page contains zero promotional
markers. Existing 14 demo profiles unaffected (spot-check t.gopalakrishnan ✔). AI runs:
6/6 succeeded via gpt/gpt-4.1-mini (existing production OpenAI integration).

## Notes
- Interview submissions use the REAL controller path: validation, confirmations, rate limiter,
  consents, `markInterviewSubmitted`. Q1 (only required question) present for all six ✔.
- AI generation uses the existing `EditorialGenerationService` (EN + ML drafts, verbatim AI
  output preserved as versions; human review edits drafts in place before approval).
- Adv. Meera Krishnan: promotional Q22 sentences (fees/appointments/enquiries) are removed
  during human review via the standard editor-update path; factual practice info retained.
- Photographs: 3 per applicant via `ProfileMediaService` (optimise → SHA-256 → private →
  staff-approved), primary portrait designation preserved. Primary-only enhancement policy
  untouched (dispatch is internal to the upload service).
- Payment waivers via `ApplicationPaymentStateService::recordStaffWaiver` (admin actor,
  audited) ✔ verified `payment_status: waived`.
- Demo disclosure: demo profiles carry the conspicuous "DEMO — Fictional Profile" note and
  AI-generated-image disclosure (demo-note line in `profile-content.blade.php`).
- Local run used the fake AI provider (no local API key); production run uses the existing
  configured OpenAI integration (12 paid generations total, avoid re-runs — seeder is
  resumable and skips completed stages).

## Blockers / next action
- None. Deploy this branch to production, then run the seeder command once over Railway SSH
  (background; poll the output), then verify demo pages (EN/ML, labels, AI disclosures).
