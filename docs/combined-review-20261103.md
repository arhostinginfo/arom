# Combined review of the 02/11/2026 changes (03/11/2026)

Tester's report. It covers three changes made at the same time by different developers:

- **Separate PO menu removed.** CRM › 6 PO / Agreement is kept, with its Part Master lines and Create Sales Order.
- **Tax invoice fixes.** Invoice date, PO no drop-down, PDF buttons, standard GST PDF and the Invoice type drop-down.
- **Radio groups to drop-downs.**

Paths are relative to `D:\xampp\htdocs\nursery-app`. Before any change, the files were backed up to
`C:\Users\ADMIN\AppData\Local\Temp\claude\D--xampp-htdocs-nursery-app\1b9abdf8-b24e-4544-b984-62f55bc97d60\scratchpad\combined-tester-backup\`,
with the same relative paths.

## What was checked

- **Merge damage in the shared files:**
  - Shared files checked: `routes/web.php`, `sales/page.blade.php`, `sales.js`, `config/nursery.php`, `SalesPage`,
    `SalesOrderService` and the tests.
  - Each was diffed against the four backups. No edit was lost and no block was duplicated.
  - Every PHP file passes `php -l`.
  - Every PHP, JS, CSS and Markdown file in `app`, `config`, `routes`, `resources`, `public/js`, `public/css`, `tests`,
    `database` and `docs` was scanned. All are valid UTF-8, with no BOM, NUL byte or conflict marker.
  - All 10 `public/js/*.js` files parse. They were checked with `vm.Script`, using the Electron runtime bundled with VS
    Code as Node.
- **Leftovers:** no `po.*` route, `PoContext`, `SeparatePo`, `Controllers\Po`, `views/po` or `'po'` module is left.
  Only comments that record the removal remain.
- **Regression:**
  - The full chain: CRM enquiry → quotation → sent → CRM PO with items → enquiry PO received → Create Sales Order →
    Draft SO with the PO no and lines → submit → approve → Tax invoice PO list → po-lines balances → save → PDF
    (200, application/pdf) → cancel (the balance comes back, and the PDF says CANCELLED).
  - Menu, dashboard and redirects.
  - Admin approval selects.
  - The other modules (sales order import and export, credit and debit notes, Purchase PO, GRN, stock transfer,
    reports) are covered by their existing suites, which all pass.
- **PDF:** inside dompdf, every drawn text was recorded with its page and position (render callbacks). The PDF was
  checked this way for:
  - an intra-state invoice, an inter-state invoice and an export invoice;
  - a credit note and a debit note;
  - a 60-line invoice with a JPG logo and a long address;
  - a company with no GSTIN, PAN, address or contact details;
  - parties with no address or GSTIN, and no transporter;
  - a PNG logo (GD is off) and a missing logo file.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| 1 | Medium | **HSN summary showed a wrong GST rate.** The PDF grouped the summary by HSN code only. Parts of the same HSN can have different rates (the rate comes from the part), so one row showed the first part's rate (e.g. 9%) beside tax added up at 9% and 6%. | **Fixed:** one row per HSN and rate (`resources/views/pdf/sales-invoice.blade.php`). |
| 2 | Medium | **PDF header ran into the body.** With a logo and a long company address, the fixed header ended at 178.6 pt, but the body starts at 172 pt. Its second row overlapped "Bill to" on page 1 and the repeated item head on later pages. | **Fixed:** top margin 192 pt and header 168 pt (were 172 pt / 148 pt). The company address in the header is cut at 120 characters (was 160). A test checks this per page. |
| 3 | Low | **Stale hint on a prospect's PO.** A PO with no Party customer said "…choose it as the PO's customer". The PO form has had no Customer field since the PO menu was removed; the customer comes from the quotation. | **Fixed:** "Add this client to the Party master first. The PO takes its customer from its quotation." (`app/Services/SalesOrderService.php`). |
| 4 | Info | **Earlier flaky upload and PDF tests.** The likely cause is three developers running the suite at the same time in one folder. `Storage::fake('local')` always uses `storage/framework/testing/disks/local` and empties it at the start of each test, so concurrent runs delete each other's files. The PDF memory check uses the process-wide peak, so it also depends on other load. Run alone, both full runs below were green with no flakes. | **Open, no code change.** Run one suite at a time. |
| 5 | Info | The guest `curl` checks wrote framework session rows to the dev database, because `SESSION_DRIVER=database`. No application data was written. | Noted |
| 6 | Info | `resources/views/pdf/quotation.blade.php` has mixed CRLF / LF line endings. This was there before and is unrelated to these changes. | Open |
| 7 | Low (docs) | Two developer reports still quote the old hint from finding 3: `docs/sales/po-to-sales-order-report.md` and `docs/crm-changes/direct-customer-po-report.md`. The second also says the query is kept for `/po/{id}`; the code keeps it for `/po/create` only, and `/po/{id}` drops it. | Open (docs only) |

Checked with no problem found:

- **Invoice date:**
  - Users who are not Admin cannot set any date. A new invoice is dated today, whether the browser sends a malformed,
    future, past or empty date. An edit keeps the saved date. The field is read-only.
  - An Admin can set a past date in the invoice's year. A future date or another year is refused, and the refused value
    is shown again.
- **PO no drop-down:** the empty state and clearing the party.
- **`$open` leak:** fixed. The PDF and CSV controls are links on every Sales page, and the sales order Import still
  opens its dialog.
- **GSTIN warning:** shown as described.
- **Radio groups:** none is left in the views or scripts, and the defaults are kept (Party, Item, User and Employee
  start Active / Customer / Sales). GRN and stock-transfer JS read the selects.
- **Old PO links:**
  - `/po` and `/po/list` give 404. `/po/{id}` and `/po/create` redirect 301 to the CRM page.
  - There is no open redirect: the target is always this app's CRM URL.
  - A guest goes to the login page first.

## Design questions (not changed)

1. **A prospect's PO cannot get a customer.** It keeps `party_id` null even after the client is added to the Party
   master, because the party is not passed on to existing quotations. Only a new enquiry and quotation fix it. Should
   there be a way to link the PO to the new party?
2. Place of supply for an unregistered customer (no GSTIN) prints the company's state. Should the party have a state
   field?
3. These points are still open from the invoice report: bank fields in the Company master, rounding to the rupee,
   Duplicate / Triplicate copies, PNG logo without GD (JPG only, GD left off) and future-dated invoices for Admin.
4. A long company address is cut at 120 characters in the PDF header (the footer cuts at 110). Should there be a short
   "address for documents" field?

## Tests added

`tests/Feature/CombinedChangesReviewTest.php` has 10 tests and 313 assertions:

- PO menu gone:
  - no `po.*` route, PO views or classes;
  - 404s for the old pages;
  - 301 redirects with no open redirect;
  - guest goes to login;
  - the menu has "6 PO / Agreement".
- The end-to-end chain CRM PO → SO → invoice → PDF → cancel, including the PO line balances and the forced date.
- The prospect PO hint (finding 3).
- Invoice date rules on create and edit, for non-Admin and Admin.
- The PDF per page:
  - header and footer on every page, and the item head repeated;
  - "Bill to" and transport on page 1 only;
  - totals, GST, amount in words, declaration and signatory once, on the last page;
  - no overlap with the header or footer (finding 2);
  - 60 rows;
  - the page count matches the download.
- The HSN summary per rate (finding 1).
- The PDF without optional data, for intra-state, inter-state, export, credit and debit, with a PNG logo, no logo and a
  missing logo.
- Sales page: PDF and CSV are links, and the Import dialog button is kept.
- Source files are clean UTF-8, with no conflict markers, PO-menu leftovers or radio groups.
- Purchase order approval rule select saves.

The finding 1 and 2 tests were run against the original template, and both failed there.

## Test runs (SQLite `:memory:`, `bootstrap/cache/config.php` absent)

- Before changes: `Tests: 1 skipped, 386 passed (8052 assertions)`
- Run 1: `Tests:    1 skipped, 396 passed (8365 assertions)`, Duration 220.49s
- Run 2: `Tests:    1 skipped, 396 passed (8365 assertions)`, Duration 213.34s

The skipped test is "write sample pdf". It needs `TAX_INVOICE_SAMPLE_PDF=1`.

Other checks:

- `artisan view:clear` was run.
- `migrate:status`: nothing pending (last migration batch 19).
- `route:list --name=po.`: no routes. Only the two unnamed redirects `po/create` and `po/{id}` exist.
- `curl`:
  - `/nursery-app/crm/pos` gives 302 to the login page.
  - `/nursery-app/sales/invoices` gives 302 to the login page.
  - `/nursery-app/po` and `/nursery-app/po/list` give 404.
  - `/nursery-app/po/1` gives 302 to the login page.

## Human browser checks needed

1. **Tax invoice PDF** with the real company logo (JPG) and address. Check by eye that the header, the "Page X of Y"
   at the top right and the footer do not overlap, on a 1-page invoice and a 40+ line invoice.
2. **An invoice with two parts that share an HSN but have different GST rates.** The HSN summary should show two rows.
3. **Sales › Tax invoice as a Sales user.** The date is greyed. Invoice type is a drop-down. Choosing and clearing the
   party fills and empties PO no. The PDF icon downloads the file.
4. **Admin on the same page.** Change the date to a past date in the year; a future date is refused.
5. **CRM › 6 PO / Agreement** on a party PO with items: Create Sales Order opens the Draft order. A prospect's PO shows
   the new hint.
6. **The converted drop-downs, at phone width:**
   - Admin › Approval: all four rules.
   - Party, Item, User and Employee forms.
   - GRN invoice type, and the quality Approve / Reject buttons, which pre-select Result.
   - Stock transfer source type.
   - Reports: Sales, Purchase and CRM formats.
7. **The top bar** has no PO menu. `/nursery-app/po/1` opens the CRM PO page.
