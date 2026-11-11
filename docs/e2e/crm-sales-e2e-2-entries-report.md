# CRM and Sales end-to-end test with 2 entries (03/11/2026)

Paths are relative to `D:\xampp\htdocs\nursery-app`.

## Summary

- **Result:** every step passed for both entries, in the automated test and in the browser. One problem turned up in the browser and was fixed (see "Problem found and fixed").
- **Automated test:** `tests/Feature/CrmSalesEndToEndTwoEntriesTest.php`, 2 tests, 289 checks, all passing. It runs on a temporary in-memory test database only.
- **Whole suite:** 398 passed, 1 skipped, 0 failed (8,654 checks). Before this round it was 396 passed and 1 skipped; the 2 new tests account for the difference.
- **Browser check:** done on a throwaway copy of the database, `nursery_e2e_check`, served on port 8001.
  - Both entries were taken from Prospect / Enquiry to credit note.
  - The copy was deleted afterwards, and the 9 test files it uploaded were removed from `storage/app/private/documents`.
  - The company GSTIN was set in the copy only.
- **Dev database:** "nursery" was only read, to make the copy. Nothing was written to it, `.env` was not changed, and MySQL was already running (not started or stopped by me).

| | Steps checked | Pass | Fail |
|---|---|---|---|
| CRM, entry 1 and entry 2 | 11 steps × 2 | 22 | 0 |
| Sales, entry 1 and entry 2 | 10 steps × 2 | 20 | 0 |
| Extra Sales checks (date lock, type drop-down, credit note) | 5 | 5 | 0 |

## The 2 entries

| # | Customer | State | What it covers |
|---|---|---|---|
| 1 | Greenfield Interiors Pvt Ltd, Pune | Maharashtra, the company's own state | Starts as a **Prospect**. Is then added to the Party master and the enquiry is moved to the customer. Quotation below the 1,00,000 limit, so it is approved at once. Quotation marked sent by hand. Invoice made by the **Sales user**: CGST + SGST, with P&F charges. **Credit note** against this invoice. |
| 2 | Bangalore Botanica LLP, Bengaluru | Karnataka, another state | **Existing customer**. Quotation above the limit, so it waits in 4 Approval and Admin approves it. Quotation sent by email. Invoice made by **Admin** with yesterday's date: IGST. |

## CRM flow (CRM menu, in order)

| Step | Entry 1 (Greenfield) | Entry 2 (Botanica) |
|---|---|---|
| Prospect | Pass. Prospect saved. | Not needed (already a customer) |
| 1 Enquiry / Lead | Pass. Contact name and number copied from the prospect. Enquiry no ENQ/26-27/…. | Pass. Typed contact kept for the customer. |
| Follow-up | Pass. Next action becomes "Site visit". | Pass. WhatsApp follow-up; next action "Measure the wall". |
| Prospect → customer | Pass. Customer added to the Party master and the enquiry moved to it. | Not needed |
| 2 Site visit + photo | Pass. Visit saved, site photo uploaded and listed. | Pass |
| 3 BOQ (upload) | Pass. BOQ PDF uploaded on the quotation. | Pass |
| 3 Costing (cost details) | Pass. Cost 36,000 + 25% margin = 45,000 proposed price. | Pass. Cost 85,000 + 20% = 1,02,000. |
| 3 Quotation | Pass. 2 lines (18% and 12% GST). Total 53,260. | Pass. 2 lines at 18%. Total 1,26,260. |
| 4 Approval | Pass. Below the limit, so approved at once. | Pass. Shown in the 4 Approval list. The Sales user is refused, Admin approves. |
| 5 Quotation sent + customer status | Pass. Sent by hand, then Feedback, then Accepted. Shown in the 5 Quotation sent list. | Pass. Sent by email (queued), then Negotiation, then Accepted. |
| 6 PO / Agreement: Part master items | Pass. 2 items chosen from the customer's parts only. A blank rate takes the item price. PO value = item total 46,000, read-only on the form. | Pass. 2 items; PO value 1,07,000. |
| 6 PO / Agreement: uploads | Pass. PO PDF + signed JPG chosen with the new PO ("saved with 2 documents"). Agreement PDF uploaded afterwards. All 3 listed on the PO. | Pass. Agreement PDF with the PO, stamp JPG afterwards. |
| Create Sales Order from the PO | Pass. Draft SO/26-27/0001 with the customer, PO no, end date, items and rates. The PO then shows "Open sales order", and a second order from the same PO is refused. | Pass. Draft SO/26-27/0002, total 1,07,000. |

Also checked:
- Enquiry status becomes "PO received" after the PO.
- The PO list and the enquiry list show both entries.
- The Lead report CSV carries both customers and the PO no.

## Sales flow

| Step | Entry 1 (Greenfield, same state) | Entry 2 (Botanica, other state) |
|---|---|---|
| Sales order: open and check | Pass. "From PO" link to the CRM PO. The Sales user edited and saved it. | Pass |
| Send for approval (Sales user) | Pass. Status "Pending approval". The confirmation box shows first. | Pass |
| Admin approves | Pass. The Sales user is refused; Admin approves. | Pass |
| Tax invoice: PO no drop-down fills | Pass. After picking the party, the drop-down lists only "PO GFI/PO/2026/118 · SO/26-27/0001". | Pass. Only "PO BB/WO/7781 · SO/26-27/0002". |
| Lines with balances | Pass. Both parts appear with rate from the order (read-only) and "Max 20". | Pass. "Max 10" and "Max 2". |
| Live totals on screen = saved totals | Pass. 34,900 / 2,631 / 2,631 / 40,162. | Pass. 1,01,000 / IGST 18,180 / 1,19,180. |
| More than the PO balance refused | Pass. "only 8 is left on the PO". | Pass. Future date refused for Admin too. |
| Generate PDF | Pass. Real PDF; shows TAX INVOICE, CGST columns, Maharashtra (27), customer GSTIN, PO no, grand total. | Pass. Shows IGST columns (no CGST), Karnataka (29), PO no. |
| Download CSV | Pass. One row per invoice line, with totals and supply type. | Pass |
| Invoice list | Pass. All 3 documents. The "Credit notes" filter shows only the credit note. | Pass |
| Sales reports | Pass. Sales order report (both SOs and POs), Sales report (both invoices, no credit note), Export report (none), Credit/Debit report (the credit note). CSV and PDF downloads work. | Pass (same reports) |

Extra checks:

| Check | Result |
|---|---|
| Invoice date for a Sales user | Pass. Read-only, today, with the hint "Only an Admin can change it". A past date sent anyway is saved as today. |
| Invoice date for Admin | Pass. Can be changed. 02/11/2026 was kept; a future date is refused. |
| Invoice type | Pass. A drop-down with Sales / Credit / Debit. |
| Credit note against entry 1's invoice | Pass. CN/26-27/0001 for 2 palms. Trying to credit 13 (more than the 12 billed) is refused. |
| Phone width | Pass. The invoice, sales order and CRM PO pages do not scroll sideways at 375 px. |

## Key figures checked

| Document | Taxable value | CGST | SGST | IGST | Grand total |
|---|---|---|---|---|---|
| Quotation 1 (Greenfield) | 20 × 1,450 + 20 × 850 = 46,000 | | | | 53,260 (incl. 18% / 12% GST) |
| Quotation 2 (Botanica) | 10 × 9,500 + 2 × 6,000 = 1,07,000 | | | | 1,26,260 (incl. 18%) |
| CRM PO 1 / Sales order 1 | 46,000 | | | | |
| CRM PO 2 / Sales order 2 | 1,07,000 | | | | |
| Invoice 1: 12 palms @ 1,450 (18%) + 20 planters @ 850 (12%) + P&F 500 (taxed at 18%) | 34,900 | 2,631 | 2,631 | 0 | **40,162** |
| Invoice 2: 10 modules @ 9,500 + 1 drip kit @ 6,000 (18%) | 1,01,000 | 0 | 0 | 18,180 | **1,19,180** |
| Credit note: 2 palms @ 1,450 (18%) | 2,900 | 261 | 261 | 0 | **3,422** |

PO balances after billing:
- Entry 1: palms 8 left, planters 0.
- Entry 2: modules 0 left, drip kits 1.

The browser run on the database copy gave exactly the same figures.

## Problem found and fixed

**A part of the PO left empty on the invoice page blocked the save.**

- **Where:** `app/Http/Requests/Sales/SalesInvoiceRequest.php:40-44`
- **What happened:** when you bill against a PO, the invoice page lists every part of the order with an empty quantity and no remove button. If you filled in only some parts, saving failed with "Item 2: enter the quantity." So you could not:
  - make a credit note for just the returned palms without also crediting the planters, or
  - invoice only part of a PO's parts.
- **Fix:** a PO part left empty is now skipped. These still behave as before:
  - leaving every part empty still says "Add at least one item with a quantity";
  - a typed quantity is still checked against the PO balance.
- **Test:** `test_a_po_part_left_empty_on_the_invoice_page_is_skipped` in the new test file. It fails without the fix and passes with it.

The automated tests did not catch this because they post only the lines they want. The browser posts every line, as a real user's page does.

## Open questions (design choices, not changed)

1. **Which invoice does the credit note belong to?** It is linked to the PO / sales order, not to a particular invoice number. It is checked against "invoiced less already credited" on the PO, and the credit note does not say which invoice it reverses. Should it record (and print) the invoice it is against?
2. **Credit note and PO balance.** After crediting 2 palms, the PO still shows only 8 palms left to bill. The returned 2 are not added back. Is that right, or should returned goods be billable again?
3. **PO value: with or without GST?** On a new CRM PO the value is first filled with the quotation total including GST (53,260). As soon as items are added it becomes the item total without GST (46,000). Which should the PO value be?
4. **PO items are not copied from the quotation.** The quotation lines have to be picked again from the Part master on the PO. Should "6 PO / Agreement" offer the quotation's lines?
5. **No "convert prospect to customer".** For entry 1, the customer had to be added separately in the Party master and the enquiry changed by hand. The prospect record stays as it is. "Create Sales Order" only works once the enquiry is for a Party master customer.
6. **On the invoice page, parts of a PO have no remove button.** With the fix, leaving a part empty skips it. A short hint such as "Leave a part empty to skip it" may help users.

## Not covered

- Actual email delivery (the quotation email was only checked as queued).
- Debit notes, cancelling an invoice, export (foreign currency) customers, and the stock reports.
- Quotation revisions, internal reject, Page setup changes, imports, the dashboard and the Accounts menu (covered by earlier test rounds).
- How the PDF looks: its content and that it opens were checked, but it was not compared by eye.
- In the browser, these steps were done on the actual page forms:
  - prospect and enquiry;
  - CRM PO with items and files;
  - Create Sales Order;
  - quotation approval;
  - sending the sales order for approval;
  - all three invoices.

  The other steps were sent from the signed-in browser to the same app addresses the pages use.
