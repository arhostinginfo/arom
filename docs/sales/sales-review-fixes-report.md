# Sales review fixes and screen changes (18/10/2026)

Developer report. It covers the fixes from the four Sales reviews (`architecture-review-sales.md`, `database-review-sales.md`, `code-review-sales.md` and `ux-review-sales.md`) and Sat's screen requests of 18/10/2026. Paths are relative to `D:\xampp\htdocs\nursery-app`.

## Database: backups and migrations

| When | Backup taken first | Migration run on "nursery" |
|---|---|---|
| 18/10, before the picker indexes | `storage/backups/nursery-before-picker-indexes-20261018-104259.sql` | `2026_10_18_000001_add_picker_indexes` (indexes only) |
| 18/10, before the Sales fixes | `storage/backups/nursery-before-sales-review-fixes-20261018-111412.sql` | `2026_10_18_000002_sales_review_fixes` (additive) |

- **`2026_10_18_000001_add_picker_indexes`** adds these indexes:
  - `parties (status, deleted_at, name)`
  - `prospects (deleted_at, name)`
  - `cities (is_active, name)`
  - `employees (status, deleted_at, name)`
- **`2026_10_18_000002_sales_review_fixes`** adds:
  - the column `sales_order_lines.credited_quantity`, filled from the saved credit notes;
  - a new table `stock_balances (unit_id, item_id, quantity)` with primary key (unit_id, item_id), filled from the stock ledger;
  - the index `sales_invoices (unit_id, invoice_type, invoice_date)`;
  - the index `sales_orders (party_id, po_number)`.
- Both migrations roll back cleanly. Before "nursery", they were tried on a throwaway copy, which has now been deleted.
- MySQL and `.env` are as found: `.env` still points at "nursery", and MySQL is still running.

## Tests

- **145 of 145 pass** (2,014 assertions) on SQLite in memory.
- The same 145 pass on MariaDB, run on a throwaway database (`nursery_test_tmp`) that was dropped afterwards.
- New tests:
  - `tests/Feature/UiBatchTest.php`: paged lookups, menu fly-outs, the list Filters panel, master hints and the date picker script.
  - Three new tests in `tests/Feature/SalesTest.php`: credit limits, stock balance and stock report, and the company GSTIN, currency, own-year, PO-number and party-info rules.
  - Existing Sales tests were updated where the new rules now refuse what they used to allow:
    - a rate for EUR customers;
    - Admin-only PO upload on an approved order;
    - no credit note without an invoice;
    - cancelling a credit note before its invoice.

## Must-fix items

| Review | Item | Fix | Files |
|---|---|---|---|
| Code M1 / DB SL-M1 | Credit notes could be issued again and again, and each put stock back | With a PO, a credit note may give back at most invoiced − already credited on that PO line. The check runs under the line's row lock and `credited_quantity` is updated; edit and cancel give it back. Without a PO, the limit per item is the customer's active tax invoices of that unit and year − earlier credit notes. The party row is locked so two notes queue. A tax invoice cannot be lowered or cancelled below what credit notes gave back. Messages name the item. Debit notes are value-only and keep their earlier limit. | `app/Services/SalesInvoiceService.php`, `app/Models/SalesOrderLine.php` (`creditRoom()`), migration 2 |
| Arch M2 / DB SL-M3 | Stock report re-read the whole ledger for every 1,000 items | `stock_balances` is updated in the same transaction as every ledger row through `App\Services\StockLedger` (one increment statement per item, in item order). The Stock report reads items 1,000 at a time; per batch it reads the balance plus only that batch's movements from From onward. | `app/Services/StockLedger.php` (new), `app/Http/Controllers/Reports/SalesReportController.php` |
| Arch M1 | Report PDFs read the whole report before the 2,000-row check | Rows are taken from the lazy collection, so at most 2,001 are read before a too-large PDF is refused. | `SalesReportController.php` |
| DB SL-M2 / Code S2 | Editing an invoice could move its date into another financial year | On edit, the date is checked against the invoice's own year, in both the Form Request and the service. | `app/Http/Requests/Sales/SalesInvoiceRequest.php`, `SalesInvoiceService.php`, `app/Models/SalesInvoice.php` (`financialYear()`) |
| Code M2 | With no company GSTIN, every invoice got CGST + SGST and had no supplier GSTIN | No invoice, credit note or debit note can be saved until the company GSTIN is entered. The message points to Admin › Masters › Company. The PDF already prints the GSTIN. | `SalesInvoiceService.php` |
| Code M3 | A blank rate ignored the item's currency | The Item master price is used only when the item is priced in the party's currency; otherwise the user is asked: "Item 2 (…) is priced in INR. Enter the rate in EUR." This applies to invoices, orders and the order import. | `SalesInvoiceService.php`, `app/Services/SalesOrderService.php`, `app/Services/SalesOrderImport.php` |
| Code M4 | Sales staff could replace the customer PO file on an Approved order | For a sales order, `DocumentService::canChange` uses `isEditableBy()`, so a Pending or Approved order's documents can only be changed by an Admin. The documents box follows the same rule. | `app/Services/DocumentService.php`, `resources/views/sales/page.blade.php` |
| Code (items left for later) | The Stock report should say that stock coming in arrives with Purchase | This note is on the Stock report panel and its PDF. | `SalesReportController.php` (`STOCK_NOTE`), `resources/views/reports/sales.blade.php`, `resources/views/pdf/report.blade.php` |

## UX must-fix items (Sales page)

| Item | Fix | Files |
|---|---|---|
| M1: GST and grand total only after save | A totals box shows taxable value, CGST + SGST or IGST (or Nil for export), P&F and the grand total while you type. It uses the same rounding as the server and says "Worked out again on save". Item, PO-line and party data now carry GST rates, the currency and the supply type. | `public/js/sales.js`, `page.blade.php`, `SalesInvoiceController.php` (`itemData()`, `parties()`, `partyInfo()`), `SalesOrderController.php` (`poLines()`), `public/js/picker.js` (copies `data` onto the option) |
| M2: PO quantities prefilled | Quantities start empty, with "Max 1,000" as the hint. A "Fill every PO balance" button is offered for tax invoices only. | `sales.js`, `page.blade.php` |
| M3: Fully billed lines, unclear messages | A line with nothing left shows as text with no box: "fully billed", "nothing left to credit" or "nothing invoiced yet". Every line message names the row and item no and shows under the row. | `sales.js`, `SalesInvoiceRequest.php`, `SalesInvoiceService.php` |
| M4: Failed save lost typed quantities | After an error the rows are rebuilt from the submitted JSON, with their limits, and the PO is not reloaded. Done for both the invoice and the order card. | `page.blade.php`, `sales.js` |
| M5: Changing type, PO or party wiped rows | Changing the party or PO asks "Replace the N items you typed …?" and puts the old choice back if you say no. Changing the type keeps typed quantities and updates the limits. Invoice type now comes first (UX S9). | `sales.js`, `page.blade.php` |
| M6: Phone sideways scroll and the Sales order 2,400 px down | Both item tables are cards at 640 px or less, with no sideways scroll at 375 px. A "Sales order" button in the page header opens that card and focuses it. The invoice card stays on top. | `page.blade.php`, `public/css/app.css` |

## Suggestions

| Review | Suggestion | Done? |
|---|---|---|
| Code S1 | Approver without Sales order View sees an empty page | Done: the order card shows read-only, with Approve and Reject, for `sales-order-approval.view`. |
| Code S2 | Edit checks the wrong year | Done (see must-fix SL-M2). |
| Code S3 / DB SL-S2 | Same PO number saved twice at once | Done: the party row is locked FOR UPDATE and the PO number is checked again inside the save. A new index backs the check. |
| Code S4 | `Party` used `$guarded` | Done: `$fillable = Party::FIELDS`. |
| Code S5 | Party GST no can be hidden in Page setup | Kept as it is. With the company GSTIN required, a customer without a GSTIN is billed as in the company's state, as before. |
| Code S6 | Stock report subquery | Done (see must-fix Arch M2). |
| Code S7 | `partyInfo` answered for suppliers | Done: customers only (404 otherwise). |
| Code S8 | Name the order-line rate and the Transporter master as decisions | Recorded in `docs/ARCHITECTURE.md`. |
| Code S9 | Tests | Done (see Tests above). |
| DB SL-S1 | Append-only ledger with reversal rows | Not taken now. The balance table follows edits and cancels correctly; reversal rows can come with Purchase. Recorded. |
| DB SL-S3 | Rate of an invoiced order line could change | Done: refused while anything is invoiced on the line. |
| DB SL-S4 / Arch S1 | Invoice number lock held for the whole save | Done: a placeholder is used and the number is taken at the end, inside the transaction. |
| DB SL-S5 | Item no unique per party | Not taken: the item no is the company's part number, and the customer's number is the IPN. Recorded. |
| DB SL-S6 | Index for the reports | Done. |
| DB SL-S7 | HSN summary from the saved lines | Already so in `pdf/sales-invoice.blade.php`. |
| DB SL-S8 | `docs/DATABASE.md` old Sales plan | Done: replaced with a pointer, and the new section was added. |
| Arch S2, S3 | Item number counter; imports loading whole masters | Not taken now; kept for the item import work. Recorded. |
| Arch S4, S5, S6 | Lazy closed card, transporter type-ahead, caching `Company::current()` | Not taken: small lists and single-row reads today. Recorded. |
| Arch S7 | Write the stock, series, approval and page-setup contract | Done in `docs/ARCHITECTURE.md`. |
| UX S1 | Sales order card above the invoice | Kept below, as Sat asked ("keep the invoice card on top"), with the header button instead. |
| UX S2 | Hide / Show label never changed | Done (CSS on `details[open]`). |
| UX S3 | Focus when the order card opens from the menu | Done. |
| UX S4 | Order amount while typing | Done: the item price shows as the rate hint, and line amounts and the order total update live. |
| UX S5 | Locked rate looked editable | Done: read-only style plus a visible "From the sales order". |
| UX S6 | Bell entry for orders waiting for approval | Not taken now. Recorded. |
| UX S7 | Currency on the invoice list | Done. |
| UX S8 | Tabs dropped the search | Done on the invoice and order lists. |
| UX S9 | Invoice type first | Done. |
| UX S10 | "(OTHER POINTS A24)" in the audit line | Removed. |

## Sat's screen requests (18/10/2026)

| Request | What was built | Files |
|---|---|---|
| Server dropdowns slow; "load like select with search" | One select-with-search component for every server-fed dropdown in CRM, Sales and Admin. Clicking opens the list at once with a search box and a status line ("Loading…", "No matches", "Scroll for more"). It shows the first 20, searches on the server as you type, and loads more as you scroll. The first page is fetched when the pointer rests on the field and kept for 60 s. It works by keyboard and on phones. Every lookup endpoint takes `?q=&page=`. The prospect search looks at phone numbers only for numeric input. **Times on a copy with 20,000 prospects:** server answers 0.8–1.4 s instead of 1.5–4.0 s (customers 3.96 s → 0.83 s, on the laptop's development server). Database time is 2–4 ms per picker query. Most lists now open in about 0.03 s. | `public/js/picker.js`, `public/css/app.css`, `app/Http/Controllers/LookupController.php` and the controllers' lookup methods, migration 1 |
| Prospect search list went behind the form | Lists float at the top level of the page (fixed position, z-index 1000). They flip above the field near the bottom of the screen and become a sheet at the bottom on phones. Checked on every CRM and Sales form. | `picker.js`, `app.css` |
| Sidebar sub-items on hover | On a desktop with a mouse, pointing at or tabbing to a module shows its pages beside the menu after a short pause. The list stays open while the pointer moves into it, and Escape closes it. The open module's pages stay in place under it. Touch screens and the phone menu keep tap. Permissions are unchanged. | `resources/views/partials/sidebar.blade.php`, `public/js/app.js`, `app.css` |
| Enquiry list responsive | No sideways scroll at 360, 768, 1024 or 1440 px. On a phone each row is a card (enquiry no, client, next follow-up, status, Open). Tablets show 5 columns, and every column shows from 1280 px. On a phone the tabs and sort fold into a Filters panel. Search, paging, sort and view stay in the address. Applied to the Enquiry, Quotation, Site visit and Contact lists. | `resources/views/partials/listbar.blade.php` (new), the four list views, `partials/sorth.blade.php`, `app.css`, `app.js` |
| Date picker (Next action date) | Every dd/mm/yyyy field gets a calendar button. The calendar opens on click or focus, and typing still works and moves the calendar. The value stays dd/mm/yyyy, the format the server checks. Keyboard: arrows, PageUp / PageDown (Shift for a year), Home / End, Enter, Escape. It has Today, Clear and Close, and also works on rows added later. | `public/js/datepicker.js` (new), `resources/views/layouts/app.blade.php`, `app.css` |
| Hint and add button under master dropdowns | Each master-fed dropdown says where its entries are added, for example "Sources are added in Admin › Masters › Source master." Users who may add to that master get an "Add …" button that opens the add page in a new tab, and the same link appears when a search finds nothing. Coming back to the form refreshes the dropdown. Covered: the Enquiry, Site visit, Quotation, PO / Agreement, Contact, Sales page, Item master and User forms; the item tables get one hint under the table. | `app/Support/MasterLink.php` (new), those views, `app.js`, `picker.js`, `app.css` |

## Decisions recorded in the docs

All of these are in `docs/ARCHITECTURE.md` and `docs/DATABASE.md`; nothing is labelled on screen.
- Menu fly-outs are used for every module (in-place opening on hover would push the menu under the pointer).
- Debit notes keep the earlier limit.
- The company GSTIN is required for all invoices, including export.
- The date format stays dd/mm/yyyy, as everywhere else in the app.
- The suggestions not taken, with reasons.
