# CRM full test: fixes

Fixes made on 14/10/2026 for `crm-full-test-50-entries-report.md`. Paths are relative to `D:\xampp\htdocs\nursery-app`, and line numbers mark where each fix starts.

**Summary:** B1 (must-fix) is fixed. All four suggestions, B2 to B5, were small and clearly right, so they are fixed too.

**Tests:** `php artisan test` passes 95 tests with 1,135 assertions. That is the 89 from before plus 6 new ones in `tests/Feature/CrmFullTestFixesTest.php`. The same 95 also pass on a throwaway MariaDB 10.4 database (`nursery_test_tmp`, dropped afterwards).

**Database:** I backed up "nursery" to `storage/app/backups/nursery-before-crm-test-fixes-20261014.sql`, then ran `2026_10_14_000002_add_contact_no_digits_to_enquiries` on it. That migration adds one column and one index; no existing value is changed. "nursery" has no enquiries yet, so nothing needed filling.

**Browser check:** done on a throwaway copy (`nursery_crm_check3`, dropped afterwards), served by PHP with this PC's `max_input_vars = 1000`. Two checks:
- A quotation with 150 items, typed in the page, saved all 150 items and the cost details. The total was ₹15,000.00.
- 150 items posted as separate fields, as without JavaScript, were refused with the new message, and nothing was saved.

XAMPP and `php.ini` were not changed. `http://localhost/nursery-app` answers normally.

## B1 (must-fix): a quotation with more than about 140 items would not save

**Cause:** PHP reads at most `max_input_vars` form fields (1,000 here) and silently drops the rest. Each item had 7 fields.

**What changed:**
- `public/js/crm.js:33-43`: when the quotation is saved, all items go as **one** field, `lines_json`, and the separate item fields are not sent. A 200-item quotation now sends about 30 fields. The item fields are enabled again if the user comes Back to the page. This runs after the page's own checks (unsaved changes, double save), so nothing is disabled when a save is stopped.
- `app/Http/Requests/Crm/QuotationRequest.php:31`: reads the items from `lines_json` when it is present. The 200-item limit and its message ("lines may not have more than 200 items") are unchanged.
- `app/Http/Middleware/EnsureFormIsComplete.php:14` (new): any POST, PUT or PATCH that arrives with as many fields as PHP's limit may have been cut off. It is refused with "The form has too many fields for the server to read in full, so nothing was saved…" and **nothing is saved**. The limit is read from `php.ini`, or from `config('nursery.max_input_vars')` when that is set. This covers every form, including the item fields sent without JavaScript.
- `bootstrap/app.php:21`: adds the middleware to the `web` group.
- `resources/views/crm/quotations/form.blade.php:51`: shows that message as "Nothing was saved."

**Tests:**
- `test_b1_items_sent_as_one_field_save_200_items`: 200 items are saved and the total is right; 201 items are refused with the limit message.
- `test_b1_a_form_at_the_max_input_vars_limit_is_refused_and_nothing_is_saved`: 150 items as separate fields (1,050 fields) are refused, nothing is saved and the page shows the message; 100 items as separate fields still save.
- The browser check above, with real PHP.

## B2: a PO could be entered against a Rejected or Expired quotation

**What changed:**
- `app/Services/CustomerPoService.php:159`: `canTakePo()` now refuses Rejected and Expired. The message is "Set its customer status to Accepted, or Revise it, before entering a PO."
- `app/Services/CustomerPoService.php:166`: `markAccepted()` sets the quotation to Accepted when a PO is saved against it, if it is not already Accepted, and logs it. The quotation, its lead and the Lead report then agree.
- `app/Http/Controllers/Crm/CustomerPoController.php:122`: the PO page's quotation list no longer offers Rejected or Expired quotations.

**Test:** `test_b2_po_refused_for_rejected_and_expired_and_marks_accepted`.

## B3: a mobile typed as "9822000005" did not find "+91 98220 00005"

**What changed:**
- `database/migrations/2026_10_14_000002_add_contact_no_digits_to_enquiries.php` (new): adds `enquiries.contact_no_digits` with the index (unit_id, contact_no_digits), and fills it for existing rows.
- `app/Models/Enquiry.php:47`: `phoneDigits()` keeps digits only and drops a leading 91 or 0. The model keeps the column up to date on every save.
- `app/Http/Controllers/Crm/EnquiryController.php:170`: a number typed in the Enquiry picker, Site visit and PO searches also matches the start of those digits.
- `docs/DATABASE.md`: the new column is documented.

**Test:** `test_b3_enquiry_picker_finds_a_mobile_typed_any_way`. These all find the lead: "9822000005", "98220", "+91 98220 00005", "+919822000005" and "098220 00005".

## B4: a text file renamed to .pdf was accepted as a BOQ

**What changed:** `app/Http/Requests/Crm/DocumentRequest.php:29`. For PDF, JPG, JPEG, PNG and WEBP files, the type read from the file's content must match its name. If it does not, the message is "This file is not a real PDF file (its content is text/plain). Save it as PDF and upload it again." Other allowed types (Word, Excel, CSV, text, email) are unchanged.

**Test:** `test_b4_a_text_file_renamed_to_pdf_is_refused`. The renamed text file is refused, while a real PDF and a `.txt` file are still accepted.

## B5: an inactive master value saved through a hand-made post

**What changed:**
- `app/Http/Requests/Crm/EnquiryRequest.php:44`: Source, Industry type, City and Service type must be active. The one value already saved on the enquiry being edited is still allowed, so old enquiries can still be saved. This is code review S10.
- `app/Http/Requests/Crm/QuotationRequest.php:176`: a new quotation (form or import) must be for an Open enquiry. A Lost or PO-received enquiry is refused; editing an existing quotation is unaffected. This is code review S11.

**Test:** `test_b5_inactive_master_and_closed_enquiry_are_refused`.

## Note on the bell lag

The test noted that the unit-wide bell counts can be up to a minute behind. That delay is as designed (choice 17 after the architecture review), so it is unchanged.
