# Page setup, optional parts and Contact / POC: review fixes

Developer report, 15/10/2026. It covers the four reviews of `page-setup-and-modules-report.md`: architecture, code, UX and database.

**Result:** all 5 must-fix items are fixed. I also took 22 of the 29 suggestions; the other 7 are listed below with the reason, together with one part of P1 I did differently.

**Tests:** 125 pass (1,565 assertions). That is the 115 from before plus 10 new ones in `tests/Feature/PageSetupReviewFixesTest.php`. Six existing assertions were changed:
- "Not used" is now "Cannot be mandatory".
- Cost details is now checked by its box id, not its name.
- Four Contact ID checks now work out the ID from the row id.

The same 125 also pass on a throwaway MariaDB 10.4 database (`nursery_test_tmp`, dropped afterwards).

**Database:** no migration this round, so "nursery" was not changed and needed no backup.

**XAMPP:** MySQL was stopped, so I started it the way `mysql_start.bat` does for the MariaDB run and the screen check. I stopped it again afterwards. `.env` still points at "nursery", the app caches are cleared, and no test server is left running.

**Screen check:** I used a throwaway copy, `nursery_ps_check2`, dropped afterwards.
- Unticking Site visit and pressing Save asks first, naming the part and what everyone loses. Cancel leaves the page as it was.
- Accepting saves the change. The toast reads "Site Visit / Requirement Gathering switched off · BOQ Preparation and Cost details (costing) on".
- The Site visit row in the page list turns grey.
- An old site visit link shows "Site Visit / Requirement Gathering is switched off", with Go to Enquiry.

## Must-fix (5 of 5 fixed)

| # | Item | Fix | Test |
|---|---|---|---|
| Arch P1 | Switches checked screen by screen; `enquiries.lookup` still let a site-visit-only user in | The switch is now part of authorisation, in one place (`AppServiceProvider.php`). Three gate checks run in this order: (1) any right on a page whose `config('nursery.pages')` entry has a `module` that is off is refused for every role, Admin included (`Access::moduleOf`); (2) Admin passes; (3) the permission package's own check, which is registered here so it runs third (`config/permission.php` `register_permission_check_method` is now false). Routes, the menu, `@can`, the bell, lookups and documents all follow it. The extra checks in `Menu` and `CrmAlerts` were removed. Future Sales, Purchase and Accounts pages tagged with a `module` get this with no code of their own. | `test_p1_…` walks every route that names a site visit right, with Site visit off. Site-visit-only routes answer 404 "switched off", including for Admin. Routes that also accept another right answer 403 to a user who holds only site visit rights. That includes the enquiry search, which answers again once the switch is back on. |
| Code M1 | Hiding "Lead assigned to" stopped the red pop-up for staff linked to an employee | `EnquiryService::create`: when the field is not sent, the lead goes to the creator's active employee record. An edit keeps the saved assignee. | `test_m1_…`: the field is hidden, a linked Sales user adds a lead, and it shows in their due list. |
| UX M1 | Switching a part off happened with no warning | The Save button asks first. The question names each part being switched off and what everyone loses (config `modules.*.off`); Cancel changes nothing. Each part's description now says "When off: …". The toast states the new state of every part. | `test_ux_m1_…`, plus the browser check above. |
| UX M2 | No way back to the enquiry or prospect after adding a contact | Add contact and the contact rows in the enquiry and prospect boxes now carry `from=enquiry:12` or `from=prospect:7`. The contact page shows "Back to ENQ/…", and Save goes back there to the Contacts box with the toast. Only those two kinds of origin are accepted, and only for a record the user may open, so the link can never send anyone to another site. | `test_ux_m2_…`: back to the enquiry on add and edit, back to the prospect, and four bad `from` values are ignored. |
| UX M3 | A switched-off part showed "Page not found … record was deleted" | New page `errors/switched-off`: "<Part> is switched off. Saved entries are kept…", with a Go to Enquiry button. It is used by the route switch and by refused BOQ, costing and site visit documents. The switch now runs before the record is looked up (middleware priority), so an old link always says this. A genuinely missing record still says "Page not found". | `test_ux_m3_…` |

## Suggestions taken (22)

| # | What was done |
|---|---|
| Arch S1 | A save writes the new values into the cache after commit (`Cache::put`). The switch and Page setup cache entries now last at most an hour (`ModuleSettings::CACHE_TTL`). Redis `maxmemory-policy volatile-lru` is added to `docs/PRODUCTION.md`. |
| Arch S3 | The "adding a page to Page setup" checklist is now in `docs/ARCHITECTURE.md`. A test fails if a page in `page_fields` has no `rules()` call. |
| Arch S4 | While costing is off, cost lines are left out of the quotation's activity log and history. |
| Arch S5 | Quotation import now follows the quotation Page setup: hidden columns are not read and mandatory ones are checked. |
| Arch S6 | Decision M2 now says: one installation and database per client, not multi-tenant. |
| Code S1 | Switching Cost details on or off also needs Approval · Edit. Without it the tick is locked and the server refuses the change. |
| Code S2 | Every part must be sent on save, so a request that leaves one out cannot switch it off. |
| Code S3 | Deleting an enquiry that has site visits, while site visits are off, now says how to reach them. The lookups no longer accept site visit rights while the part is off (P1). |
| Code S4 | A new contact cannot be saved against an inactive customer. The one already saved on a contact stays allowed. |
| Code S5 | Decision C3 is added: contacts are shared across units, like prospects. |
| Code S6 | Tests added for: the hidden assignee, a non-Admin with Page setup Edit (including the costing rule), a save with a part missing, an inactive customer and a deleted prospect. A save with both customer and prospect was already covered. |
| UX S2 | A page of a switched-off part stays in the Page setup list, greyed: "Switched off with … in Optional parts above". |
| UX S3 | The Mandatory dash now reads "Cannot be mandatory" to screen readers. |
| UX S4 | Unticking Show remembers the Mandatory tick and gives it back when Show is ticked again. |
| UX S6 | Fields are listed in form order on Contact and Employee master (Page setup screens and the "Mandatory: …" line). |
| UX S7 | The Contacts list and the Contacts / POC box leave out columns whose field is hidden in Page setup. |
| UX S8 | Add contact from an enquiry whose client has no contacts yet starts with the enquiry's contact name and no. |
| UX S10 | Each part's description says what users lose when it is off. |
| UX S11 | One label for the box link: "All contacts of this client". |
| DB PS-S1 | Contacts search now looks up matching client ids first (at most 200 of each) and passes them as plain id lists, so every part of the search uses an index. |
| DB PS-S3 / PS-S4 | PS-S3: deleting a prospect soft deletes its contacts in the same transaction and lists them in the log. PS-S4: the first save of the switches is a single insert-or-update, and the old values are read with the rows locked, so two admins saving at once cannot clash. |

## Suggestions not taken (7), and why

| # | Why not now |
|---|---|
| Arch P1, second half (not a suggestion; done differently): pass views a flag instead of `@module` in Blade | The `@module` directive reads the same cached service. Flags passed from each controller would add code on every page and repeat the same lookup. |
| Arch S2 (each part lists its own pages, menu items and document types in config) | This is for when Sales, Purchase or Accounts bring optional parts of their own. The three CRM parts are already tagged in config, and P1 covers authorisation. |
| UX S1 and S5 (phone layout of the Optional parts ticks and Page setup tables) | These are layout changes to shared table styles. They go to the "Build the HTML design file" thread first, so the design and the app stay the same. |
| UX S9 (field-specific error messages for every Page setup field) | Needs a message per field on every page. It is worth doing as one consistent pass over all forms, not only the new ones. |
| DB PS-S2 (CHECK constraint "customer or prospect, never both") | Needs a migration on "nursery" for a rule the Form Requests and services already enforce. It is best done together with the same rule for `enquiries` (CRM review S3). |
| DB PS-S5 (generated Contact ID column) | The reviewer marked it optional. Keeping it written by the app lets a later client use another prefix. |
| DB PS-S6 (simplePaginate for contacts) | The reviewer said it is fine at today's size. Revisit when contacts reach a few hundred thousand. |

## Files changed

- **Authorisation and switches:** `app/Providers/AppServiceProvider.php`, `config/permission.php`, `app/Support/Access.php`, `app/Support/Menu.php`, `app/Services/CrmAlerts.php`, `app/Support/Modules.php` (`refuse()`), `app/Http/Middleware/EnsureModuleIsOn.php`, `bootstrap/app.php` (middleware priority), `app/Http/Controllers/Crm/DocumentController.php`.
- **Switch screen:** `app/Services/ModuleSettings.php` (upsert, locked read, cache put with TTL), `app/Http/Requests/Admin/ModuleSettingsRequest.php` (every part required, costing needs Approval · Edit), `app/Http/Controllers/Admin/PageSetupController.php` (toast, greyed pages), `resources/views/admin/page-setup/index.blade.php` (confirmation, "When off", locked costing tick, greyed rows), `resources/views/admin/page-setup/edit.blade.php` (screen-reader text, remembered Mandatory), `config/nursery.php` (`modules.*.off`, field order).
- **Page setup cache:** `app/Services/PageSetupService.php`.
- **Errors:** `resources/views/errors/switched-off.blade.php` (new), `resources/views/errors/layout.blade.php` (extra links).
- **Enquiry, quotation and prospect:** `app/Services/EnquiryService.php` (assignee, delete message), `app/Services/QuotationService.php` (cost log, import), `app/Services/ProspectService.php` (contacts go with the prospect).
- **Contacts:** `app/Http/Controllers/Crm/ContactController.php` (origin, prefill, search), `app/Http/Requests/Crm/ContactRequest.php` (active customer), `app/Http/Controllers/Crm/EnquiryController.php`, `app/Http/Controllers/Crm/ProspectController.php`, `resources/views/crm/partials/contacts.blade.php`, `resources/views/crm/contacts/form.blade.php`, `resources/views/crm/contacts/index.blade.php`.
- **Tests:** `tests/Feature/PageSetupReviewFixesTest.php` (new), `tests/Feature/PageSetupAndModulesTest.php` (the changed assertions listed under Tests).
- **Docs:** `docs/ARCHITECTURE.md` (after-the-reviews section, checklist, decisions M2, C3, C4), `docs/PRODUCTION.md` (Redis eviction policy).

## After the detailed test (15/10/2026): S1

**Item:** with BOQ switched off, a quotation's "Recent changes" box still showed BOQ uploads, for example "Uploaded BOQ …pdf".

**Fix:** every record's Recent changes box now uses `ActivityLog::history()`. That covers enquiry, site visit, quotation, PO, prospect, contact, employee, user and role. While a part is off, the box leaves out:
- BOQ and Costing approval document uploads and deletes, while BOQ or costing is off;
- site visit entries, while site visits are off;
- quotation changes that touched only cost details, while costing is off.

All of them show again when the part is switched back on. Admin › Activity log still lists every entry, because it is the audit trail of who did what.

**Test:** `test_detailed_s1_recent_changes_hide_boq_cost_and_site_visit_rows_while_off`. 126 tests pass on the test database (SQLite). MySQL was stopped and stays stopped, so this round did not run on MariaDB. The only new query is a `NOT LIKE` filter, which MariaDB handles the same way. No migration, and `.env` still points at "nursery".
