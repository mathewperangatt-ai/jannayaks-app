# GST CGST/SGST vs IGST (live-payment readiness)

## Behaviour in this branch

`PricingAmounts` adds 18% GST on exclusive bases. The **total** (`amount_incl_paise`) is unchanged by classification.

Component split uses **only** verified inputs:

1. Seller state: `config('jannayaks.tier_pricing.billing.state')` / `JANNAYAKS_BILLING_STATE` (must be set for a classified split).
2. Recipient place of supply: an explicit argument to `forStandardProfile()` / `forTier()` / `forApplication()` (customer billing state).

| Seller state | Place of supply | Result |
| --- | --- | --- |
| Present, equal | Present, equal | CGST + SGST (half each) |
| Present | Present, different | IGST = full GST |
| Either missing | — | Unclassified: `gst_paise` only; no CGST/SGST/IGST invention |

Payment page and tax invoice show CGST/SGST, IGST, or a single “GST (added)” line accordingly. Razorpay charge amount remains the inclusive total.

## Gap: customer place of supply is not collected yet

Applications do not currently store a customer billing state / place of supply. Invoice “place of supply” is therefore left empty rather than copying seller config.

### Smallest necessary change before live classified invoices

1. Add a nullable `billing_place_of_supply` (Indian state name) on `applications`, collected on the payment step before initiating a gateway link.
2. Pass that value into `PricingAmounts::forApplication($application, $placeOfSupply)` so initiation, display, payment `cgst`/`sgst`/`igst` columns, and the tax invoice stay consistent.
3. Optionally persist `place_of_supply` on `payments` for the invoice document.

Do **not** invent state from profile geography or default every quote to CGST/SGST.

## Tests

See `tests/Feature/GstClassificationTest.php` for intra-state, inter-state, and unclassified cases.
