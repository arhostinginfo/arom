# Tax invoice fixes: report

Developer report, 02/11/2026. It covers the client's sheet for `sales/invoices/create` (four points), plus one extra request: Invoice type becomes a dropdown.

**Tests:** `tests/Feature/TaxInvoiceFixesTest.php` is new. It has 6 tests plus 1 that writes the sample PDF and is skipped unless `TAX_INVOICE_SAMPLE_PDF=1` is set. All of them pass, and `SalesTest` passes. In the last full-suite run (sqlite `:memory:`), 384 passed, 1 skipped and 2 failed (7,985 assertions). The 2 failures are customer PO / upload tests ("po menu is gone and crm keeps 6 po agreement", and "allowed types upload and open again and refused types never reach storage"). The other developer is changing that area right now, and which tests fail changes from run to run. I did not touch those files.

**Database:** no migration, and no writes to the dev database. Reads only: there are no sales invoices and no sales orders, and the company row has **no GSTIN** and a JPG logo.

**Backups** of every file I changed: `C:\Users\ADMIN\AppData\Local\Temp\claude\D--xampp-htdocs-nursery-app\1b9abdf8-b24e-4544-b984-62f55bc97d60\scratchpad\invoice-fixes-backup\`, with the same folder structure as the app.

## 1. Invoice date: filled in automatically, only an Admin can change it

- **Before:** the date was a free field for every user. It started at today, but anyone could type another date.
- **Fix:**
  - A new invoice is dated today.
  - For users who are not Admin, the field is read-only, so the date picker does not open. The hint says *"Today's date, set automatically. Only an Admin can change it."* On a saved invoice it says *"As saved. Only an Admin can change it."*
  - The server enforces this too (`SalesInvoiceRequest::prepareForValidation`). For a user who is not Admin, a new invoice always gets today's date and an edit keeps the saved date, whatever the browser sends.
  - An Admin can edit the date. The hint says *"Today by default. As Admin you can enter a past date in 2026-27, not after today."* Rule S-6 is unchanged: the date must be in the year on the top bar (or the invoice's own year for an edit) and not after today.

## 2. "PO no not appearing under drop down"

- **What I checked:**
  - The dropdown lists only the party's **Approved** sales orders in the unit on the top bar. Orders from any financial year are included. That rule is unchanged.
  - When the party changes, the script calls `sales.parties.info`, which returns `orders`.
  - Both the server-rendered list (after a failed save, or from "Make tax invoice") and the JSON list work. The tests prove it.
- **Main cause:** the dev database has no sales orders at all, so the list can only be empty.
- **Bug fixed:** clearing the party left the previous party's POs in the list. Now the list empties, the hint hides, and PO rows go back to free rows.
- **Empty state:** when the chosen party has no approved order, the select's first option reads **"No approved sales order for this party."** The hint under it still explains where orders come from. This works on the server-rendered page and when the script refreshes the list.

## 3. "Unable to generate tax invoice pdf"

- **Root cause (a bug):**
  - `SalesPage` passes a view variable `$open` ('invoice' or 'order') to `sales/page.blade.php`.
  - The shared `partials.iobtn` include checks `isset($open)`, and the variable leaked into it.
  - So on the Sales page, **Generate PDF** and **Download CSV** (both cards) were drawn as `<button type="button" data-open="invoice">`, which does nothing when clicked, instead of download links.
  - The Apache log confirms that no PDF request ever reached the server.
- **Fix:** `'open' => null` on the two entry bar includes and on the footer PDF include in `sales/page.blade.php`. The controls are links again. A test checks this.
- **Other reasons the client could not get a PDF:**
  - The company has no GSTIN, so no invoice can be saved, and the PDF exists only for saved invoices.
  - Before, only users with `masters.edit` saw the GSTIN warning. Every user who can add invoices now sees *"Your company's GSTIN is not entered yet, so invoices cannot be saved. Ask an Admin to enter it in Admin › Masters › Company."*
  - A new invoice shows *"Save the invoice to generate its PDF."* where the PDF button will appear.
- **Logo:**
  - The PDF now prints the company logo through `Company::pdfLogo()`, and only when the server can draw it.
  - JPG works without GD. PNG and WebP are skipped while GD is off, and the PDF still generates.
  - A missing logo file is also skipped. Tests cover JPG, PNG and a missing file.
- **Memory:** a 60-line invoice renders in about 90 MB on this machine. The memory limit is 512 MB.

## 4. Standard GST tax invoice layout (`resources/views/pdf/sales-invoice.blade.php`, rewritten)

A4 portrait, DejaVu Sans, Indian money grouping as before.

**Fixed header, on every page:**
- Logo (JPG only).
- Company name, address, GSTIN, state and code (from the GSTIN), PAN, phone and email.
- Title box: TAX INVOICE, CREDIT NOTE or DEBIT NOTE, with "Original for recipient" and CANCELLED when it applies.
- Details grid: invoice no and date; PO no · SO no; PO date (the customer PO date if linked, otherwise the sales order date); place of supply; reverse charge "No"; supply type (and currency); unit.
- "Page X of Y", drawn by the controller after layout.

**First page only:**
- Bill to and Ship to: name, address, GSTIN (or Unregistered / Not applicable for export), state and code.
- Transport: mode, transporter, vehicle no, distance.

**Item table:**
- Columns: Sr, description of goods (item no and description), HSN/SAC, Qty, UOM, Rate, taxable value, then CGST % and amount plus SGST % and amount, or IGST % and amount, or no tax columns for export. Then Total.
- The table head repeats on each page, and a row never splits across pages.

**Last page only, printed once after the last item:**
- HSN-wise summary with rates and tax, and the P&F tax note.
- Amount in words.
- Bank details, if the Company master ever gets bank fields.
- Payment terms.
- Declaration.
- Totals: items, P&F, taxable value, CGST and SGST or IGST (export: "Nil (LUT)"), total GST, round off (only if not zero), and the grand total (₹ or the currency code).
- "For <Company> … Authorised signatory".
- Export invoices keep the LUT wording.

**Fixed footer, on every page:** "This is a computer generated tax invoice." (or credit / debit note), the company name, address and contact, the invoice no and "Page X of Y".

Sample layout (see `docs/sales/sample-tax-invoice.pdf`: 40 lines, 3 pages, fake data):

```
┌──────────────────────────────────────────────────────────── Page 1 of 3 ┐
│ [LOGO]  Nursery Components Pvt Ltd                 ┌──────────────────┐ │
│         Plot 21, MIDC Chakan, Pune 410501          │   TAX INVOICE    │ │
│         GSTIN 27AAACN1234F1Z5 State Maharashtra(27)│ Original for rec.│ │
│         Ph 020 2345 6789 · accounts@…              └──────────────────┘ │
│ Invoice no | Invoice date | PO no          | PO date                    │
│ Place of supply | Reverse charge No | Supply Intra-state | Unit         │  ← every page
├─────────────────────────────────────────────────────────────────────────┤
│ BILL TO …                     │ SHIP TO …                               │  ← page 1 only
│ TRANSPORT Mode · Transporter · Vehicle no · Distance                    │
│ SR│DESCRIPTION│HSN│QTY│UOM│RATE│TAXABLE│CGST%│CGST│SGST%│SGST│TOTAL      │  ← head repeats
│ 1 │BRK-2210 … │8708│25│NOS│42.5│1,062.50│9│95.63│9│95.63│1,253.76      │
│ …                                                                       │
│ (last page) HSN summary · Amount in words │ Taxable / CGST / SGST /     │
│ Payment terms · Declaration               │ Total GST / Grand total     │
│                                           │ For … Authorised signatory  │
├─────────────────────────────────────────────────────────────────────────┤
│ This is a computer generated tax invoice. · address · contact  Page 1/3 │  ← every page
└─────────────────────────────────────────────────────────────────────────┘
```

## Extra: Invoice type as a dropdown

- On a new invoice, the Sales / Credit / Debit radio buttons are now `<select class="inp" id="invoice_type" name="invoice_type">` with `<label for>`.
- The values, the default (Sales), the selection after a failed save, the required star, the hint and the error display are the same as before.
- A saved invoice still shows its type as read-only text.
- `public/js/sales.js` now reads and listens to the select. Changing the type still recalculates PO limits (balance, credit room, invoiced) and keeps the quantities already typed.

## File by file

| File | Change |
|---|---|
| `app/Http/Requests/Sales/SalesInvoiceRequest.php` | Users who are not Admin: the date is forced to today (new) or the saved date (edit). |
| `app/Http/Controllers/Sales/SalesInvoiceController.php` | `pdf()` uses the new `invoicePdf()`: logo only when it can be drawn (errors swallowed), render, then "Page X of Y" at the top right and in the footer. |
| `app/Support/Gst.php` | `STATES` (GST state codes) and `stateLabel()`. |
| `resources/views/pdf/sales-invoice.blade.php` | Rewritten as above. |
| `resources/views/sales/page.blade.php` (shared, targeted edits only) | Invoice type select; date read-only and hints; PO empty option; `'open' => null` on the two entry bars and the PDF button; GSTIN warning for every user who can add invoices; "Save the invoice to generate its PDF." |
| `public/js/sales.js` (shared, targeted edits only) | `invType()` reads the select and listens for its change; `setPoOptions()` covers the empty state and a cleared party. |
| `tests/Feature/SalesTest.php` (3 lines) | The future-date, other-year and edit-other-year checks are posted as Admin, because only an Admin can type a date now. |
| `tests/Feature/TaxInvoiceFixesTest.php` | New: date rules, PO dropdown (page and JSON, draft excluded, empty state), invoice type select, PDF links, JPG / PNG / missing logo, 60-line layout (header and footer fixed, head repeats, totals once after the last row, at least 2 pages, memory delta), IGST / export / credit note variants, sample PDF writer. |
| `docs/sales/sample-tax-invoice.pdf` | Sample: 40 lines, 3 pages, fake data. |

## Browser steps

1. **Admin › Masters › Company:** enter the GSTIN. Otherwise no invoice can be saved. A JPG logo prints on the PDF; PNG prints only once GD is on.
2. **Sales › Sales order:** add an order for a customer, send it for approval, and approve it as Admin.
3. **Sales › Tax Invoice** as a Sales user:
   - Invoice date shows today and is greyed, with the hint below it.
   - Invoice type is a dropdown.
   - Choose the party. **PO no** lists "PO … · SO/…", or shows "No approved sales order for this party." when the party has none.
   - Clear the party: the PO list empties.
4. Pick the PO, enter quantities and save. The red PDF icon is now a link in the bar and next to Save. Click it and `INV-26-27-0001.pdf` downloads.
5. Log in as Admin, open a new invoice: the date can be changed to a past date in the year. A future date, or a date outside the year, is refused.
6. A long invoice (say 40 lines) prints on several pages. The header and footer are on every page, and the totals block is on the last page only.

## Open questions

- **Bank details:** the Company master has no bank fields, so the PDF prints none. The template already shows `bank_name`, `bank_account_no`, `bank_ifsc` and `bank_branch` if those columns are added. Shall I add them to the Company master? That needs a migration.
- **Round off:** grand totals are not rounded to the rupee today, so "Round off" never prints. Should invoices round to the nearest rupee?
- **Copies:** "Original for recipient" only. Do they also want "Duplicate for transporter" and "Triplicate for supplier" pages?
- **Logo format:** with GD off, only JPG logos print. Ask the client to upload a JPG, or turn GD on (not done; php.ini not touched).
- **Future-dated invoices:** an Admin cannot enter a future date (rule S-6 kept). Confirm that is wanted.

## Update 11/11/2026: header on the first page, footer on the last page

Request: "the header should be fixed at first page and footer should be at last page and whatever the final total is
there that is always at last page".

- **Header** (logo, company, GSTIN, title, invoice no / date, PO, place of supply): page 1 only. It is now in the normal
  flow of `resources/views/pdf/sales-invoice.blade.php`; the top page margin went from 192pt to 28pt.
- **Footer** ("This is a computer generated …", company name and address, phone / email): last page only, at the bottom.
  dompdf repeats a fixed element on every page, so the footer is no longer in the view;
  `SalesInvoiceController::drawPdfFooter()` draws it on the last page (`invoicePdf()` page script).
- **Totals, GST, HSN summary, amount in words, signatory:** unchanged, once, on the last page.
- **Every page:** the item table head repeats, and "INV/… · Page X of Y" is at the bottom right, so the middle pages are
  identified.
- **Tests:** `TaxInvoiceFixesTest` and `CombinedChangesReviewTest` updated (header on page 1 only, footer only on the last
  page, footer below the body). Whole suite: 1 skipped, 398 passed (8,658 assertions).
- **Sample:** `docs/sales/sample-tax-invoice.pdf` (40 lines, 3 pages) regenerated and checked page by page.
- **Backup** of the changed files: the scratchpad folder `pdf-header-footer-backup`.
