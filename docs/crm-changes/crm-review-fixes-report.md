# CRM changes: fixes after the four reviews

Fixes made on 14/10/2026 for `architecture-review-crm.md`, `code-review-crm.md`, `ux-review-crm.md` and `database-review-crm.md`. Paths are relative to `D:\xampp\htdocs\nursery-app`; line numbers are where the fix starts.

**Result:** all 19 must-fix items are fixed. Two pairs overlapped and were fixed once each: the Documents box paging (architecture C3 and code M4), and the bell and dashboard cache (architecture C1 and database DB-C2). That leaves 17 separate fixes. The code review's missing tests are added, plus a test for each fix.

**Tests:** `php artisan test` gives **89 passed, 1,074 assertions**. That is 76 before plus 13 new in `tests/Feature/CrmReviewFixesTest.php`. The same 89 also pass on a throwaway MariaDB 10.4 database (`nursery_test_tmp`, dropped afterwards), which covers the earlier review's point that the suite ran on SQLite only.

**Database:** "nursery" was backed up to `storage/app/backups/nursery-before-crm-review-fixes-20261014.sql`. After that, `2026_10_14_000001_add_crm_list_and_dashboard_indexes` ran on it. That migration only adds indexes.

**Screens:** checked on a throwaway copy (`nursery_crm_check2`, dropped afterwards) at 1366 px and 375 px:
- the red pop-up keeps Tab inside it;
- the bell menu works with Enter, the arrow keys and Escape, and focus goes back to the bell;
- the unsaved-changes prompt appears before a follow-up is saved;
- the Reject dialog shows its error;
- "day(s) late" text appears in the Enquiry list;
- quotation items are cards on a phone, with the totals visible.

`http://localhost/nursery-app` answers normally.

## Architecture review

| # | What changed, file by file | Test |
|---|---|---|
| **C1** Bell and dashboard cache emptied on every save (same as DB-C2) | `app/Providers/AppServiceProvider.php`: the per-save `bump` listeners were removed. `app/Services/CrmAlerts.php`: rewritten. The three unit-wide counts are cached once per unit with `Cache::flexible(…, [60, 300])` (`unitCounts`, :123). Follow-ups due (count, overdue count, first 10) are cached per employee, or per unit for Admin and unlinked users (`due`, :71). `forgetDue` (:113) clears only those keys, after commit. `app/Services/EnquiryService.php:49` onward calls `forgetDue` on create, update, delete, follow-up and status change. `app/Services/CrmDashboard.php:36`: `Cache::flexible` per unit and year, with no version key. `docs/ARCHITECTURE.md` (CRM section) is updated. | `CrmReviewFixesTest::test_c1_a_saved_follow_up_shows_at_once_and_the_dashboard_matches_the_list` |
| **C2** Files on one server's disk | `config/nursery.php:195`: `documents_disk` from `DOCUMENTS_DISK` (default `local`). `app/Services/DocumentService.php:27`: `diskName()` and `disk()` replace the `DISK` constant. `app/Http/Controllers/Crm/DocumentController.php:58`: on an S3 disk, download redirects to a 5-minute `temporaryUrl`. `app/Mail/QuotationMail.php:39` reads the same disk. `docs/PRODUCTION.md`: new section "CRM: documents, mail and queue" (bucket, `.env` values, queue worker, mail). | `test_c2_documents_go_to_the_configured_disk` |
| **C3** Documents box loads every document (same as code M4) | See code M4. | `test_m4_documents_box_pages_on_the_server` |

## Code review

| # | What changed, file by file | Test |
|---|---|---|
| **M1** USD or EUR quotations skip the rupee limit | `app/Models/ApprovalRule.php:32`: `needsApproval($amount, $inRupees)`; a quotation in another currency always needs approval. `app/Services/QuotationService.php:196` passes the currency. The toast says so (`app/Http/Controllers/Crm/QuotationController.php:108`). So do the status bar (`resources/views/crm/quotations/status.blade.php`) and the Admin › Approval hint (`resources/views/admin/approval/edit.blade.php`). | `test_m1_approval_limit_boundary_and_other_currencies_with_a_real_approver`: exactly ₹1,00,000 is approved at once, ₹1,00,000.01 waits, USD 500 waits; it uses a non-Admin Approver role and a Sales user who gets 403. |
| **M2** PO against a Draft, unapproved or earlier revision | `app/Services/CustomerPoService.php:136`: `lockQuotation` takes `lockForUpdate` and requires the current revision with a status the customer has (`canTakePo`, :150), otherwise it gives a field error. `app/Http/Controllers/Crm/CustomerPoController.php:141`: `?quotation=` fills the form only for such a quotation. Moving a PO to another enquiry's quotation re-checks the old enquiry (`CustomerPoService.php:63`). | `test_m2_po_is_refused_for_draft_pending_and_earlier_revision`, `test_db_c1_lead_status_follows_the_pos_and_quotations` |
| **M3** BOQ swapped after approval | `app/Services/DocumentService.php:41`: `canChange()` allows quotation uploads and deletes only on the current revision while it is a Draft. `upload` and `delete` refuse anything else. `resources/views/crm/partials/documents.blade.php:7` hides upload and delete and explains why. Revise copies the BOQ into the new Draft as its own file (`DocumentService::copyCategory` :103, called at `QuotationService.php:144`). This also takes code suggestion S4. | `test_m3_quotation_documents_change_only_on_the_current_draft_and_revise_copies_the_boq` |
| **M4** Documents box unpaged | `app/Services/DocumentService.php:52`: `box()` returns `simplePaginate(10, 'dpage')`, newest first, and a `count()`. It is used by `EnquiryController.php:201`, `SiteVisitController.php:132`, `QuotationController.php:350` and `CustomerPoController.php:113`. `resources/views/crm/partials/documents.blade.php` shows the total and Previous / Next. | `test_m4_documents_box_pages_on_the_server` (12 documents: 10 on page 1, 2 on page 2) |

## UX review

| # | What changed, file by file | Test |
|---|---|---|
| **M1** Unsaved changes lost when another form is sent | `public/js/app.js:95`: when a different form is submitted while one has unsaved changes, it asks first, stops if the user cancels and focuses that form's Save button. | Browser check on the copy (the prompt appeared and the page stayed); `test_ux_reject_error_due_text_city_picker_items_and_menus` checks the code is in place. |
| **M2** Focus leaves the pop-up and dialogs | `public/js/app.js:36`: one handler for every open overlay (red pop-up, Reject, Email, Import, Add prospect). Tab and Shift+Tab wrap inside, and focus that lands behind is brought back. | Browser check: 5 Tabs stayed in the pop-up and returned to Close. |
| **M3** Bell menu not keyboard-friendly | `public/js/app.js:20`: menu items get `role="menuitem"`, the first item is focused, Up and Down move, and Escape or Tab closes and returns focus to the opener. This also covers the Unit and profile menus. `resources/views/layouts/app.blade.php:59`: `role="menuitem"` on the bell items. | Browser check (Enter, then focus on "Follow-ups due", ArrowDown, then "Site visits due", Escape, then back on the bell); the HTML is asserted in the UX test. |
| **M4** Reject dialog shows no error | `resources/views/crm/quotations/status.blade.php:81`: the textarea is marked in red and has `aria-invalid`, `aria-describedby="remarks-err"` and the message under it. The same was done for the Email dialog's To and CC. The typed reason is kept. | UX test (asserts `remarks-err`, `aria-describedby`, `data-autoopen`); browser check. |
| **M5** "Due" shown only in red | `app/Models/Enquiry.php:57`: `dueLabel()` gives "Due today" or "N day(s) late". The new `resources/views/crm/partials/duetag.blade.php` shows an icon and that text. It is used in `crm/enquiries/list.blade.php:50`, `dashboard.blade.php:74` and `crm/enquiries/followups.blade.php:6`. The dashboard tile also shows an icon. | UX test ("3 day(s) late" on the list and the enquiry). |
| **M6** Dashboard due count differs from its list | `app/Http/Controllers/DashboardController.php:24`: the tile and list now come from `CrmAlerts::due()`, the same source as the bell, the pop-up and Enquiry list › Follow-ups due. They were removed from the unit-wide dashboard cache. `resources/views/dashboard.blade.php` is updated. | `test_c1_…` (tile count 1 = list 1 for a linked sales user, with another employee's lead present). |
| **M7** City dropdown loads the whole master | `resources/views/crm/enquiries/form.blade.php:147`: City is now a type-ahead picker. `app/Http/Controllers/LookupController.php:79` adds `cities`, the first 20 active cities starting with the typed text. The route is `routes/web.php:85` (`lookup.cities`, `enquiry.view`). `EnquiryController.php:188` uses the 200-row fallback (`pickerOptions`). | UX test (with 217 cities the page does not list them, the lookup returns 20, "Pun" finds Pune, and a Purchase user gets 403). |
| **M8** Phone hides quotation totals | `resources/views/crm/quotations/form.blade.php:185` onward: every item cell has `data-label`. `public/css/app.css:272` onward: at 640 px or less each item is a card ("Item 1", fields in two columns, Remove top-right), and Items total, Discount and Total sit full-width under the items. The table's minimum width is overridden. | UX test (`data-label` cells); browser check at 375 px (no sideways scroll, totals visible). |

## Database review

| # | What changed, file by file | Test |
|---|---|---|
| **DB-C1** Lead status overwritten under concurrency | `app/Services/EnquiryService.php:136`: `refreshStatus()` replaces `setStatus()`. It locks the enquiry row and then works out PO received, Lost (every current quotation Rejected or Expired) or Open from the records. It is called by `QuotationService` (customer status, revise, delete) and `CustomerPoService` (create, update for old and new enquiry, delete). The PO service transactions retry 3 times after a deadlock. A duplicate PO no on one enquiry is refused (`CustomerPoService.php:156`, review suggestion S5). | `test_db_c1_lead_status_follows_the_pos_and_quotations` |
| **DB-C2** Cache thrown away on every save; dashboard queries scan the year | Cache: see architecture C1. Indexes in migration `2026_10_14_000001`: `enquiries (unit_id, deleted_at, status, enquiry_date)` and `(unit_id, deleted_at, enquiry_date, lead_source_id, service_category_id)`. | `test_c1_…`, `test_db_c4_index_migration_adds_the_list_and_dashboard_indexes` |
| **DB-C3** `%text%` searches with EXISTS | `app/Http/Controllers/LookupController.php:105` `orNumber()`: digits find that number in the last three financial years, and other text matches the start of the number. `:117` `orClient()`: `whereIn` on prospect and party ids whose name starts with the text. `EnquiryController.php:164` `matching()` covers enquiry no, client and contact no; the site visit (`SiteVisitController.php:113`) and PO (`CustomerPoController.php:94`) searches reuse it. Quotation search and list are at `QuotationController.php:177` (the client name search in the list also takes UX suggestion S12). New indexes: `enquiries (unit_id, contact_no)`, `(unit_id, enquiry_no)`, `customer_pos (unit_id, po_no)`. The placeholders say what to type. | `test_db_c3_pickers_match_the_start_and_numbers_by_their_digits` (and the existing wildcard test in `FixRound2Test`) |
| **DB-C4** List sorts with no index | Migration `2026_10_14_000001`: `quotations (unit_id, is_latest, quotation_date)`, `site_visits (unit_id, visit_at)`, `enquiries (unit_id, next_action_date)`, `(unit_id, expected_closing_date)`. The same migration covers suggestions S1 (`customer_pos (enquiry_id, deleted_at)`) and S6 (`activities (enquiry_id, activity_date)`, `site_visits (enquiry_id, visit_at)`). | `test_db_c4_…`; ran on "nursery" after the backup. |
| **Docs** ENQ / QT and ON DELETE | `docs/DATABASE.md`: new "Numbers" section (an enquiry has ENQ; a quotation copies it and has its own QT), an ON DELETE rules table for every new FK, an index table for the new migration, and a pointer from the Part 1 `quotations` table. `docs/crm-changes/crm-changes-report.md`: the number wording and choice 17 now match the code. | — |

## Tests the code review asked for

All of these are in `tests/Feature/CrmReviewFixesTest.php`:
- **Approval with real roles:** a non-Admin approver role, a non-approver getting 403 on Approve and Reject, the boundary at exactly ₹1,00,000 and at ₹1,00,000.01, a USD quotation, and self-approval refused except for Admin (code suggestion S1, now built). Approved and Sent quotations refuse Save.
- **PO:** refused for a Draft, an Internal Approval and an earlier revision.
- **Documents:** upload and delete refused on an Approved quotation and on an earlier revision; a Documents box with two pages.
- **Paging:** the Site visit list, the Quotation list and the Employee master; `per_page=100`; an unknown `per_page` falls back to 25.
- **MariaDB:** the whole suite was also run on a throwaway MariaDB database.

## Suggestions also taken (small and clearly right)

- **Code S2:** an approver role without Quotation View can open the quotation and its PDF (`routes/web.php`, show and pdf).
- **Code S6:** the plain-text quotation email prints "Shah & Sons" as typed (`resources/views/mail/quotation.blade.php`).
- **Code S7:** a site visit moved to another unit's enquiry moves unit (`app/Services/SiteVisitService.php`).
- **Code S12 / architecture S6:** `ApprovalRule::for()` reads with `firstOrNew` and never writes.
- **UX S2:** Follow-ups due sorts by the next action date, and Closing this month by the closing date.
- **UX S3:** the pop-up says "Showing 5 of 8".
- **UX S7:** Submit, Approve, Reject and Email are guarded against double clicks.
- **UX S10:** the link reads "Lead ageing report".
- **UX S21:** the internal codes "OTHER POINTS A20, A21, A22" are removed from the screens.
- **Database S5:** the PO quotation lock is now `lockForUpdate`, and a duplicate PO no is refused.
- **Database S7:** migration `000004` lists its permissions and removes them in `down()`.
- **Architecture S8:** no version key is left, so `hrtime` is gone.

## Suggestions left for later

- **Architecture S1 / code S5:** a `failed()` handler that logs a failed email.
- **Architecture S2:** a 20 MB total BOQ limit for emailing.
- **Architecture S7:** a separate upload rate limit.
- **Code S3:** "Draft, not approved" printed on the PDF.
- **Code S10, S11:** inactive masters and closed enquiries in a hand-made POST.
- **Database S2, S3, S4, S8:** client change after a quotation, a CHECK constraint, a date moved to another year, and `simplePaginate` for very large units.
- **UX:** S1, S4 to S6, S8, S9 and S11 to S20.

These are listed for the reviewers. None of them blocks the full test.
