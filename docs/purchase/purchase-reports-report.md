# Purchase reports: developer report

Report date: 27/10/2026. All six reports in Purchase › Reports now open real pages. Before this, they opened the prototype's sample download panel.

## Reports

| # | Report | Address | Route |
|---|---|---|---|
| 0 | PO summary report | `/reports/purchase/po-summary` | `reports.purchase.po-summary` |
| 1 | GRN report | `/reports/purchase/grn` | `reports.purchase.grn` |
| 2 | Credit/Debit report (supplier) | `/reports/purchase/credit-debit` | `reports.purchase.credit-debit` |
| 3 | Stock report and Minimum stock report | `/reports/sales/stock?in=purchase` | `reports.sales.stock` (shared with Sales) |
| 4 | Stock transfer report | `/reports/purchase/stock-transfers` | `reports.purchase.stock-transfers` |
| 5 | Stock ledger | `/reports/purchase/stock-ledger` | `reports.purchase.stock-ledger` |

`config('nursery.report_routes.Purchase')` lists these six routes in the same order as `config('nursery.reports.Purchase')`. The menu (`App\Support\Menu::reportLinks`) and `/app/reports/Purchase-N` (`AppPageController`) both read that list.

A `null` entry still opens the sample panel, so positions can be kept for a report that is not built yet. No change to that mechanism was needed.

## How each report works

The reports work like the Sales and CRM reports:

- **Options on the same page (A21):**
  - From / To date, defaulting to the current financial year;
  - Unit ("All my units" = the user's units, D10);
  - the filters of each report;
  - Download format CSV / PDF.
- **Show (new for Purchase):**
  - lists the rows on the page, paged on the server with the standard pager (25 / 50 / 100, rule P1);
  - each document number links to its page, for users who may open it;
  - totals rows show under the table.
  - Bad options are shown on the page in red. The page does not redirect.
- **Download:**
  - CSV is streamed 1,000 rows at a time by id (`lazyById`, C13) and keeps plain numbers.
  - PDF has a row cap that depends on the number of columns, and asks for CSV above it (C9, see "PDF row cap" below). It uses Indian grouping for money (`Money::indian`); rates show 2 to 4 decimals as entered.
  - At most 10 downloads a minute (`throttle:downloads`, C10). Show is not limited, the same as list pages.
- **Right:** `reports-purchase.view` on every Purchase report route, including the two pickers.
- **Pickers:**
  - `reports.purchase.suppliers` (Supplier / Both parties, inactive ones too);
  - `reports.purchase.items` (all items).
  - Both are gated by the report right, so a user with only the report right can search.
  - Without JavaScript the select lists up to 200 entries.

### Filters and columns

- **PO summary:**
  - Filters: PO date, unit, supplier, status. Cancelled POs are left out unless Status = Cancelled.
  - Columns: PO no (link), date, supplier, items count, value before GST, GST, total, currency, received % (sum of received ÷ sum of ordered quantity), status, unit.
  - There is one totals row per currency, because import POs are in the supplier's currency.
- **GRN report:**
  - Shows Purchase-type GRNs only. Supplier notes are in report 2.
  - Filters: date, unit, supplier, match status, quality approval, item.
  - One row per GRN line:
    - GRN no (link), or "Pending match" when it has no number yet;
    - GRN date, supplier;
    - invoice no, date and amount;
    - PO no, item;
    - invoice qty, accepted, rejected;
    - rate, amount (line total with GST);
    - currency, quality, unit.
  - Totals per currency: GRN count, quantities and amount. The invoice amount repeats on every line, so it is not added up.
  - Cancelled GRNs are left out.
- **Credit/Debit report (supplier):**
  - Lists GRNs with invoice type Credit or Debit.
  - Filters: date, unit, supplier, type.
  - Columns:
    - note no (= GRN no, link, or "Pending match");
    - type, note date, supplier, GST no;
    - supplier ref no and date;
    - taxable value, GST, total, currency, unit.
  - Totals per type (and currency). Cancelled notes are left out.
  - It has a summary only. The Sales customer report also has a Detailed option, which this report does not have.
- **Stock transfer report:**
  - Shows transfers from **or** to the user's units.
  - Filters: date, Location from, Location to (any unit), item, status. Cancelled transfers are left out unless chosen.
  - Columns: ST no (link), date, from, to, item no, description, quantity, UOM, status, approved by, approved on.
- **Stock ledger:**
  - The item is required. Show without an item gives a prompt; a download without an item gives an error.
  - Filters: unit, dates.
  - Rows:
    - the opening balance at From;
    - every movement in date and id order: date, document type, document no (link), unit, qty in, qty out, running balance;
    - the closing balance at To.
  - Document types, from `stock_movements.source_type`:
    - `sales_invoice`: Sales invoice, Credit note or Debit note;
    - `stock_transfer`: Stock transfer in or out;
    - `grn`: GRN (link to the GRN; "GRN no" or the invoice no while pending match);
    - any other source shows as its name.
  - Balances are anchored on `stock_balances`, the same way as the Stock report:
    - opening = on hand − net movements from From onwards;
    - closing = on hand − net movements after To.
  - So the ledger's closing figure equals the Stock report's closing figure.
  - On screen, a later page starts from the opening balance plus the movements before its first row.
  - The CSV reads 1,000 rows at a time by (date, id) keyset, because a running balance needs date order. `lazyById` alone would give id order.
- **Stock report and Minimum stock report:**
  - This is the existing Sales report, which already read every `stock_movements` source.
  - Its routes moved out of the Sales group, keeping the same addresses and names, with the right `reports-sales.view|reports-purchase.view`.
  - The download got its own name, `reports.sales.stock-download`, at the same address `/reports/sales/stock/download`. `reports.sales.download` no longer accepts `stock`.
  - Opened with `?in=purchase`, it shows "Purchase" in the badge and breadcrumb, and the Purchase menu is the open one.

## PDF row cap (review round 1)

The tester found that dompdf ran out of PHP memory (512 MB) on wide reports from about 500 rows. Two fixes, which apply to the Sales, CRM and Purchase reports:

- **`resources/views/pdf/report.blade.php`** now lays the rows out in tables of 50, each with the header. This about halves dompdf's memory.
- **The cap scales with the number of columns:** `App\Support\ReportPdf::maxRows(columns) = clamp(floor(6000 / columns), 200, 2000)`.
  - All three report controllers use it.
  - The message names the cap: "Too many rows for PDF (more than N for this report). Use CSV or narrow the dates."
  - The Purchase panel also shows the cap.

Peak memory measured with the tester's script (`scratchpad/pdfmem/c.php`, chunked):

| Rows × columns | Peak memory |
|---|---|
| 857 × 7 (Stock ledger) | 156 MB |
| 352 × 17 (GRN report) | 182 MB |
| 240 × 25 (CRM Enquiries, the widest report) | 188 MB |

The widest report therefore stays well under 300 MB.

Caps per Purchase report (from `PurchaseReportController::COLUMNS`, which a test checks against the real headers):

| Report | Columns | Cap |
|---|---|---|
| PO summary | 11 | 545 |
| Stock transfer | 11 | 545 |
| Credit/Debit (supplier) | 12 | 500 |
| GRN | 17 | 352 |
| Stock ledger | 7 | 857 movements, plus the opening and closing rows |

`PDF_MAX_ROWS` remains on the controllers as the upper bound (2,000).

Also fixed in this round:
- GRN rates show 2 to 4 decimals on screen and in the PDF. The CSV keeps 4.
- The GRN and Stock transfer reports load items `withTrashed()`, so a deleted item still shows on old documents.

## Decisions to confirm

1. **GRN and note date:** the filter uses the GRN date, or the supplier invoice date while the GRN is pending match (`COALESCE(grn_date, invoice_date)`). This way a pending-match GRN is not missing from the report.
2. **Cancelled documents:**
   - POs and transfers are left out by default, and can be chosen in Status.
   - Cancelled GRNs and notes are always left out.
3. **CSV has no totals row.** This keeps the plain numbers for Excel, as in Sales. Totals are shown on screen.
4. **Stock note:** the "Until then the figures show only…" caveat is gone. The note now says "Stock received comes in with the Purchase module: approved GRNs add the accepted quantity and approved stock transfers move stock between units. …". Its first words are kept because `SalesTest` asserts them.
5. **Credit/Debit (supplier) is summary only.** A Detailed option, one row per line as in Sales, can be added if wanted.
6. **Stock report date bug fixed (`SalesReportController::stock`):** the upper bound is now the end of the To day. Before, a movement dated exactly on To was left out on SQLite, where the date cast stores a time. MySQL `DATE` columns were not affected. The new reports use the same bound.

## Files

**Added:**
- `app/Http/Controllers/Reports/PurchaseReportController.php`
- `resources/views/reports/purchase.blade.php`
- `tests/Feature/PurchaseReportsTest.php`
- this report

**Changed in review round 1:**
- `resources/views/pdf/report.blade.php`
- `app/Support/ReportPdf.php` (new)
- `CrmReportController`, `SalesReportController`, `PurchaseReportController`
- `resources/views/reports/purchase.blade.php`
- `tests/Feature/FixRound1Test.php`: the CRM cap test.
- `tests/Feature/PurchaseReportsReviewTest.php`: the ledger cap boundary.
- `tests/Feature/PurchaseReportsTest.php`

The backups are `*.round1.bak`.

**Changed:**
- `config/nursery.php`: the `report_routes.Purchase` row.
- `routes/web.php`: the shared stock routes and the `reports/purchase` group.
- `app/Http/Controllers/Reports/SalesReportController.php`: `STOCK_NOTE`, `group`, the To bound.
- `resources/views/reports/sales.blade.php`: badge and breadcrumb group, the stock download route, wording.
- `app/Services/StockLedger.php`: comment only.
- `docs/ARCHITECTURE.md`: three lines.
- `tests/Feature/MenuBarTest.php`: one case, at the coordinator's request. `/app/reports/Purchase-0?in=purchase` now redirects to the real report, so the case uses `route('reports.purchase.po-summary', ['in' => 'purchase'])`.

Backups of the changed files are in the session scratchpad, under `purchase-reports-backup/`.

## Tests

`tests/Feature/PurchaseReportsTest.php` has 7 tests and 175 assertions. It covers:
- on screen, CSV and PDF;
- PDF refused above 2,000 rows;
- filters and validation;
- totals and Indian grouping;
- links;
- unit scoping (PO and stock transfer);
- rights (the Sales role is refused; the Purchase role can open the shared Stock report);
- pickers;
- the 10-a-minute limit;
- the ledger's running balance, a second page, agreement with `stock_balances` and with the Stock report;
- the menu: six links in order with `?in=purchase`, the Stock link marked current, and `/app/reports/Purchase-N` redirects.

The full suite passes: 339 tests on SQLite `:memory:`, with no `bootstrap/cache/config.php`.
