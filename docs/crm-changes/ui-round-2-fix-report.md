# UI round 2: fixes after the re-test (18/10/2026)

This answers `ui-round-2-retest-report.md`. The must-fix and all six suggestions are done; none was left out. Paths are relative to `D:\xampp\htdocs\nursery-app`.

## Must-fix

| # | Problem | Fix | Files |
|---|---|---|---|
| B1 | A saved PO / Agreement page scrolled sideways on a phone, because the long label of the selected quotation widened the field. | A field in a form grid, and a select-with-search, may now shrink below its content. The selected label is cut short with "…", and hints wrap. This applies to every form, not only PO / Agreement. | `public/css/app.css` |

## Suggestions (all taken)

| # | Suggestion | Done | Files |
|---|---|---|---|
| S1 | No pop-up below the approval limit | Below the limit, or with approval switched off, Submit / Send for approval goes straight through, with the usual toast. The pop-up shows only when the document will go to an approver. | `app/Support/ApprovalNotice.php`, `resources/views/crm/quotations/status.blade.php`, `resources/views/sales/page.blade.php` |
| S2 | Hint line under Prospect on the Enquiry form | The line "Pick one. Type to search, or Add a new prospect…" is removed. The Add button that opens the Add prospect drawer stays. | `resources/views/crm/enquiries/form.blade.php` |
| S3 | A typed contact no was silently replaced | While a Prospect with a phone is picked, Contact no is read-only and titled "From the Prospect". This happens when picking on the page and also when an existing enquiry opens. Picking a customer or a Prospect without a phone makes it editable again. | `resources/views/crm/enquiries/form.blade.php`, `app/Http/Controllers/Crm/EnquiryController.php` (`phone_locked`), `public/js/crm.js` |
| S4 | The magnifier's tooltip never showed | The icon now takes the pointer, so "Search entries" shows on hover. Clicking it still focuses the search box. | `public/css/app.css` |
| S5 | Two warnings still used the browser's box | "Unsaved changes" on leaving the page (link or entry picker), and on sending another form, now use the same Continue / Go back pop-up. The browser's own "Leave site?" prompt on closing the tab cannot be replaced, so it stays. | `public/js/app.js`, `public/js/picker.js` |
| S6 | Unclear message for an empty file | An empty file now gets "The file is empty." | `app/Http/Requests/Crm/DocumentRequest.php` |

## Tests and state

- **153 of 153 pass** (2,137 assertions) on SQLite and on a throwaway MariaDB database (`nursery_test_tmp`), which was dropped afterwards.
- **New test** `UiRound2Test::test_retest_fixes_phone_width_prospect_hint_locked_phone_and_empty_file` covers B1 (the layout rules), S2, S3 and S6. The approval test now expects no question below the limit.
- **No migration;** "nursery" is unchanged.
- **Left as found:** `.env` still points at "nursery", and MySQL and Apache are left running.
