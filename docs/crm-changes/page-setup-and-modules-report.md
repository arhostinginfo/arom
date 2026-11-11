# Page setup for every page, optional parts and Contact / POC: change report

Developer report, 14/10/2026. Request: Sat in the project chat ("Page setup for every page … switches for Site Visit, BOQ and Cost details … also complete all task which is pending"). The pending task was the Contact / POC section of the CRM changes Word file.

**Status:** all three parts are built. **115 tests pass, 1,454 assertions**: the 100 from before, unchanged, plus 15 new ones in `tests/Feature/PageSetupAndModulesTest.php`. The same 115 also pass on a throwaway MariaDB 10.4 database (`nursery_test_tmp`, dropped afterwards).

**Database:** "nursery" was backed up to `storage/app/backups/nursery-before-page-setup-and-modules-20261014.sql`, then `2026_10_14_000003_create_module_settings_and_contacts_tables` ran on it. It only adds two tables (`module_settings`, `contacts`) and the four `contact.*` permissions (also given to the Sales role). No existing table, column or row was changed.

**XAMPP:** MySQL and Apache were both stopped on Sat's PC. I started only MySQL, the same way XAMPP's `mysql_start.bat` does (no setting changed), for the backup, the migration, the MariaDB test run and the screen check, and stopped it again at the end, so XAMPP is as I found it.

**Screens:** checked on a throwaway copy (`nursery_ps_check`, served by `php artisan serve` on 127.0.0.1:8123, dropped afterwards) at desktop width and at 375 px: Page setup list with Optional parts, Page setup of Contact / POC, Contacts list and form, the quotation page with costing off and the Site visit page refused while it is off. No horizontal scroll at 375 px; no console errors other than the 404 of the deliberately refused page and the browser's own view-transition notices on quick navigation.

## What a user sees

1. **Admin › Page setup** lists every built page, grouped **CRM** (Enquiry, Site visit, Quotation, PO / Agreement received, Prospect, Contact / POC) and **Masters** (Employee master, Source, Category, Industry type and City master). Each page's Configure screen has Show and Mandatory per field, as Enquiry had before. Fields the app always needs are shown ticked and locked. Hiding a field keeps what is already saved in it.
2. **Admin › Page setup › Optional parts** has an On tick for **Site Visit / Requirement Gathering**, **BOQ Preparation** and **Cost details (costing)**, all on for Nursery. While a part is off:
   - Site visit: gone from the CRM menu, Access rights, Page setup, the enquiry workflow bar, the bell (Site visits due), and the lead report columns; its pages, list, lookup and photos answer "Page not found".
   - BOQ: the quotation's documents box becomes "Attachments", BOQ is not offered as a document type, existing BOQ files are hidden and refused, the quotation email sends only the PDF, and texts no longer mention the BOQ.
   - Costing: the Cost details box is gone from the quotation, cost values are not read, Submit for approval no longer asks for costs, and the Access rights name, approval caption and workflow step drop "costing".
   - Everything saved stays and shows again when the part is switched back on, including the rights roles and users had on a hidden page.
3. **CRM › Contacts** (new menu item after Prospect): list paged and searched on the server (name, Contact ID, mobile however typed, client name) and a form with all fifteen Word file fields. The **enquiry** and **prospect** pages show the client's contacts (first 10 by name) with Add contact and View all.

## Decisions taken (recorded in `docs/ARCHITECTURE.md`, "Decisions (14/10/2026)")

- M1 The switches live on Admin › Page setup behind `page-setup.edit`; no new permission.
- M2 One switch per installation, not per unit or role.
- M3 A part that is off refuses its pages with 404 and is hidden everywhere it shows; approval does not need costs while costing is off; nothing is deleted.
- M4 Fields the database needs on every row or the app's rules use are fixed (always shown, mandatory). Tick-box groups can be hidden but not made mandatory.
- M5 Page setup covers the field forms of the built CRM pages and masters, not quotation items, follow-ups, uploads or admin screens.
- C1 Contact / POC: all fifteen Word file fields; Contact ID CT00001 (system generated); client (prospect or customer) and Name mandatory, Mobile mandatory by default; Preferred communication Call / WhatsApp / Email; the five contact roles are tick boxes.
- C2 Contacts are their own CRM page with `contact.*` rights (Sales has them) plus a box on the enquiry and prospect pages; the existing text contact fields on enquiry, site visit, quotation and PO stay as the Word file has them.

For the decisions register (design/05-decisions-register.md, kept by the design thread): the seven items above, M1 to M5, C1 and C2.

## File by file

### Configuration and database

| File | Change |
|---|---|
| `config/nursery.php` | New `modules` (the three parts, default on, with what each covers). `page_fields` now lists every page with `group`, optional `module`, and per field `inputs`, `checked`, `can_require`. `pages`: `site-visit` tagged `module`, `quotation` gets `label_parts`, new `contact` page. `nav`: Site visit tagged `module`, new Contacts item. Sales role gets `contact.*`. New `contact_channels` and `document_category_modules`. |
| `database/migrations/2026_10_14_000003_create_module_settings_and_contacts_tables.php` | New. `module_settings` and `contacts` tables with keys and indexes, `contact.*` permissions given to Sales; `down()` removes them. |
| `bootstrap/app.php` | Route middleware alias `module`. |
| `routes/web.php` | Site visit routes grouped under `module:site_visit`; Contacts routes (index, create, store, show, update, destroy) each with `contact.*`; `PUT admin/page-setup-modules` with `page-setup.edit`; customer and prospect lookups also allow `contact.view`. |

### Shared pieces

| File | Change |
|---|---|
| `app/Services/PageSetupService.php` | Rewritten for every page: `pages()` grouped and filtered by module, `available()`, `fields()` with `can_require` and a per-request copy, `rules($page, $rules)` (drops hidden inputs and their `.*` rules, sets required / nullable, leaves fixed fields as written), `form($page)`. Save and its activity log row as before. |
| `app/Support/PageForm.php` | New. What a view needs: `show`, `req`, `star`, `mandatory()` ("Mandatory: A, B and C."). |
| `app/Services/ModuleSettings.php` | New. On / off per part, cached until saved and kept for the request; `save()` in a transaction with an activity log row (`modules.updated`). |
| `app/Support/Modules.php` | New. `on()`, `allows()`, `label()` ("Quotation, BOQ and costing"). |
| `app/Http/Middleware/EnsureModuleIsOn.php` | New. 404 while the part is off. |
| `app/Models/ModuleSetting.php`, `app/Models/Contact.php` | New models. Contact keeps `mobile_digits`, `roleNames()`, `clientName()`, `scopeOfClient()`. |
| `app/Providers/AppServiceProvider.php` | Scoped `PageSetupService` and `ModuleSettings`, morph name `contact`, Blade `@module`. |
| `app/Models/ActivityLog.php` | Labels for `modules.updated` and `contact.created / updated / deleted`. |
| `app/Support/Access.php` | `pageLabel()`, `pages()` (Access rights list without hidden pages), `hidden()`, `keepHidden()`. |
| `app/Support/Menu.php` | Skips menu items of a part that is off. |
| `app/Services/RoleService.php`, `app/Services/UserService.php` | Saving keeps rights on hidden pages (`Access::keepHidden`). |
| `resources/views/partials/matrix.blade.php` | Access rights grid from `Access::pages()` with the dynamic quotation name. |

### Page setup screens

| File | Change |
|---|---|
| `app/Http/Controllers/Admin/PageSetupController.php` | Groups pages, refuses a page of a part that is off, new `updateModules()`. |
| `app/Http/Requests/Admin/ModuleSettingsRequest.php` | New. Only the listed parts, booleans, `page-setup.edit`. |
| `resources/views/admin/page-setup/index.blade.php` | Optional parts card (On tick, part and what it covers) and the pages by group. |
| `resources/views/admin/page-setup/edit.blade.php` | Show-only fields have no Mandatory tick; general wording. |

### Pages that now follow Page setup

| File | Change |
|---|---|
| `app/Http/Requests/Crm/EnquiryRequest.php` | Uses `PageSetupService::rules()`; the field-to-inputs map moved to config. |
| `app/Http/Requests/Crm/SiteVisitRequest.php`, `resources/views/crm/site-visits/form.blade.php` | Rules and fields follow Page setup; "Mandatory: …" line from the setup. |
| `app/Services/SiteVisitService.php` | Hidden visit time keeps the saved time. |
| `app/Http/Requests/Crm/QuotationRequest.php` | Page setup on header fields; cost rules dropped while costing is off. |
| `app/Services/QuotationService.php` | Hidden header fields keep their values; Submit needs costs only while costing is on. |
| `resources/views/crm/quotations/form.blade.php` | Header fields follow Page setup; Cost details only while costing is on; documents box "BOQ and attachments" or "Attachments". |
| `app/Http/Requests/Crm/CustomerPoRequest.php`, `resources/views/crm/customer-pos/form.blade.php` | Rules and fields follow Page setup; the end-after-start check only while validity is shown. |
| `app/Http/Requests/Crm/ProspectRequest.php`, `resources/views/crm/prospects/fields.blade.php` | Page, Add prospect drawer and import follow Page setup; field errors under every field. |
| `app/Http/Requests/Admin/EmployeeRequest.php`, `resources/views/admin/employees/form.blade.php` | Rules and fields follow Page setup; hidden date of joining kept. |
| `app/Http/Requests/Admin/MasterRequest.php`, `app/Services/MasterService.php`, `resources/views/admin/masters/list.blade.php` | Order in dropdowns follows Page setup; when hidden a new entry goes last and an edit keeps its order. |

### Optional parts, where they show

| File | Change |
|---|---|
| `app/Support/CrmFlow.php` | No Site visit step while off; quotation step named from the parts that are on. |
| `app/Services/CrmAlerts.php` | No Site visits due while off; the switch is part of the cache key. |
| `app/Http/Controllers/Reports/CrmReportController.php`, `resources/views/reports/crm.blade.php` | Lead report without the two site visit columns while off. |
| `app/Services/DocumentService.php`, `app/Http/Requests/Crm/DocumentRequest.php`, `app/Http/Controllers/Crm/DocumentController.php`, `resources/views/crm/partials/documents.blade.php` | Document types of a part that is off are not offered, listed, downloaded or deleted; site visit documents refused while site visits are off. |
| `app/Mail/QuotationMail.php` | BOQ files attached only while BOQ is on. |
| `resources/views/crm/quotations/status.blade.php`, `resources/views/admin/approval/edit.blade.php`, `resources/views/crm/enquiries/form.blade.php`, `resources/views/crm/site-visits/form.blade.php` | Wording follows the parts that are on (submit confirmation, email note, approval caption, enquiry delete note, placeholders). |

### Contact / POC

| File | Change |
|---|---|
| `app/Http/Controllers/Crm/ContactController.php` | New. List (server paging, search, sort, client filter), create / show / store / update / destroy, `box()` for the enquiry and prospect pages. |
| `app/Http/Requests/Crm/ContactRequest.php` | New. Rules with Page setup; customer or prospect, not both. |
| `app/Services/ContactService.php` | New. Transactions, Contact ID from the row id, client row locked, activity log on add, change and delete. |
| `resources/views/crm/contacts/index.blade.php`, `resources/views/crm/contacts/form.blade.php`, `resources/views/crm/partials/contacts.blade.php` | New screens and the Contacts / POC box. |
| `app/Http/Controllers/Crm/EnquiryController.php`, `app/Http/Controllers/Crm/ProspectController.php`, `resources/views/crm/prospects/form.blade.php` | Pass and show the client's contacts. |

### Tests and docs

| File | Change |
|---|---|
| `tests/Feature/PageSetupAndModulesTest.php` | New, 15 tests: every page in Page setup and a page added by config only; hidden fields kept and mandatory fields enforced on site visit, quotation, PO, prospect (page, drawer, import), employee and masters; fixed and show-only fields; rights and logging; no query for the settings once cached; each switch off and back on (pages refused, menu, bell, workflow, Access rights, Page setup, lead report, documents, email, approval without costs) with data kept; role and user rights kept; contacts added, numbered, searched, paged, shown on enquiry and prospect, rights and Page setup. |
| `docs/ARCHITECTURE.md` | New section and decisions M1 to M5, C1, C2. |
| `docs/DATABASE.md` | New tables, keys, ON DELETE rules and the migration run. |

## For the reviewers and the tester

- Every new route checks a permission; site visit routes also check the switch. Every change writes its activity log row in the same transaction.
- The Contacts list pages on the server (25 a page, 50 or 100 on choice), keeps page, search and sort in the address, and every search is "starts with" on an indexed column.
- The settings are cached until saved, so they add no query per page for 5,000+ users; in production the cache is Redis (`docs/PRODUCTION.md`).
- Not changed: quotation import still reads every header column (Page setup applies to the quotation page only); prospect import follows Page setup.
