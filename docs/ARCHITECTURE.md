# Nursery app architecture

Standard Laravel 12 application on MySQL 8 or MariaDB 10.4+ (XAMPP on the development PC; MySQL 8.4 LTS recommended for production). Server-rendered Blade pages using the Grove design from the prototype.

## Request flow (decision A2)

```
Browser
  → routes/web.php                     one named route per screen and action
  → middleware: auth, active, permission:<page>.<action>
  → Form Request (app/Http/Requests)   validation, required fields, who may submit
  → Controller (app/Http/Controllers)  thin: calls a service, returns a view or redirect
  → Service (app/Services)             business rules, one DB transaction, writes the activity log
  → Eloquent models (app/Models)       tables, relations, casts
  → MySQL
```

## Server-side pagination (project rule P1, Sat 08/10/2026)

Every list, table, report screen and large picker pages on the server. Nothing loads a whole table into a page.

- Paging, search, filters and sort run in SQL, and every sort and filter column has an index (see `docs/DATABASE.md`).
- Normal lists use `paginate()`. Very large tables such as the activity log use `simplePaginate()` (Previous / Next, no count) or `cursorPaginate()`.
- 25 rows a page by default, 50 or 100 on choice (`?per_page=`); anything else falls back to 25. `App\Support\ListQuery` reads `per_page`, `sort` and `dir`, allows only the columns a list names, and adds the primary key last so pages are stable.
- Page, filters, sort and size stay in the address (`withQueryString()`), so links, Back and bookmarks work.
- Big pickers (customer, prospect, sales person, activity log user, entry selector) are type-ahead fields that ask a small JSON endpoint for the first 20 "starts with" matches (`LookupController`, decision A11). Without JavaScript the select lists everything only when there are at most 200 records.
- CSV downloads stream 1,000 rows at a time by id (`lazyById`), in the order rows were added (decision C13). Report PDFs have a row cap that scales with the columns (`App\Support\ReportPdf::maxRows`: about 6,000 cells, between 200 and 2,000 rows, revised 27/10/2026 after dompdf ran out of memory on wide reports) and ask for CSV above it (C9); `pdf/report.blade.php` lays the rows out in tables of 50. Downloads, PDFs and imports are limited to 10 a minute per user (C10).
- Reviewers treat a list without server-side pagination as must-fix.

## Working safely with many users at once

- Checks that guard a change (still the current revision, last active Admin, prospect not used on a quotation) run inside the transaction with the rows locked (`lockForUpdate`), so the check and the change are one step.
- Document saves retry up to 3 times after a deadlock. Numbers come from `NumberSeries`, which locks the series row first (see `docs/DATABASE.md`).
- The financial year is looked up once per request and created ahead by the seeder and a daily scheduled command (`nursery:financial-years`).
- The app keeps no local state besides session and cache, which move to Redis in production (decision A10, `docs/PRODUCTION.md`), so it runs on several servers behind a load balancer.

## Folders

| Folder | Holds |
|---|---|
| `app/Enums` | Fixed value lists as PHP enums (`UserStatus`, `Market`, `FollowupStatus`) |
| `app/Http/Controllers/Admin` | Users list, Access rights, Activity log |
| `app/Http/Controllers/Auth` | Sign in and sign out |
| `app/Http/Controllers/Crm` | Prospect and Quotation entry pages, imports, CSV and PDF |
| `app/Http/Controllers/Reports` | Report panels: CRM, Sales and Purchase (`PurchaseReportController`: options, on-screen paged rows, CSV / PDF download) |
| `app/Http/Requests` | Validation for each form |
| `app/Http/Middleware` | `EnsureUserIsActive` signs out a user who was made inactive |
| `app/Services` | `UserService`, `RoleService`, `ProspectService`, `QuotationService`: the operations and their log rows; `NumberSeries`: document numbers (D16) |
| `app/Listeners` | `LogAuthActivity`: sign-in, sign-out and failed sign-in rows |
| `app/Support` | `Access` (permission list), `Menu` (left menu), `ActivityLogger`, `WorkContext` (unit on the top bar, financial year), `Csv` (download and import), `ListQuery` (page size and sort) |
| `config/nursery.php` | Pages, permissions, default roles, menu, report lists, units |
| `database/migrations`, `database/seeders` | Schema and first data |
| `resources/views` | Blade: `layouts/app` shell, `partials` (menu, icons, permission grid), `admin`, `auth`, `errors` |
| `public/css/nursery.css` | Prototype stylesheet (design file v1.6); `app.css` holds additions |
| `public/js/preview.js` | Prototype screens for modules not built yet |
| `tests/Feature` | End-to-end tests of each operation (`CrmChangesTest` covers the CRM changes of 13/10/2026, `PageSetupAndModulesTest` Page setup, the optional parts and Contact / POC) |
| `docs/DATABASE.md` | Tables built now and the plan for every module |

## Decisions (for the client to confirm)

- **A1 Stack:** Laravel 12 (PHP 8.2+), MySQL 8 or MariaDB 10.4+ InnoDB, Blade pages, no separate front-end build step. Roles and permissions with the standard `spatie/laravel-permission` package.
- **A2 Layers:** route → middleware → Form Request → controller → service → model, as above. Business rules live in services so every screen and future import uses the same rules.
- **A3 Permissions:** named `<page>.<action>` (e.g. `sales-order.add`), listed once in `config/nursery.php`. Routes check them with the `permission:` middleware, the menu hides what the user cannot open, and Admin passes every check.
- **A4 One activity log for the whole app:** `activity_logs` with a polymorphic subject, written by services inside the same database transaction as the change, so a change and its log row are saved together or not at all.
- **A5 Morph names:** polymorphic columns store short names (`user`, `role`, `prospect`, `quotation`) instead of PHP class names, so code can be reorganised without breaking stored data.
- **A6 Database conventions:** listed at the top of `docs/DATABASE.md` (names, keys, decimal sizes, audit columns, unit and financial year on every transaction).
- **A7 Not-yet-built modules:** their menu items open the prototype's sample screens inside the real app, behind the real permissions, with a "Sample screen" banner. Each is replaced by real Blade pages, tables and services when that module is developed.
- **A8 Entry pages (D11):** each transaction page shows one entry as a full form, with an Entry selector searched on the server (D23), Add new, and the page's Import / Download CSV / PDF buttons. Built pages replace their sample screens; the old `/app/<page>` addresses redirect to them.
- **A9 PDFs and CSV:** PDFs are made on the server with `barryvdh/laravel-dompdf` (needs `dom` and `mbstring`, not `gd`); CSV files are UTF-8 with a BOM so Excel shows Indian names and the rupee sign correctly.
- **A10 Production set-up:** Redis for sessions, cache, rate limits, the permission cache and queues; trusted proxies and secure cookies from `.env`; details in `docs/PRODUCTION.md`. The development PC keeps the database drivers.
- **A11 Type-ahead pickers:** large lists are searched on the server, first 20 "starts with" matches, so the name indexes are used.
- **P1 Server-side pagination:** as described above; applies to every future module.

## CRM changes (Sat, 13/10/2026)

- Lead to PO flow: Enquiry (`EnquiryService`, follow-ups) → Site visit (`SiteVisitService`) → Quotation with BOQ, cost details and approval (`QuotationService`) → PO / Agreement (`CustomerPoService`). Documents on any of them (`DocumentService`, private disk).
- Alerts (`CrmAlerts`) and the CRM dashboard (`CrmDashboard`) use `Cache::flexible` (stale-while-revalidate): the unit-wide counts (site visits due, quotations pending approval, PO pending) and the dashboard are cached once per unit, fresh for 60 seconds and served while one request refreshes them, at most 5 minutes old. Follow-ups due are cached per employee (per unit for Admin and users not linked to an employee); a change to a lead clears only those keys after commit (`CrmAlerts::forgetDue`), so a saved follow-up shows at once. Nothing is cleared on every save of the unit (architecture review C1, database review DB-C2).
- Uploaded documents go to the disk named by `DOCUMENTS_DISK` (`config('nursery.documents_disk')`): `local` on the development PC, a shared private S3-compatible bucket in production, so every app server and the queue worker see the same files; downloads from the bucket redirect to a 5-minute link (architecture review C2). See `docs/PRODUCTION.md`.
- An enquiry's status (Open / PO received / Lost) is worked out in one place, `EnquiryService::refreshStatus`, under a lock on the enquiry row, by every quotation and PO operation (database review DB-C1).
- Entry pickers and list searches match the start of a number or name ("12" finds enquiry no 0012 in the last three years), so they use indexes (database review DB-C3).
- Quotation emails are sent by the queued job `SendQuotationMail`. Production must run a queue worker (`php artisan queue:work`); on the development PC `MAIL_MAILER=log` writes the mail to the log.
- Admin › Page setup (`PageSetupService`) decides which fields of each page show and which are mandatory; each Form Request builds its rules from it (see the section below).

## Page setup for every page and optional parts (Sat, 14/10/2026)

So the same pages can be used for other clients, field setup and the Nursery-specific parts are configuration, not code.

- **Page setup (`PageSetupService`)**: every entry page is listed once in `config('nursery.page_fields')` with its group, fields, default mandatory flags and the fields that are always shown (fixed). Admin › Page setup lists them by group and saves Show / Mandatory per field in `page_settings`. A page plugs in with three steps and no new Page setup code: add it to `page_fields`, pass its Form Request rules through `PageSetupService::rules($page, $rules)` (hidden fields are dropped, so they are not validated or saved and what is stored stays; shown fields become required or nullable), and use `PageSetupService::form($page)` in its view (`show`, `req`, `star`, `mandatory`). Built now for Enquiry, Site visit, Quotation (header), PO / Agreement, Prospect (page, Add prospect drawer and import), Contact / POC, Employee master and the four simple masters. Sales, Purchase and Accounts pages join the same way when they are built.
- **Optional parts (`ModuleSettings`, `App\Support\Modules`)**: Site Visit / Requirement Gathering, BOQ Preparation and Cost details, listed in `config('nursery.modules')`, switched on Admin › Page setup › Optional parts for the whole installation (all on for Nursery). Code asks `Modules::on('boq')`; Blade uses `@module('boq') … @endmodule`; routes use the `module:site_visit` middleware (404 while off); config entries carry `module` (pages, menu items, Page setup pages) and `label_parts` (the Access rights name "Quotation, BOQ and costing"). While a part is off it is left out of the left menu, Access rights, the enquiry workflow bar, the quotation page, the bell alerts, the lead report, the approval page caption, document types, the quotation email and Page setup; saving a role or user keeps the rights of hidden pages (`Access::keepHidden`). Nothing is deleted.
- **Caching (5,000+ users)**: both are read on most requests, so each is cached until its next save (`Cache::rememberForever`, Redis in production) and kept for the rest of the request (scoped services), so a page view runs no query for them. A save clears the key. The alert counts key includes the site visit switch, so a count cached while it was off is never shown after it is switched on.
- **Contact / POC (`ContactService`, CRM › Contacts)**: contacts belong to a client (prospect or customer), not to a unit, like prospects. The list is paged, searched (name, Contact ID, mobile digits, client, all "starts with") and sorted on the server; the enquiry and prospect pages show the client's first 10 contacts with Add contact and View all.

### Decisions (14/10/2026)

- **M1 Where the switches live:** on Admin › Page setup, behind `page-setup.edit`, because they decide which pages and fields exist. No new permission.
- **M2 One switch per installation**, not per unit or role: the parts describe how the client works, and per-role access is already Access rights.
- **M3 A part that is off** refuses its pages with 404 (as if not built), hides its alert, workflow step, report columns and document types, and drops BOQ files from the quotation email. Approval no longer needs cost details while costing is off. Site visit documents, and BOQ and costing documents of a quotation, are hidden and refused.
- **M4 Fixed fields**: a field the database needs on every row (NOT NULL) or that the app's rules use (client, dates and numbers, sales person, currency, status) is always shown and mandatory. Tick-box groups (contact roles) can be shown or hidden but not made mandatory.
- **M5 Page setup scope**: the field forms of the built CRM pages and masters. Quotation items, cost details (a switch of their own), follow-ups and document uploads are not field-configurable. Users, roles and approval settings are admin screens, not client pages, so they are not in Page setup.
- **C1 Contact / POC fields** (Word file section marked "need to discuss"): all fifteen fields of the Word file. Contact ID system generated (CT00001). Client = a prospect or an existing customer, picked like on the enquiry, mandatory. Name mandatory always; Mobile mandatory by default (can be changed in Page setup); the rest optional. Preferred communication = Call / WhatsApp / Email, the follow-up kinds of the Word file. Decision maker, Influencer, Purchase, Facility and Accounts / billing contact are tick boxes, several allowed. Notes free text.
- **C2 Contact place**: a CRM › Contacts page (list and form) with its own rights (`contact.*`, given to Sales), plus a Contacts / POC box on the enquiry and prospect pages. The enquiry's own Contact name and no, the site visit POC and the quotation and PO contact stay as typed text, as in the Word file.

### After the reviews (14/10/2026)

- **One place for the switches (architecture review P1):** `AppServiceProvider` registers three `Gate::before` checks in this order: (1) a right on a page whose `config('nursery.pages')` entry has a `module` that is off is refused for everyone, Admin included (`Access::moduleOf`); (2) Admin passes; (3) spatie's own permission check (`config/permission.php` `register_permission_check_method` is false so it runs third). Routes (`permission:` middleware), the menu, `@can`, alerts, lookups and documents all ask the gate. A Sales, Purchase or Accounts page tagged with a `module` gets this with no code. The `module:` route middleware stays only to answer "<part> is switched off" (404) instead of a bare 403, and runs before the record is looked up.
- **Cache:** a save writes the new values into the cache after commit; each entry lasts at most an hour (`ModuleSettings::CACHE_TTL`), so a stale value cannot stay longer. Redis eviction policy: see `docs/PRODUCTION.md`.
- **Adding a page to Page setup (checklist):** 1. add it to `page_fields` in `config/nursery.php` with its group, fields, defaults and fixed fields (every NOT NULL column or field a rule depends on is fixed); 2. in its Form Request return `app(PageSetupService::class)->rules('<page>', $rules)` (imports too); 3. in its view use `PageSetupService::form('<page>')` (`show`, `req`, `star`, `mandatory`); 4. its service fills only the keys it receives (`array_intersect_key`), so hidden fields keep their values; 5. if it belongs to an optional part, add `module` to its `pages`, `nav` and `page_fields` entries. A test fails if a page in `page_fields` has no `rules()` call.
- **M2 (added):** one installation and database per client, not multi-tenant; the switches and Page setup are per installation.
- **C3 Contacts across units (code review S5):** contacts, like prospects, are shared by all units: anyone with Contact · View sees every client's contacts (names, mobiles, emails). Revisit if units become separate companies.
- **C4 Prospect delete:** deleting a prospect soft deletes its contacts in the same transaction and logs them.

## Sales module (workbook SALES tab, built 15/10/2026)

- **One page (SALES A24):** `/sales/invoices` and `/sales/orders` both render `sales/page.blade.php` (`App\Support\SalesPage`): the Tax invoice card first, the Sales order card below it, closed by default (D5) and opened (and scrolled to) from the menu's Sales Order item. Each card has its own entry picker, form and error bag (`invoice`, `order`), so a mistake on one card never shows on the other. Items are sent as one JSON field (`lines_json`), like the quotation.
- **Services:** `SalesOrderService` (create, change, delete, submit, approve, reject), `SalesOrderImport`, `SalesInvoiceService` (create, change, cancel, GST, PO balance, stock), `PartyService`, `ItemService`, `CompanyService`, `SalesMasterService`. Every change writes its activity log row in the same transaction.
- **Qty lock (D6):** each sales order line keeps `invoiced_quantity`. An invoice against a PO locks the order's lines in id order (`lockForUpdate`), checks quantity ≤ balance (Sales) or ≤ invoiced (credit / debit note), takes the order's rate, and updates the billed quantity in the same transaction, so two invoices at once cannot overbill. A change or a cancel first gives the old quantities back.
- **GST (D14, `App\Support\Gst`):** per line from the item's tax structure. Customer GSTIN state = company GSTIN state (or no GSTIN): CGST + SGST; another state: IGST; export customer (foreign currency): no GST, under LUT. P&F charges are part of the taxable value at the highest line rate. Rates and HSN are copied onto each invoice line.
- **Numbers (D16):** `NumberSeries` keys `sales_order` (SO), `tax_invoice` (INV), `credit_note` (CN), `debit_note` (DN), per unit and financial year, taken under the series row lock. Invoices are cancelled, never deleted, so the series stays unbroken (C12).
- **Stock ledger:** `stock_movements` gets a row per invoice line: Sales out, Credit in, Debit none. Approved stock transfers (out of one unit, into the other) and quality-approved GRNs (accepted quantity) add their rows. The Stock report and the Stock ledger read every source.
- **Approval (D3):** `approval_rules` row `sales_order` (Admin › Approval), approvers hold `sales-order-approval.edit`; history in `approvals` (polymorphic). An export order always goes to an approver (the limit is in rupees).
- **Masters:** Party master and Item master have their own pages (list, form, Import, Download CSV); Tax master, HSN/SAC, UOM, Category (items) and Transporter share one config-driven screen (`config('nursery.sales_masters')`, `SalesMasterController`); Company is one form. All are in Page setup.
- **Page setup:** pages `sales-order`, `tax-invoice`, `party`, `item`, `company`, `master-tax-structures`, `master-hsn-sac`, `master-uoms`, `master-item-categories`, `master-transporters`, through the same `PageSetupService::rules()` / `form()` calls as the CRM pages.
- **Optional parts:** Sales shows no site visit, BOQ or cost details data (sales orders are not made from quotations; the workbook has no such link), so the three switches have nothing to hide in Sales. If a later change links them, the gate and `@module` apply as in CRM.

### Decisions (15/10/2026)

- **S-1 Masters built with Sales:** Party, Item, Company, Tax, HSN/SAC, UOM, Category (items) and Transporter, because invoices cannot work without them. Currency/Country and Bank master keep their sample screens (Bank belongs to Accounts). The RM BOM section of the Item master (D17) waits for Purchase, because its raw materials are purchase items.
- **S-2 GST fetch from GST no (ADMIN A22):** not built; it needs a paid GST portal (GSP) account. The GST no is checked for its format, and its first two digits give the state for CGST + SGST or IGST.
- **S-3 Item no:** typed, or blank for the next number of the category's prefix (PRS-0001), taken under a lock on the category row.
- **S-4 Customer / supplier rule (ADMIN A23):** a customer's item cannot be a Purchase item, a supplier's cannot be a Sales item; a "Both" party can have either.
- **S-5 Sales order:** Rev no / Rev date are the customer PO's revision, typed. Draft and Rejected orders can be changed; a changed Rejected order is a Draft again; Pending and Approved orders only by an Admin (D11). An invoiced line keeps its part and cannot go below what was invoiced; an order with invoices cannot be deleted. A PO number cannot repeat for the same party. Rate blank = Item master price.
- **S-6 Tax invoice:** Invoice date in the financial year on the top bar and not after today. With a PO: only an Approved order of the same party and unit, its parts and rates. Without a PO: active Sales / Both items of the customer or of no party (A22). Credit and debit notes are invoice types with their own series; they do not change the PO balance and cannot go above what was invoiced on a PO line. The invoice type and number never change after saving.
- **S-7 Export customers (D15):** a party with a currency other than INR. Its invoices are in that currency without GST ("export under LUT"), its orders always need approval, and they appear in the Export sales report.
- **S-8 Cancel:** Delete on a tax invoice is Cancel with a reason; the lines stay as a record, the PO balance and stock go back, and cancelled invoices are left out of reports.
- **S-9 Reports:** Sales report = tax invoices (all customers, with currency); Export sales report = export customers only; Credit/Debit report = credit and debit notes; Summary or Detailed for these three. Sales order report = one row per item with invoiced and balance. Stock report = opening, in, out, closing and Below minimum per active item from the stock ledger, with "Below minimum stock only".
- **S-10 Not built now:** the Sales dashboard tiles (D18) come with the dashboard work; e-way bill / e-invoice portal integration (no portal account).

## Screen requests (Sat, 18/10/2026)
- **Select with search** (`public/js/picker.js`, one component for every server-fed dropdown): clicking a field opens its list at once with a search box and a status line ("Loading…", "No matches", "Scroll for more"). The first page is fetched when the pointer rests on or focus reaches the field and kept for 60 seconds, so most lists are already filled when they open. Typing searches on the server (250 ms pause, the older request is cancelled); scrolling to the end asks for the next 20. Every lookup endpoint takes `?q=&page=` (1–50) through `LookupController::page()`, ordered by name then id so pages never overlap.
- Lists float at the top level of the page with fixed position, flip above the field near the bottom of the screen, and become a sheet at the bottom on phones; nothing on the page can cover them.
- Indexes for the pickers' "first page in name order": `parties(status, deleted_at, name)`, `prospects(deleted_at, name)`, `cities(is_active, name)`, `employees(status, deleted_at, name)`. The prospect search looks at phone numbers only when the text is a number, so a name search uses the name index.
- **Menu fly-outs**: on a desktop with a mouse (1024 px and wider), pointing at or tabbing to another module shows its pages beside the menu after a short pause; it stays open while the pointer moves into it; Escape closes it. The open module's pages stay in place under it. Touch screens and the phone menu keep tap. The fly-out is used for every module, wide or narrow menu alike, because opening pages in place on hover would push the menu under the pointer.
- **Lists that fit the screen** (`partials/listbar`, `table.fit` with column classes c1–c3): every column from 1280 px, the c3 columns hidden from 1024 to 1279, the c2 columns hidden on tablets, and on phones each row is a card with the key fields and an Open button. On phones the view tabs and the sort fold into a Filters panel; search, paging, sort and view all stay in the URL. Used on the Enquiry, Quotation, Site visit and Contact lists.

## Sales review fixes (18/10/2026)
- **Contract for Purchase and Accounts (architecture review S7):** stock moves only through `App\Services\StockLedger::move()` / `remove()`, which write `stock_movements` (with a `source_type` such as `sales_invoice`, later `grn` or `stock_transfer`) and keep `stock_balances` in step, always inside the document's own transaction. Document series come from `NumberSeries`, approvals from `ApprovalRule` with an `approvals` row, and page fields from `page_fields` through `PageSetupService::rules()` and `form()`.
- **Credit notes:** at most what was invoiced, less earlier credit notes. With a PO this is per PO line (`credited_quantity`, under the line lock). Without a PO it is per item, across the customer's tax invoices of the year (party row lock). Debit notes change value only and keep the earlier limit (not more than invoiced on a PO line, no limit without one), because they move no stock.
- **Company GSTIN:** required before any invoice is saved. It decides CGST + SGST or IGST and is printed as the supplier's GSTIN.
- **Blank rate:** the Item master price is used only when the item is priced in the party's currency; otherwise the user types the rate. This applies to orders, invoices and the order import. A rate on an order line that is already invoiced cannot change.
- **Customer PO file:** once an order is Pending or Approved, only an Admin can add or remove its documents, as with the order itself.
- **Report PDFs** stop after reading one row more than their cap (`ReportPdf::maxRows`, at most 2,000). The Stock report reads `stock_balances` plus only the period's movements per batch of items, and says where stock comes from. It is listed under Sales and Purchase (one route, `reports.sales.stock`, open to either reports right; the Purchase menu adds `?in=purchase`). Purchase reports (27/10/2026): see `docs/purchase/purchase-reports-report.md`.
- **Sales page:** Invoice type comes first. Totals with GST show while typing, worked out by the same rules as the server. PO quantities start empty with "Max …" as the hint, and lines with nothing left show as text. Typed rows come back after a failed save. Changing the party or PO asks before replacing typed rows, and changing the type keeps them. On phones the item tables are cards, and a "Sales order" button at the top opens that card. Approvers without Sales order View see the order read-only with Approve and Reject.
- **Shared screen parts (18/10/2026):** a date picker on every dd/mm/yyyy field (`public/js/datepicker.js`), and under every master-fed dropdown a hint naming the master plus, for users who may add to it, an "Add …" button opening its add page in a new tab (`App\Support\MasterLink`). The dropdown refreshes when the user comes back.
- **Suggestions not taken (recorded):** a bell entry for sales orders waiting for approval (UX S6); building the closed Sales order card only when opened, caching `Company::current()`, and a type-ahead for transporters (architecture S4–S6, small lists and single-row reads today); an item-number counter on categories and name-limited import loading (architecture S2, S3: kept for the item import work); ledger reversal rows (database SL-S1).


## Purchase › Purchase order (built 27/10/2026)
- **Page:** `/purchase/orders` (`PurchaseOrderController`, `purchase/orders/page.blade.php`, `public/js/purchase.js`), built to look and work like the Sales order card: entry bar, approval bar, short view (Party name, PO date) with "Show all headings" (delivery date, payment terms, currency, transporter, billing / shipping address, remarks; PURCHASE B11 / C11), items (item picker fills description, UOM, tax and price hint; UOM and Tax are drop-downs from their masters), GST totals while typing, Generate PDF (company logo via `Company::pdfLogo()`), list `/purchase/orders/list` (All, Pending approval, Approved, Open (pending GRN), Draft or rejected). There is no Import or CSV, because the prototype's page capability table has no "po" entry. Menu item Purchase › Purchase Order goes to the built page; `/app/po` redirects there (`built_pages.po`) and keeps the `purchase-order.view` check.
- **Service:** `PurchaseOrderService`: create, update, delete, cancel, submit, approve, reject, receive. Each runs in a transaction with the PO row locked (`lockForUpdate`) and logs in the same transaction. Totals and GST (`Gst::split` per line) are always worked out on the server. Supply type comes from the supplier: Intra-state, Inter-state, or Import for a supplier in a currency other than INR (no GST on the PO).
- **Rules:** the supplier is an active Supplier / Both party. Items are active Purchase / Both items of that supplier or of no party (S-4). Rate blank = the item price, but only in the PO's currency. The PO date is not after today and falls in the PO's financial year. Draft / Rejected POs can be edited; Pending / Approved / Partly received only by an Admin (as sales orders). Delete (soft) applies to Draft / Rejected with nothing received. Cancel with a reason applies to Pending / Approved with nothing received (`purchase-order.delete`).
- **Approval:** `approval_rules` row `purchase_order` (seeded as required, above ₹1,00,000 taxable value, as the prototype's "Purchase PO" does). A PO in another currency always needs approval. Approvers hold `purchase-order-approval.edit` (Admin by default). The rule is set in Admin › Approval.
- **GRN contract:** see `docs/purchase/purchase-order-report.md`. In short: `purchase_order_lines.received_quantity` changes only through `PurchaseOrderService::receive($po, [line_id => ±qty], $user)`, called inside the GRN's transaction. Pending = `quantity - received_quantity`. Open POs: `PurchaseOrder::pendingReceipt()`.
