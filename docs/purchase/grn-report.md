# Purchase › GRN entry: developer report

Report date: 27/10/2026. Purchase › GRN Entry now opens a real page instead of the prototype sample screen (`preview.js` `R.grn`). The old address `/app/grn` redirects to it, for users with GRN entry · View.

## What a user sees

- **GRN entry** (`/purchase/grns`) opens the latest GRN of the unit and year on the top bar, or a new one.
- **Entry bar:**
  - search by GRN no, supplier invoice no or supplier name;
  - green **Add new** button;
  - **GRN list**.
  - There is no PDF, CSV or Import, because the prototype's capability table gives GRN only page setup.
- **Header:**
  - GRN no and GRN date. Until the GRN is matched they show "on match".
  - **Show all headings** (the short view is the default).
  - Short view: Party name (suppliers only), Invoice type (Purchase / Credit / Debit), Invoice no, Invoice date, and Invoice amount including GST.
  - The full view adds Transporter, Vehicle no, E-way bill no, E-way bill date, Customer name, SO no (the customer's sales orders) and Remarks.
- **Quality bar:**
  - shows the match state and the Quality approval (Pending / Approved / Rejected);
  - holders of GRN quality approval · Edit see **Approve quality** and **Reject**;
  - Approve opens a dialog where a rejected quantity can be entered for each item. A reason is needed to reject.
  - Note shown: "Stock is usable only after Approved."
- **Items:**
  - PO no lists the supplier's open POs in this unit (Approved or Partly received, with something pending).
  - The item list then shows that PO's pending items.
  - Invoice qty shows "Max N (PO qty pending)".
  - UOM comes from the PO line.
  - Rate left blank is the PO rate less the PO discount.
  - Amount is shown per line, and the green **Add item** button adds a line.
  - A Credit / Debit note lists the supplier's purchase items, with no PO.
- **Amount match box:**
  - shows the supplier invoice amount, the taxable value, GST, the calculated total (qty × rate + GST), the difference, and an Amount matched / not matched badge, all while typing;
  - the GRN no box shows "Generated when the amount matches", "Pending match · no GRN no yet", or the GRN no and date.
- **Footer buttons:**
  - **Save as pending match** and **Save and generate GRN no**. A matched GRN shows **Save changes** instead.
  - **Delete GRN** for a GRN that is still pending match.
  - **Cancel GRN**, with a reason, for a matched GRN.
- **Below the form:** the recent changes (activity log) and Added / Updated by.
- **List** (`/purchase/grns/list`):
  - views All, Pending match, Pending quality and Approved;
  - search, sort (GRN no, invoice date, amount) and paging on the server;
  - on phones each row becomes a card.
  - The item lines also become cards below 1100 px wide, so there is no sideways scroll.
- **Admin › Page setup › GRN entry** (key `grn`):
  - Party name, Invoice type, Invoice no, Invoice date and Invoice amount are fixed and mandatory, as in the prototype's MANDATORY list.
  - The other fields can be shown or hidden and made mandatory.
  - Quality approval can only be shown or hidden.
- **Admin › Access rights:** a new right, **GRN quality approval** (View / Edit).

## Schema

Migration `2026_10_27_300001_create_grns_tables` is additive only. It ran on local MySQL on 27/10/2026. Full columns are in `docs/DATABASE.md`.

- **`grns`:**
  - `grn_no` stays NULL until the GRN is matched. It is unique per unit and year.
  - `invoice_type`, plus the supplier invoice no, date and amount.
  - `active_invoice_no`: the invoice number is unique per supplier and year among live GRNs. A cancelled or deleted GRN frees the number.
  - transporter, vehicle no, e-way bill no and date;
  - optional `customer_id` and `sales_order_id`;
  - totals;
  - `match_status`, `quality_status` with who, when and remarks, and `stock_posted`;
  - `status`: Saved or Cancelled. Draft is reserved.
  - cancel fields, audit columns and soft deletes.
- **`grn_lines`:**
  - nullable `purchase_order_id` and `purchase_order_line_id`, and the item;
  - quantity (the invoice quantity), plus `accepted_quantity` and `rejected_quantity`;
  - UOM, rate, tax structure, GST and total.
- **Other additions:**
  - permissions `grn-quality.view` and `grn-quality.edit`;
  - NumberSeries key `grn` (GRN/26-27/0001);
  - morph name `grn`;
  - stock ledger source_type `grn`.

## Match, PO and stock behaviour

1. **Match rule:** the lines total must be within ±₹1 of the invoice amount (`config('nursery.grn.match_tolerance')`).
   - The lines total is Σ(qty × rate) plus GST.
   - GST is worked out on the server from the PO line's tax structure, by the supplier's state: CGST + SGST, IGST, or none for an import supplier.
2. **When it matches:** the GRN gets its number and its GRN date (today, kept inside the GRN's financial year). For a Purchase invoice, the PO received quantities go up through `PurchaseOrderService::receive()` in the same transaction.
3. **When it does not match:**
   - "Save and generate GRN no" is refused with "Amount not matched. Fix it or save as pending match.", and nothing is saved.
   - "Save as pending match" saves the GRN with no number and no effect on the PO.
   - Editing a pending GRN later can match it.
4. **A matched GRN must stay matched when it is edited.**
   - The PO quantities move by the difference.
   - The supplier and invoice type are fixed.
   - After the quality approval, the GRN cannot be edited at all.
5. **Stock** comes into the GRN's unit only on Quality approval = **Approved**:
   - only the accepted quantity comes in;
   - it is written by `StockLedger::move` with source_type `grn` and dated with the GRN date.
   - The rejected quantity moves no stock and is given back to the PO line, so it is pending again for a replacement.
   - **Rejected** brings no stock in, and the whole quantity goes back to the PO.
   - The quality approval is final.
6. **Cancel** works only on a matched GRN, needs a reason, and the number stays in the series. It reverses the PO quantities.
   - If stock came in, it locks the unit's stock balances and checks that the unit still holds the accepted quantity.
   - If the unit holds it, it calls `StockLedger::remove`. If not, the cancel is refused and names each item that is short.
7. **Delete** (soft) works only on a GRN still pending match.
8. **Lock order** is the same in every path: GRN row → PO rows (in id order, through `receive()`) → PO lines → stock_balances (in item order).
   - PO and stock transfer code never lock in the opposite direction.
   - `receive()` is used unchanged.
   - A PO sent back to Pending approval, for example after an Admin edit, cannot receive. The GRN then shows `receive()`'s own message.
9. **Dashboard hooks:** `Grn::pendingMatch()`, `Grn::pendingQuality()` and `Grn::qualityApproved()`. No tiles were added.

## Decisions to confirm with the client

1. **PO line required:** every Purchase invoice line must be against a PO line. The prototype (PURCHASE A20) allows lines with no PO, listing all of the supplier's parts. Switching on `config('nursery.grn.lines_without_po')` allows that. Without a PO, the item drop-down lists at most 200 items, and there is no type-ahead.
2. **Credit / Debit notes:**
   - no PO and no stock;
   - when they match they get a GRN no, and the quality step is approved at once.
   - They use the same GRN series and the same supplier-invoice uniqueness.
3. **When each thing moves:** PO received quantities go up at match; stock comes in at quality approval. This is the recommended split.
4. **Rejected quantities** are given back to the PO as pending.
5. **Tolerance** is ±₹1. The rate defaults to the PO rate less the PO discount. The rate can be typed, but the tax cannot be changed on the GRN.
6. **GRN date** is the date of matching, kept inside the GRN's financial year. Stock is dated with the GRN date, not the quality approval date.
7. **Quality approval hidden in Page setup** means there is no quality step: the GRN is approved at match and its stock comes in at once.
8. **Who can do what:**
   - GRN quality approval · Edit belongs to Admin only by default. The Purchase role keeps `grn.*` but does not get it.
   - Cancelling a GRN after its quality approval also needs the quality right.
   - There is no ApprovalRule or Admin › Approval card: the prototype only implies the quality field.
9. **Invoice number uniqueness:** per supplier and financial year (the GRN's year), among live GRNs.
10. **Remarks** was added as an optional field. It is not in the prototype's field list.
11. **Customer name / SO no:** stored for reference only, with no effect on stock or the sales order.
12. **Quality approval hidden in Page setup:** every matched GRN is then quality-approved straight away. Cancelling it still needs GRN quality approval · Edit, which only Admin has by default. If the client hides the quality step, should GRN entry · Delete be enough to cancel?
13. **Invoice date and financial year:** the invoice date may not be after the end of the GRN's financial year, so the GRN date stays inside that year. An earlier date (a March invoice received in April) is allowed on purpose. Should invoices from before the year also be refused?

## Fixes after the first test round (27/10/2026)

1. **PO line on a GRN:**
   - A PO line that any GRN line points at, including lines of cancelled or deleted GRNs, can no longer be removed from the PO. The user now sees "Item N is on GRN …, so it cannot be removed." instead of a database error.
   - Its item cannot change while a live GRN points at it.
   - This is an additive guard in `PurchaseOrderService::saveLines()`; `receive()` is unchanged.
2. **Item drop-down:** new endpoint `GET /purchase/grns/supplier-items?party=` returns the supplier's purchase items (rate, UOM, GST rates, at most 200). When the supplier changes, the page rebuilds the item drop-down of every row and of new rows (credit / debit notes and lines without a PO).
3. **Unit check before validation:** both GRN form requests refuse (403) a GRN of a unit the user may not work in. This happens before validation, so another unit's PO details are not described.
4. **Cancel after quality approval:** the quality-right check now runs in `GrnService::cancel()`, after the GRN row is locked.
5. **Invoice date:** refused if it is after the end of the GRN's financial year (see decision 13).
6. **Page fixes:**
   - A match tolerance of 0 is now used as 0.
   - "Max N (PO qty pending)" and its red mark show only while the GRN can be edited.

## Tests

`tests/Feature/GrnTest.php` has 9 tests.

**Changes to existing tests that are needed** (they belong to other owners, so they were not edited):
- `AdminAccessTest:94` and `MenuBarTest:129` expect `/app/grn` to return 200. It now redirects (302) to the built page, the same as `/app/po`.
- Suggested fix: assert the redirect, or follow it.
