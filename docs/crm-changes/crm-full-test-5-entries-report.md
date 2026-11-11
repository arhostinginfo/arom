# Full CRM test with 5 dummy entries and page setup (22/10/2026)

Paths are relative to `D:\xampp\htdocs\nursery-app`.

## Result

- **New test:** `tests/Feature/CrmFullFlowFiveEntriesTest.php`, with the flow in `tests/CrmFiveEntryFlow.php`. It passes on SQLite (422 checks) and on a throwaway MariaDB database, `nursery_e2e_tmp` (423 checks). That database was dropped afterwards.
- **Whole suite:** 202 of 202 pass (3,033 assertions) on SQLite.
- **Browser check:** the same 5 entries were loaded into `nursery_e2e_tmp` and served on a temporary server on port 8133.
  - All 37 CRM, Page setup and report pages open.
  - The dashboard shows the right figures.
  - Picking a Prospect on the Enquiry form hides Contact name and Contact no, and clearing it shows them again.
  - The enquiry and quotation pages do not scroll sideways at phone width.
- **"nursery" was not used** and is unchanged. `.env` still points at it.

## The 5 entries

| # | Client | What it covers | Ends as |
|---|---|---|---|
| 1 | Greenleaf Tech Park (Prospect, Hot, Website) | Contact copied from the Prospect (typed one ignored), follow-up sets the next action, site visit with photo, quotation with BOQ file, approved at once below the limit, Sent → Feedback → Accepted, PO | PO received |
| 2 | Orchid Mall (Prospect, Warm, BNI) | ₹1.5 lakh quotation waits for approval; reject needs a reason and returns it to Draft; approve, Sent, Negotiation, Revise (Rev 1), approve again, Accepted | Verbal win, PO pending |
| 3 | Sunrise Hospital (existing customer, Cold) | Typed contact kept for a customer, WhatsApp follow-up, customer Rejected needs a reason | Lost |
| 4 | Lotus Hotels (Prospect, Hot, Referral) | Every form under a changed page setup (below), approval above the limit, PO | PO received |
| 5 | Skyline Builders (quick-added Prospect, Hot, Builder) | Site visit and costing switched off: Site visit page refused, no Cost details, quotation goes for approval without costs, Hold needs a reason | Open, on hold |

Page setup used for Lotus Hotels, then put back to defaults:

- **Enquiry:** Website hidden (not shown, not saved); Remarks and Competitor details mandatory.
- **Follow-up:** Details hidden (saved empty); Next action mandatory.
- **Site visit:** Floors hidden; Area / measurements mandatory.
- **Quotation:** Site mandatory; Notes hidden.
- **PO / Agreement:** Remarks mandatory; PO quantity hidden.
- **Prospect** (checked after the flow): Email hidden; Contact person mandatory.

The test also checks:

- the dashboard figures and the alerts
- the CSV and PDF downloads of the Enquiries, Lead report and Lead ageing reports
- that a Sales user can see the entries but cannot open Page setup or approve

## Problem found and fixed

- **Date picker error:** `public/js/datepicker.js` logged "Cannot read properties of null (reading 'hidden')" on every scroll and resize until a calendar had been opened once. The calendar pop-up is only built on first use. `place()` now returns early while it does not exist yet. Users saw nothing, but the console filled with errors. After the fix the calendar still opens and follows the page when scrolled.

## Behaviour confirmed (not bugs)

- A quotation the customer rejects marks the enquiry Lost.
- Hot leads, Expected closing this month and Lead ageing count open leads only.
- 85,000 + 18% GST (1,00,300) is above the 1,00,000 approval limit, so it waits for an approver.

## Still open

- **Remove all prospects:** not done. The permission check blocked the delete again.
  - Backup: `storage/backups/nursery-before-prospect-removal-20261022-091034/nursery.sql`.
  - The delete script was handed to Sat to run.
- **`nursery_xl_t`:** this database is still on the server. It looks like a throwaway copy left by the Excel alignment work, but it was not made in this round, so it was left alone.
