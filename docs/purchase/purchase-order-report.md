# Purchase order: developer report

Report date: 27/10/2026. Purchase › Purchase Order now opens a real page in place of the prototype sample screen ("po").

## What a user sees
- **Purchase › Purchase Order** opens `/purchase/orders`. This is the latest PO of the unit and year on the top bar, or a new one.
- **Entry bar:** search by PO no or supplier, **Add new** (green), the Purchase order list, and Generate PDF.
- **Header:** PO no (PO/26-27/0001) and PO date. Party name lists only suppliers (Supplier or Both).
- **Show all headings** (the short view is the default) adds:
  - delivery date;
  - payment terms (filled from the supplier);
  - currency (default: the supplier's currency);
  - transporter;
  - billing and shipping address (default: the company address);
  - remarks.
  All of these are in Admin › Page setup › Purchase order.
- **Items:**
  - Item no / description: the supplier's purchase items and items of no party, with a description that can be edited.
  - Quantity.
  - UOM and Tax: drop-downs from their masters. Left blank, they use the item's.
  - Rate: left blank, it uses the item price.
  - Disc %, Delivery date, Amount, and Received once the PO is approved.
  - Green **Add item** button.
- **Totals:** taxable value, then CGST + SGST or IGST (none for an import supplier), then total. They show while typing and are worked out again on save.
- **Approval bar:**
  - Send for approval. Below the limit the PO is approved at once; above it, the button asks first.
  - Approve, or Reject with a reason, for holders of Purchase order approval · Edit.
  - Approval history and recent changes are shown.
- **Footer:**
  - **Delete purchase order:** Draft or Rejected, with nothing received.
  - **Cancel purchase order:** Pending or Approved, with a reason.
  - Generate PDF and Save.
  - Delete and Cancel are kept as Sales keeps them. The CRM no-delete rule is not applied to Purchase.
- **List** (`/purchase/orders/list`): the views are All, Pending approval, Approved, Open (pending GRN), and Draft or rejected. It has search, sort and paging.
- **PDF:** the same look as the invoice and quotation PDFs, with the company logo. Supplier, bill-to and ship-to, terms, items with HSN and GST, the amount in words, and "NOT APPROVED" or "CANCELLED" when that applies.
- **Admin › Approval:** a new **Purchase order** card. Approval required: Yes. Approve only above ₹1,00,000, the value before GST.

## GRN contract (for the GRN developer)
- Receive against a PO only through `PurchaseOrderService::receive(PurchaseOrder $po, array $qty, User $actor)`:
  - `$qty` is `[purchase_order_line_id => quantity]`, positive to receive.
  - Use a negative quantity to take a quantity back when a GRN is changed or cancelled.
  - Call it **inside the GRN's own transaction**.
- It locks the PO row, then its lines in id order. It refuses:
  - receiving against a PO that is not Approved or Partly received;
  - a line going above its quantity (no over-receipt tolerance);
  - a line going below 0.
- It sets the status to Partly received or Received, or back to Approved when everything is taken back, and logs the change.
- **Pending quantity:** `PurchaseOrderLine::pending()` = `quantity - received_quantity`.
- **Open POs for the GRN picker:** `PurchaseOrder::query()->forWork($unitId, $yearId)->pendingReceipt()`.
- **Pending approval:** `->pendingApproval()`. Dashboard tiles and the PO summary report can use the same scopes.
- **Once anything is received:**
  - the supplier cannot change;
  - a received line keeps its item and rate and cannot be removed or lowered below what was received;
  - the PO cannot be deleted or cancelled.
- `Closed` (short-close) is a reserved status. Its action comes with GRN.
- Stock does not move on a PO. GRN writes stock through `StockLedger`.

## Fixes after the tester's review (27/10/2026)
- **Supplier change:** a PO keeps an item that is already on it only while the supplier stays the same. A Draft switched to another supplier must use that supplier's items (S-4).
- **Delete:** only a Draft or Rejected PO can be deleted, Admin included. Use Cancel for a Pending or Approved PO. Cancel works only on Pending or Approved; a Draft or Rejected PO is deleted instead.
- **Admin edits an Approved or Partly received PO:**
  - If the value before GST went up, or the supplier changed, and the approval rule needs approval for the new value, the PO goes back to **Pending approval**. This writes a "Submitted" approval row with the old and new value, logs a status change, and clears approved by / on.
  - When it is approved again, it returns to Approved, Partly received or Received from the received quantities.
  - Otherwise the receipt status is worked out again as `receive()` does it. For example, lowering the open line to what was received makes the PO Received.
  - A lower value never needs a new approval.
- **Currency:** it is always the supplier's currency from the Party master. A different currency picked on the page is refused, and the service sets the supplier's currency anyway. GST / Import and "another currency always needs approval" follow the supplier.
- **`/app/po`:** it now redirects for a user with only Purchase order approval · View. Nav key `preview_perm` holds the allowed rights.
- **Not changed:** a Rejected PO can be sent for approval again without being edited first, as on sales orders.

## Decisions to confirm with the client
1. **Approval levels:** the prototype shows 2 levels above ₹1,00,000 (Purchase, then Admin). The built version is one level, the same as quotations and sales orders: required, with an amount above which the PO goes to an approver. Approvers are roles with Purchase order approval · Edit (Admin by default; the Purchase role does not get it).
2. **Approval amount:** the PO value before GST, as in the prototype (qty × rate). A PO in another currency always needs approval.
3. **Discount % per line:** added, because the brief asked for it. It is not in the prototype.
4. **Import supplier** (a party in a currency other than INR): no GST on the PO.
5. **Default addresses:** billing and shipping start as the company address, as in the prototype.
6. **Lines without a delivery date** take the header delivery date.
7. **No Import or CSV:** the prototype's page capability table has no PO entry. Generate PDF is built because the brief asked for it.
8. **Cancel:** anyone with Purchase order · Delete can cancel an approved PO, as long as nothing is received.
