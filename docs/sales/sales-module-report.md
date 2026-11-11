# Sales module: change report

Developer report, 15/10/2026. It covers the Sales module of the workbook's SALES tab and the masters it needs.

**What it covers:** the SALES tab's two pages on one page (Tax invoice, with credit and debit notes, and Sales order), their actions (add, update, import, CSV, PDF), the sales order approval, the qty lock and PO rules, and all five Sales reports. It also covers the ADMIN-tab masters an invoice needs: Party, Item, Company, Tax, HSN/SAC, UOM, Category (items) and Transporter.

**Tests:** 138 pass, with 1,899 assertions. That is 126 from before (one Page setup test moved to a page not built yet) plus 12 new ones in `tests/Feature/SalesTest.php`. The same 138 also pass on a throwaway MariaDB 10.4 database (`nursery_test_tmp`, dropped afterwards). The tester's separate 5-entry check of every CRM form also passed (105 of 105).

**Database:** "nursery" was backed up to `storage/app/backups/nursery-before-sales-module-20261015.sql`, then `2026_10_15_000001_create_sales_tables` ran. The migration only adds:
- the new tables;
- the GST slabs and six units of measure;
- the sales order approval rule;
- two approval permissions.

No existing table, column or row changed.

**XAMPP:** MySQL was stopped. I started it the way `mysql_start.bat` does for the backup, the migration, the MariaDB tests and the screen check, and it stayed on during the tester's check. I stopped it again at the end. `.env` still points at "nursery".

**Screen check:** I used a throwaway copy, `nursery_sales_check`, with sample parties and items; it is dropped. I ran one full flow:
- added a sales order with two items and sent it for approval;
- Admin approved it;
- Make tax invoice loaded the PO's parts with their balances;
- saved an invoice for 800 of one part and all 600 of the other, plus ₹250 P&F;
- the totals came out at Taxable ₹70,850, CGST and SGST ₹6,376.50 each, Grand total ₹83,603;
- Generate PDF downloaded.

At 375 px there is no horizontal scroll on the Sales page, the Item master or the report panel, and the Sales Order menu item scrolls to its card. There were no console errors.

## What a user sees

1. **Sales › Tax Invoice / Sales Order** open one page: the Tax invoice card first, the Sales order card below (closed until the Sales Order item or a link opens it).
2. **Sales order:**
   - Fields: party, PO number, start and end date, Rev no and Rev date, and items (item no / description / IPN, quantity, rate, delivery date).
   - A blank rate uses the Item master price.
   - Upload PO (PDF, JPG or PNG, up to 10 MB).
   - Send for approval → Approve / Reject with a reason. The rule is in Admin › Approval, and by default every order needs approval.
   - Make tax invoice; Import, Download CSV, Sales order list.
3. **Tax invoice:**
   - Fields: party, type (Sales / Credit / Debit), date, PO no (the party's approved orders), shipping address (filled from the party), mode of transport, transporter, vehicle no, distance, P&F.
   - With a PO, its parts are listed with "Max …" (the balance) and the order's rate is locked.
   - Without a PO, the customer's parts can be picked, and a blank rate uses the Item master price.
   - Totals: CGST + SGST or IGST, or no GST for export customers.
   - Generate PDF (company details, HSN summary, amount in words), Download CSV, Invoice list.
   - Cancel needs a reason; the number stays in the series.
4. **Reports › Sales:** Sales order report, Sales report (Summary / Detailed), Export sales report, Credit/Debit report (customer), Stock report and Minimum stock report. Each one has From / To, Unit, Party and its own options, as CSV or PDF.
5. **Admin › Masters:**
   - Party master and Item master: list, form, Import, Download CSV.
   - Tax master, HSN/SAC, UOM, Category (items), Transporter: one list with an add / edit form beside it.
   - Company: one form.
6. **Admin › Page setup** lists Sales (Sales order, Tax invoice) and the new masters. **Admin › Approval** has a Sales order rule. **Access rights** has "Sales order approval".

## Decisions taken (in `docs/ARCHITECTURE.md`, "Decisions (15/10/2026)")

- S-1 Masters built now: Party, Item, Company, Tax, HSN/SAC, UOM, Category (items), Transporter. Currency/Country and Bank stay sample screens. RM BOM waits for Purchase.
- S-2 The GST portal lookup is not built (it needs a GSP account). The GST no is checked for its format and gives the state.
- S-3 The item no is typed, or blank for the category prefix's next number (PRS-0001), taken under a lock.
- S-4 A customer's item cannot be a Purchase item; a supplier's cannot be a Sales item.
- S-5 Sales order:
  - Rev no and Rev date are the customer PO's revision.
  - Draft and Rejected orders can be changed; Pending and Approved ones only by an Admin.
  - Invoiced lines are locked.
  - A PO number cannot repeat for the same party.
- S-6 Tax invoice:
  - The date must be in the year on the top bar and not after today.
  - A PO must be the same party's Approved order; its rate is used.
  - Credit and debit notes have their own series, do not change the balance, and cannot go above what was invoiced.
- S-7 Export customer = a party with a currency other than INR. Its invoices are in that currency with no GST (LUT), and its orders always need approval.
- S-8 Delete on an invoice = Cancel. The lines stay, the PO balance and stock go back, and the invoice is left out of reports.
- S-9 Report contents, as listed under "What a user sees".
- S-10 Not built now:
  - Sales dashboard tiles (D18), which come with the dashboard work.
  - E-way bill and e-invoice portal integration.
- GST: CGST + SGST when the customer's GSTIN state is the company's (or the customer has no GSTIN), IGST for another state. P&F is taxed at the highest line rate.
- Optional parts: Sales shows no site visit, BOQ or costing data, so the three switches have nothing to hide there.

For the decisions register (design/05-decisions-register.md, kept by the design thread): S-1 to S-10.

## File by file

### Database and configuration

| File | Change |
|---|---|
| `database/migrations/2026_10_15_000001_create_sales_tables.php` | New. companies, tax_structures, hsn_sac_codes, uoms, item_categories, transporters, items, sales_orders, sales_order_lines, sales_invoices, sales_invoice_lines, stock_movements. Seeds GST slabs, units, the sales order approval rule and two permissions. `down()` removes them. |
| `config/nursery.php` | `sales-order-approval` page; Sales menu items now go to the built page; Sales report routes; `built_pages.sales`; page_fields for sales-order, tax-invoice, party, item, company and the five lists; `sales_masters`; `document_categories.sales_order`. |
| `config/permission.php` | Unchanged this round (gate order from the Page setup fixes). |
| `routes/web.php` | Sales routes (invoices, orders, pickers), master routes (parties, items, company, lists), `admin/approval/sales-order`, Sales reports, `sales_order` documents. Every route checks a permission. |
| `app/Providers/AppServiceProvider.php` | Morph names for the new models. |
| `app/Services/NumberSeries.php` | Prefixes SO, INV, CN, DN. |

### Models and rules

| File | Change |
|---|---|
| `app/Models/Master.php`, `TaxStructure.php`, `HsnSacCode.php`, `Uom.php`, `ItemCategory.php`, `Transporter.php`, `Company.php`, `Item.php`, `SalesOrder.php`, `SalesOrderLine.php`, `SalesInvoice.php`, `SalesInvoiceLine.php`, `StockMovement.php` | New models. |
| `app/Models/Party.php` | Fields, relations, `stateCode()`, `isForeign()`. |
| `app/Models/ActivityLog.php` | Labels for party, item, company, sales order and invoice actions. |
| `app/Support/Gst.php` | New. Supply type and the CGST / SGST / IGST split. |
| `app/Support/Money.php` | `words()`: amount in words, Indian style (lakh, crore). |
| `app/Support/SalesPage.php` | New. Builds the one Sales page (both cards, pickers, searches). |

### Services

| File | Change |
|---|---|
| `app/Services/SalesOrderService.php` | New. Create, change, delete, submit, approve, reject; line rules (billed lines locked); log rows. |
| `app/Services/SalesOrderImport.php` | New. CSV import grouped by Group, whole file checked first, numbers reserved in one step. |
| `app/Services/SalesInvoiceService.php` | New. Create, change, cancel; PO balance under row locks; GST per line and on P&F; stock rows. |
| `app/Services/PartyService.php`, `ItemService.php`, `CompanyService.php`, `SalesMasterService.php` | New. Master saves with logs, in-use checks, imports, item auto-number. |
| `app/Services/ApprovalSettingsService.php` | Sales order rule wording. |

### Requests and controllers

| File | Change |
|---|---|
| `app/Http/Requests/Sales/SalesOrderRequest.php`, `SalesInvoiceRequest.php`, `SalesOrderActionRequest.php`, `InvoiceCancelRequest.php` | New. Rules with Page setup; error bags `order` / `invoice`. |
| `app/Http/Requests/Admin/PartyRequest.php`, `ItemRequest.php`, `CompanyRequest.php`, `SalesMasterRequest.php`, `SalesOrderApprovalRuleRequest.php` | New. |
| `app/Http/Requests/Crm/DocumentRequest.php` | Upload PO: PDF, JPG, PNG only. |
| `app/Http/Controllers/Sales/SalesOrderController.php`, `SalesInvoiceController.php` | New. Page, lists, lookups, PO lines, CSV, import, PDF, approval, cancel. |
| `app/Http/Controllers/Admin/PartyController.php`, `ItemController.php`, `CompanyController.php`, `SalesMasterController.php` | New. |
| `app/Http/Controllers/Admin/ApprovalController.php` | Sales order rule. |
| `app/Http/Controllers/Admin/MasterController.php` | Sample list now only Currency/Country and Bank master. |
| `app/Http/Controllers/Crm/DocumentController.php` | `sales_order` documents. |
| `app/Http/Controllers/Reports/SalesReportController.php` | New. The five reports, CSV or PDF. |

### Screens and scripts

| File | Change |
|---|---|
| `resources/views/sales/page.blade.php`, `orders-list.blade.php`, `invoices-list.blade.php` | New. |
| `resources/views/pdf/sales-invoice.blade.php` | New. Tax invoice / credit / debit note PDF. |
| `resources/views/reports/sales.blade.php` | New. Report panel. |
| `resources/views/admin/parties/*`, `admin/items/*`, `admin/company/edit.blade.php`, `admin/sales-masters/list.blade.php` | New master screens. |
| `resources/views/admin/masters/index.blade.php`, `admin/approval/edit.blade.php` | Sales masters listed; Sales order approval card. |
| `resources/views/partials/ferr.blade.php` | Optional error bag. |
| `public/js/sales.js` | New. Item rows, PO parts, party details, amounts, JSON send. |
| `public/js/picker.js` | `window.nPickerInit` for rows added later. |

### Tests and docs

| File | Change |
|---|---|
| `tests/Feature/SalesTest.php` | New, 12 tests: all masters (rules, uniqueness, in-use, Page setup); party and item import / export and auto-number; company; sales order numbering, rates, validation, approval and rejection, Admin-only edits, auto-approval, export approval, approval rule; Upload PO, import, export, list; GST intra / inter / export with P&F, dates, PDF, list, CSV; PO qty lock and rate, edit, credit / debit notes, stock, billed-line protection, cancel; only approved same-party orders; page, menu, card error bags and rights; Page setup on Sales pages and masters; all five reports with filters. |
| `tests/Feature/PageSetupAndModulesTest.php` | The plug-in example moved to a page not built yet. |
| `docs/ARCHITECTURE.md`, `docs/DATABASE.md` | Sales sections, decisions, tables. |

## For the reviewers and the tester

- Every list pages on the server (25 a page by default) and keeps search, view and sort in the address; pickers are type-ahead above 200 records; CSV files are streamed 1,000 records at a time; report PDFs stop at 2,000 rows.
- Every change writes its activity log row in the same transaction. Numbers come from the series under its row lock. PO balances are checked and updated under row locks.
- Money shows in Indian grouping on screen and in PDFs; CSV keeps plain numbers.
- Things to try:
  - billing more than a PO balance;
  - two invoices against the same PO;
  - a customer in another state, and an export customer;
  - cancelling an invoice;
  - editing an approved order as Sales (refused) and as Admin;
  - hiding fields in Page setup;
  - a role without Sales rights.
