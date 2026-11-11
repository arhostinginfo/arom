# CRM changes: what was built and where

Request: Sat's Word file "CRM changes.docx" (13/10/2026), copied as `crm-changes-request.md`.
App: `D:\xampp\htdocs\nursery-app` (Laravel 12, MariaDB 10.4). Paths below are relative to the app folder; line numbers are where the change starts.

**Status:** every point of the Word file is built except "Contact / POC Fields – need to discuss" (see "Not done"). 76 tests pass (58 existing, updated where the quotation flow changed, plus 18 new). The four new migrations ran on "nursery" after a backup; no existing column or row was changed or deleted.

## 1. Nursery end-to-end workflow

New lead → Site visit → BOQ + costing + quotation → Approval → Quotation sent → Follow-up → Quotation (version) → PO entry.

- **Screen:** CRM menu now lists Enquiry, Site visit, Quotation, PO / Agreement, Prospect in that order. The Enquiry page shows a workflow bar (Lead, Site visit, BOQ/costing/quotation, Approval, Quotation sent, PO) with links to open or add the next step; Site visit has "Make quotation"; an accepted quotation has "Enter PO / agreement".
- **Files:** `config/nursery.php:91` (menu), `config/nursery.php:27` (permissions), `app/Support/CrmFlow.php` (steps), `resources/views/crm/partials/flow.blade.php`, `app/Http/Controllers/Crm/EnquiryController.php:197`, CSS `public/css/app.css:172`.

## 2. New Enquiry: fields and Page setup (show/hide, mandatory/optional)

- **Screen:** CRM › Enquiry (`/crm/enquiries`), entry page with Entry selector, Add new, Enquiry list, follow-ups and documents. All 23 fields of the Word table. Enquiry ID is system generated (`ENQ/26-27/0001`, per unit and financial year). Mandatory as in the Word file.
- **Screen:** Admin › Page setup (`/admin/page-setup`, `/admin/page-setup/enquiry`): Show and Mandatory ticks per field. Enquiry date and Company / Client name always show and are mandatory; a hidden field cannot be mandatory and keeps its saved value.
- **Screen:** CRM › Enquiry list (`/crm/enquiries/list`): All, Open leads, Follow-ups due, Hot leads, Closing this month, PO received, Lost; search and sort; paged on the server.
- **Files:** `app/Models/Enquiry.php`, `app/Services/EnquiryService.php`, `app/Http/Requests/Crm/EnquiryRequest.php:39` (rules built from Page setup), `app/Http/Controllers/Crm/EnquiryController.php` (list at :100), `resources/views/crm/enquiries/form.blade.php`, `list.blade.php`, `crm/partials/prospect-drawer.blade.php`; Page setup: `config/nursery.php:165`, `app/Services/PageSetupService.php:19` and `:54`, `app/Models/PageSetting.php`, `app/Http/Requests/Admin/PageSetupRequest.php`, `app/Http/Controllers/Admin/PageSetupController.php`, `resources/views/admin/page-setup/index.blade.php`, `edit.blade.php`; enums `app/Enums/ProspectType.php`, `LeadRating.php`, `EnquiryStatus.php`; routes `routes/web.php:93`, `:67`.

## 3. Source master, Category master, Employee master

- **Screen:** Admin › Masters (`/admin/masters`) now real: Source master, Category master (the Enquiry's Service type), Industry type master, City master, Employee master. Masters of later modules still open their sample screens from the same page.
- Source and Category lists are seeded exactly as in the Word file; Industry type with Corporate, Mall, Hotel, Hospital, Builder, Other; City with 17 common cities.
- Each list: search, sort, paging, add, edit, make inactive, delete (refused when in use). Employee master: code, name, designation, department, mobile, email, date of joining, address, login user, status.
- **Files:** `app/Models/SimpleMaster.php`, `LeadSource.php`, `ServiceCategory.php`, `IndustryType.php`, `City.php`, `Employee.php`; `app/Services/MasterService.php:25`; `app/Http/Requests/Admin/MasterRequest.php`, `EmployeeRequest.php`; `app/Http/Controllers/Admin/MasterController.php`, `EmployeeController.php`; `resources/views/admin/masters/index.blade.php`, `list.blade.php`, `admin/employees/index.blade.php`, `form.blade.php`; type-ahead `app/Http/Controllers/LookupController.php` (employees, login users); routes `routes/web.php:53`.

## 4. Contact / POC fields: not built (see "Not done")

The Enquiry keeps Contact name and Contact no as the Word table asks; Site visit has a POC text field; Quotation and PO have a Contact field.

## 5. Site visit / requirement gathering

- **Screen:** CRM › Site visit (`/crm/site-visits`) and Site visit list (`/crm/site-visits/list`, views All, Visits due, Planned, Done). Fields: Client (enquiry), visit date and time, POC, technical person (employee), site address, area/measurements, floors, indoor/outdoor area, existing plants, existing planters, lighting condition, water availability, access restrictions, customer requirement, remarks, next action, status. Photos as documents (camera on a phone).
- **Files:** `app/Models/SiteVisit.php`, `app/Services/SiteVisitService.php`, `app/Http/Requests/Crm/SiteVisitRequest.php`, `app/Http/Controllers/Crm/SiteVisitController.php`, `resources/views/crm/site-visits/form.blade.php`, `list.blade.php`; routes `routes/web.php:103`.

## 6. BOQ preparation (upload optional, sent by mail with the quotation)

- **Screen:** Quotation page, "BOQ and attachments" box (type BOQ by default). "Email quotation" sends the PDF and every BOQ file of that revision.
- **Files:** `resources/views/crm/partials/documents.blade.php`, `app/Services/DocumentService.php:22`, `app/Jobs/SendQuotationMail.php`, `app/Mail/QuotationMail.php`, `resources/views/mail/quotation.blade.php`, `app/Services/QuotationService.php:248`.

## 7. Cost details (internal)

- **Screen:** Quotation page, "Cost details · Internal · not printed": purchase/material, plant production, planter, labour, transportation, installation, maintenance, other; Total cost, Margin %, Margin amount, Proposed selling price worked out live and again on save. Never printed on the PDF or email. Copied to a new revision.
- **Files:** `app/Models/QuotationCost.php`, `app/Services/QuotationService.php:494`, `resources/views/crm/quotations/form.blade.php:209`, `public/js/crm.js:48`.

## 8. Approval workflow (amount criteria)

- **Screen:** Quotation status bar: "Submit for approval" (costing + BOQ + quotation with Rev no). At or below the limit it is approved at once; above it goes to Internal Approval, and approvers see Approve and Reject (reason required, back to Draft with the reason). Approval history on the quotation.
- **Screen:** Admin › Approval (`/admin/approval`) now real: Approval required Yes/No, Approve only above (₹), approvers shown. Approvers are roles with the new permission "Quotation approval · Edit" (Admin always).
- **Files:** `app/Services/QuotationService.php:175` (submit), `:203` (approve), `:215` (reject); `app/Models/ApprovalRule.php:28`, `app/Models/Approval.php`; `app/Services/ApprovalSettingsService.php`; `app/Http/Requests/Admin/ApprovalRuleRequest.php`, `Crm/QuotationActionRequest.php`; `app/Http/Controllers/Admin/ApprovalController.php`; `resources/views/crm/quotations/status.blade.php`, `admin/approval/edit.blade.php`; routes `routes/web.php:150`, `:71`.

## 9. Quotation header, lines and status

- **Screen:** CRM › Quotation. Header: Enquiry (gives the client and enquiry no), Quotation no, Rev no, Contact, Site, Date, Valid until, Sales person, Prepared by (automatic), Currency, Payment terms, Delivery / service terms, GST / tax, Discount, Notes, Attachments. Lines: Item no, Item description, Qty, Unit, Rate, Discount %, Tax %, Total; items total, discount and total worked out live and on save.
- Status: Draft → Internal Approval → Approved → Sent (Email quotation or Mark as sent) → Customer Viewed/Feedback → Negotiation → Accepted / Rejected / Expired / Hold (reason needed for the last three). Only a Draft can be edited; Revise makes the next version as a new Draft.
- PDF, CSV download and CSV import updated to the new fields. New Quotation list (`/crm/quotations/list`: All, Pending approval, Verbal wins / PO pending, With the customer).
- **Files:** `app/Models/Quotation.php`, `QuotationLine.php:38`, `app/Enums/QuotationStatus.php`, `app/Services/QuotationService.php` (make :438, saveLines :467, revise :114, import :287, setCustomerStatus :257), `app/Http/Requests/Crm/QuotationRequest.php:86`, `app/Http/Controllers/Crm/QuotationController.php`, `resources/views/crm/quotations/form.blade.php` (items :178), `status.blade.php`, `list.blade.php`, `resources/views/pdf/quotation.blade.php`, `public/js/crm.js:31`.

## 10. Follow-up & activity management (red pop up)

- **Screen:** Enquiry page, "Follow-ups" box: Call, E-mail or WhatsApp, date, details, next action and target date; history paged. The next action becomes the enquiry's next action. When its date comes it shows in red on the enquiry, the lists and the dashboard, and a red "Follow-ups due" pop up opens on any page (once per browser session for the same list). Users linked to an employee see their own leads; Admin and unlinked users see the unit's.
- **Files:** `app/Models/Activity.php`, `app/Services/EnquiryService.php:92`, `app/Http/Requests/Crm/ActivityRequest.php`, `resources/views/crm/enquiries/followups.blade.php`, `app/Services/CrmAlerts.php:50`, `resources/views/layouts/app.blade.php:65`, `public/js/app.js:28`.

## 11. PO / Agreement received

- **Screen:** CRM › PO / Agreement (`/crm/pos`): Quotation (gives client, enquiry, site, value and terms), PO / Agreement no, PO date, site, PO value, PO quantity, validity start and end, payment terms, GST / tax, customer contact, received by, received date, remarks; PO document and agreement document uploads. Saving marks the enquiry "PO received"; deleting the last PO opens it again.
- **Files:** `app/Models/CustomerPo.php`, `app/Services/CustomerPoService.php:27`, `app/Http/Requests/Crm/CustomerPoRequest.php`, `app/Http/Controllers/Crm/CustomerPoController.php`, `resources/views/crm/customer-pos/form.blade.php`; routes `routes/web.php:112`.

## 12. Documents & attachments

- **Screen:** a Documents box on Enquiry (enquiry documents, visiting cards, requirement emails), Site visit (site photos), Quotation (BOQ, costing approval, quotations and revisions) and PO (customer PO, agreement). Each row shows uploader, date/time and belongs to its record. Files are kept on the private disk and opened through the app with the record's permission and unit checked; up to 10 MB, PDF, images, Word, Excel, CSV, text, email files. Uploads and deletes are logged.
- **Files:** `app/Models/Document.php`, `app/Services/DocumentService.php`, `app/Http/Requests/Crm/DocumentRequest.php`, `app/Http/Controllers/Crm/DocumentController.php`, `resources/views/crm/partials/documents.blade.php`, `config/nursery.php:194`; routes `routes/web.php:121`.

## 13. Notifications & alerts

- **Screen:** bell on the top bar with a count and a menu: Follow-ups due (red pop up too), Site visits due, Quotations pending approval (approvers only), PO pending. Each opens its filtered list.
- **Files:** `app/Services/CrmAlerts.php:58`, `app/Providers/AppServiceProvider.php:79` (shared with every page) and `:52` (refresh when records change), `resources/views/layouts/app.blade.php:35`, `public/js/app.js`, CSS `public/css/app.css:210`.

## 14. Dashboards

- **Screen:** Dashboard (`/dashboard`, was a sample screen) for the unit on the top bar and the current financial year: tiles Enquiry vs PO received (with conversion %), Total open leads, Hot leads, Activities due/overdue, Verbal wins / PO pending, Expected closing this month; month-wise chart of enquiries and PO received; lead ageing bars; pie charts of enquiries by source and by category; top-10 lists of hot leads, activities due, expected closing and PO pending, each with "View all".
- **Reports › CRM:** new "Lead ageing report" and "Lead report (lead to PO)" (site visits, follow-ups, current quotation and status, PO), CSV or PDF. The "Enquiries" report now lists the enquiries with every field (filters Status and Lead rating instead of Followup status and Sales person).
- **Files:** `app/Services/CrmDashboard.php:36`, `app/Support/Charts.php`, `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`, `partials/chart-pie.blade.php`, `chart-bars.blade.php`; `app/Http/Controllers/Reports/CrmReportController.php` (enquiries :150, ageing :169, lead report :190), `resources/views/reports/crm.blade.php`; `config/nursery.php:126`; routes `routes/web.php:89`, `:163`.

## Other changed files

- `app/Support/Menu.php:62`: top-level menu items can open a real route (Dashboard).
- `app/Providers/AppServiceProvider.php:36`: short morph names for the new records.
- `app/Services/ProspectService.php:68`: a prospect used on an enquiry cannot be deleted.
- `app/Models/ActivityLog.php`: labels for the 24 new log actions.
- `app/Support/Dates.php`: dd/mm/yyyy to database dates.
- `resources/views/crm/partials/entrybar.blade.php`: list links without the download attribute.
- `public/css/app.css:172` onwards: styles for the new screens. Also fixes the "Recent changes" timeline, whose text was squeezed into the dot column on every entry page.
- Docs: `docs/DATABASE.md` (new section at the end), `docs/ARCHITECTURE.md` (CRM changes section).

## Database changes

Backup before migrating: `storage/app/backups/nursery-before-crm-changes-20261013.sql` (mysqldump of "nursery"). "nursery" had no prospects, quotations or enquiries, so no business data was affected.

| Migration | Adds |
|---|---|
| `2026_10_13_000001_create_crm_masters_tables` | `lead_sources`, `service_categories`, `industry_types`, `cities` (seeded), `employees`, `page_settings`, `approval_rules` (quotation: required above ₹1,00,000) |
| `2026_10_13_000002_create_enquiries_site_visits_activities_tables` | `enquiries`, `site_visits`, `activities` |
| `2026_10_13_000003_add_crm_quotation_fields` | new columns on `quotations` and `quotation_lines`; `quotation_costs`; `approvals` |
| `2026_10_13_000004_create_customer_pos_and_documents_tables` | `customer_pos`, `documents`; the new permissions (enquiry, site-visit, quotation-approval, customer-po) and the CRM ones given to the existing Sales role |

Keys, indexes and ON DELETE rules are listed in `docs/DATABASE.md`. Enquiry numbers use the existing locked `NumberSeries` (ENQ). A quotation copies its enquiry's ENQ number and takes its own QT number from `NumberSeries`, as before; it no longer takes a new ENQ number of its own. A later migration (`2026_10_14_000001`) added indexes only, after the database review.

## Tests

`php artisan test` on the in-memory SQLite test database: **76 passed, 934 assertions**.

- New `tests/Feature/CrmChangesTest.php` (18 tests): seeded masters; master add/edit/delete and in-use rules; employee master; enquiry numbers and mandatory fields; Page setup hide/mandatory; follow-ups and the red pop up (own leads only for a linked employee); alert counts; site visit with photo; quotation totals and costing, PDF without costing; BOQ upload checks and document permissions across roles and units; approval below and above the limit, reject with reason, double approve refused; costing needed and the Admin rule; email job with BOQ, customer statuses with reasons, lead Lost/Open, Revise copying costing; PO against an accepted quotation, PO pending, delete rules; dashboard figures; lead ageing and lead report downloads; permissions and other units; server-side paging.
- Helper `tests/CrmFixtures.php`. Existing `CrmTest`, `FixRound1Test`, `FixRound2Test` updated for the enquiry-first quotation (customer-or-prospect and deleted-prospect checks moved to the enquiry; the "no Decision wording" test now also covers the 21 new pages).

Browser check: on a throwaway copy of "nursery" (`nursery_crm_check`, served at `127.0.0.1:8123`, deleted afterwards), at 1366 px and 375 px: Dashboard, red pop up, Enquiry, Enquiry list, Site visit, Quotation (status bar, totals, cost details, approval history, BOQ box), PO, Masters, Page setup. No console errors. `http://localhost/nursery-app` answers normally.

## Not done

- **Contact / POC fields (Contact ID, designation, department, mobile, WhatsApp, email, preferred communication, decision maker, influencer and the other roles):** the Word file marks this section "need to discuss", so no contact master was built. The enquiry, site visit, quotation and PO each keep a contact as text.

## Choices made

1. An enquiry is the lead; a quotation is always made from an enquiry, copies its client and ENQ number and has its own QT number (the old Customer / Prospect picker moved to the enquiry). Quotation import now needs an "Enquiry no" column.
2. Company / Client name is a Prospect (new client, with "Add" in place) or an existing Customer from the Party master; one of the two.
3. "Contact NO/NAME" is two fields, Contact name and Contact no, both mandatory together; Page setup treats them as one field. The same for Next action and its date.
4. Competitor details (no Mandatory value in the Word table) is optional by default.
5. Service type uses the Category master. Industry type and City are masters too, so Admin can extend "etc." and add cities; 17 common cities are seeded.
6. "Lead assign to", technical person and PO received by pick from the Employee master. An employee can be linked to a login user, so their own follow-ups show in their red pop up.
7. Enquiry status is set by the system: Open, PO received (when a PO is saved), Lost (quotation Rejected or Expired).
8. Enquiries show across financial years for the unit on the top bar, because a lead can run past 31 March; the number still restarts each year.
9. Line total = Qty × Rate − Discount % + Tax %. Header Discount is an amount off the items total; header GST / tax is text for the terms block.
10. Approval: one level; default limit ₹1,00,000 (Admin › Approval can change it or require approval for all). An internal rejection returns the quotation to Draft with the reason. Approvers are roles with the new "Quotation approval · Edit" permission; Admin always.
11. Cost details must be entered before submitting for approval.
12. Only a Draft can be edited; after submitting, changes go through Revise (new version as a Draft, costing copied).
13. "Customer Viewed/Feedback" is one status, as written. "Expired/hold" became two statuses, Expired and Hold.
14. A PO is entered against a quotation that was sent; the table is `customer_pos`, so it does not clash with the planned supplier purchase orders.
15. Quotation emails go through the queue. Production needs `php artisan queue:work`; on Sat's PC `MAIL_MAILER=log` writes the mail to the log, so nothing leaves the PC until mail is set up.
16. The red pop up shows once per browser session for the same list of due follow-ups; the bell always shows the counts.
17. Dashboard and the unit-wide alerts are cached once per unit for up to a minute (served stale while one request refreshes, at most 5 minutes old); follow-ups due are cached per employee and refresh as soon as one of their leads changes (changed after the architecture and database reviews).
18. Old quotation columns (Followup status, Source of enquiry, Reference, Project name, annual volume, sample/production dates, technical details) are kept in the database but no longer on the form; existing quotations got a Status from their Followup status.

These go into the decisions register (`design/05-decisions-register.md`) via the design thread.

## Screens the HTML design file and prototype need to match

New: Dashboard (CRM tiles, charts, lists); CRM › Enquiry (with workflow bar, follow-ups, documents); Enquiry list; Site visit; Site visit list; Quotation list; PO / Agreement; red "Follow-ups due" pop up and the bell menu; Admin › Masters (real index), the simple master page, Employee master list and form; Admin › Page setup list and Enquiry field setup; Admin › Approval (quotation rule); Reports › CRM › Lead ageing report and Lead report.
Changed: CRM › Quotation (header fields, items with Qty/Unit/Discount %/Tax %/Total, cost details, status and approval bar, Email dialog, Reject dialog, BOQ box); Quotation PDF; Reports › CRM › Enquiries filters; CRM menu order.
