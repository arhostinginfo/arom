# Radio groups to drop-downs — report (02/11/2026)

Request (02/11/2026): wherever the segmented radio design is used (like "Invoice type* Sales Credit Debit" on
Sales › Invoices), show it as a drop-down instead.

Each segmented radio group (`<div class="segc" role="radiogroup"><label><input type="radio">…`) is now
`<select class="inp" id="…" name="(same name)">` with a real `<label for="…">` (same text and required star), the same
values / labels / default / `old()` / saved selection, the same hint and error partial. `.inp` is `width:100%`, so the
drop-downs fit the phone width. Disabled states come from the surrounding `<fieldset disabled>`, which disables a
select the same way it disabled the radios. No blank option was added where a value is always chosen (every new record
has a default).

Not in this change (other developers): `resources/views/sales/page.blade.php` (the Sales invoice reference example),
`resources/views/po/*`, `resources/views/crm/customer-pos/*`, `public/js/sales.js`, `public/js/customer-po.js`.

## Converted groups

| Page | Field (name) | Before | After |
|---|---|---|---|
| Admin › Approval (`admin/approval/edit.blade.php`) — Quotation | Approval required (`is_required`) | segc radios Yes / No | `select#is_required` Yes(1) / No(0), aria-required |
| Admin › Approval — Sales order | Approval required (`is_required`) | segc radios | `select#so_is_required` |
| Admin › Approval — Stock transfer | Approval required (`is_required`) | segc radios | `select#st_is_required` (hint kept) |
| Admin › Approval — Purchase order | Approval required (`is_required`) | segc radios | `select#po_is_required` |
| Admin › Masters › Employee (`admin/employees/form.blade.php`) | Status (`status`) | segc Active / Inactive | `select#status`, error class + partial |
| Admin › Masters › Item (`admin/items/form.blade.php`) | Type (`type`) | segc Sales / Purchase / Both | `select#type` |
| Admin › Masters › Item | Status (`status`) | segc Active / Inactive | `select#status` |
| Admin › Masters › Party (`admin/parties/form.blade.php`) | Type (`type`) | segc Customer / Supplier / Both | `select#type` |
| Admin › Masters › Party (Page setup "opening") | DR / CR (`opening_balance_type`) | segc DR / CR (default DR) | `select#opening_balance_type`, still only when Opening is shown |
| Admin › Masters › Party | Status (`status`) | segc Active / Inactive | `select#status` |
| Admin › Users (`admin/users/form.blade.php`) | Status (`status`) | segc Active / Inactive | `select#status`, hint "Inactive users cannot sign in." kept |
| Purchase › GRN entry (`purchase/grns/page.blade.php`) | Invoice type (`invoice_type`) | segc Purchase / Credit / Debit | `select#invoice_type`; once matched it stays read-only text + hidden field, as before |
| Purchase › GRN › Quality approval dialog | Result (`result`) | segc Approved / Rejected | `select#qa_result` (default Approved, old() kept) |
| Purchase › Stock transfer (`purchase/stock-transfer.blade.php`, Page setup "source_type") | Source type (`source_type`) | segc Purchase / Credit / Debit | `select#source_type`, star / aria-required from Page setup; see note |
| Reports › Sales (`reports/sales.blade.php`) | Summary / Detailed (`detail`) | segc | `select#detail` (default summary) |
| Reports › Sales | Format (`format`) | segc Excel (CSV) / PDF | `select#format` (default csv) |
| Reports › Purchase (`reports/purchase.blade.php`) | Download format (`format`) | segc | `select#format` (old() / query value kept) |
| Reports › CRM (`reports/crm.blade.php`) | Format (`format`) | segc | `select#format` |
| Sample screens (`/app/...`, `public/js/preview.js`) | every `seg()` group (Invoice type, Layout, Format, Domestic / Export, Status, Type, DR / CR, Approval required, Levels, Item no generation, Paid from, Bank entry type, Entry type tabs, Access rights role) | buttons with role="radio" in `.segc` | `seg()` now builds `<select class="inp" data-seg>`; `fld()` ties the label to it |

Note — Stock transfer Source type is optional (nullable). New transfers start on Purchase (no blank option). An older
transfer saved without a source type shows a blank "Choose…" option, so saving it does not quietly set Purchase
(with the radios, nothing was checked and nothing was sent).

## Left as is

- `resources/views/sales/page.blade.php` Invoice type, `resources/views/po/register.blade.php` Format, customer PO files,
  `public/js/sales.js` (`input[name=invoice_type]:checked`): owned by other developers.
- Single checkboxes (`type="checkbox"`, e.g. "Below minimum stock only", user rights): unchanged, not radio groups.
- No radio group was found that is really a different UI (e.g. table row selection); none left for that reason.

## JS changes

- `public/js/grn.js`: `invType()` reads `[name=invoice_type]` (the select, or the hidden field once matched); the
  lines are redrawn on the select's `change`; the Approve / Reject quality buttons set `select[name=result].value` and
  fire `change`; the per-item rejected-quantity block shows / hides from the select's value.
- `public/js/preview.js`: `seg()` builds a select (optional 4th argument = aria-label, used for the Entry type select
  in a card heading); `segVal(id)` returns the select's value; `wireSeg(root, cb)` calls `cb(id, value)` on change, so
  every caller (invoice number, BOM, entry type, access-rights role, config) behaves as before; `need()` reads `.value`.
  Access rights "Role" label is tied to its select.
- Both files parse (checked in the browser with `new Function`).

## CSS

- `.segc` rules in `public/css/nursery.css` and `public/css/app.css` are KEPT: `sales/page.blade.php` and
  `po/register.blade.php` still use them until the other developers finish.
- Added in `public/css/app.css`: `.sh select[data-seg],.entrybar select[data-seg]{width:auto;max-width:100%}` so the
  sample screens' heading / entry-bar drop-downs do not stretch across the bar.

## Tests

- New `tests/Feature/RadioToSelectTest.php` (8 tests): approval rules (render + PUT for quotation, sales order, stock
  transfer), party (type, DR / CR, status; create, edit, old input after a refused save), item (type, status), user and
  employee status, GRN entry invoice type, stock transfer source type (saved value; blank choice for an older transfer
  without one), Sales report detail / format (render, CSV download with detail=detailed, old input after a refused
  download), Purchase report format (default and ?format=pdf), CRM report format. Each also asserts the page has no
  `type="radio"` / `role="radiogroup"` and the select has a `<label for>`.
- `tests/Feature/GrnTest.php` (matched GRN test): the quality dialog's Result is a select with Approved selected, and a
  matched GRN shows the invoice type as a hidden field, not a select.
- No existing test asserted the converted radio markup (UiRound2Test's `role="radiogroup"` check is for CRM prospects
  and still holds).

## Test run

- `artisan test tests/Feature/RadioToSelectTest.php`: `Tests: 8 passed (261 assertions)`.
- `artisan test tests/Feature/GrnTest.php`: `Tests: 14 passed (241 assertions)`.
- Full `artisan test` (run twice): stops with `PHP Fatal error: Trait "Tests\SeparatePo" not found in
  tests\Feature\CustomerPoDirectReviewTest.php on line 24`. The other developers' PO removal deleted
  `tests/SeparatePo.php` while 8 PO test files still use it (CustomerPoDirect*, PoDualEntry*, PoModule*,
  PoToSalesOrder, SeparatePoFinalReview); not touched here.
- Full suite without those 8 files (phpunit with a scratchpad copy of phpunit.xml that excludes them), second run:
  `Tests: 382, Assertions: 7861, Failures: 3, Skipped: 1.` The 3 failures are in files being edited by the other
  developers, none about the converted pages: `CrmCustomerPoTest::test_po_menu_is_gone_and_crm_keeps_6_po_agreement`
  (405 expected, 404 received) and two `TaxInvoiceFixesTest` sales invoice PDF tests (PDF HTML content; memory peak).
  The first run had 18 further failures ("Only the listed parts can be switched.", module switch list mid-change by
  the PO removal); they were gone on the rerun.
