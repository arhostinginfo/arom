# Create Sales Order from a customer PO (02/11/2026)

Developer report. Paths are relative to `D:\xampp\htdocs\nursery-app`. The feature was built on 02/11/2026; the same day
the Separate PO menu was removed (see `docs/crm-changes/direct-customer-po-report.md`, "Separate PO menu removed"), so
the button now lives on **CRM › 6 PO / Agreement** only.

## What the user sees

- **CRM › 6 PO / Agreement**, on a saved PO, has a **Create Sales Order** button next to "PO list".
  - It shows only for users with **Sales order · Add**. Without it there is no button, and posting to the route gives 403.
  - It is **disabled**, with the reason under it ("To create a sales order: …"), when:
    - the PO has **no Party customer** (a PO against a prospect's quotation): "Add this client to the Party master first,
      and choose it as the PO's customer.";
    - the PO has **no items**: "Add items to the PO first."
- Clicking it makes a **Draft** sales order in Sales and opens it, with the toast "Sales order SO/… made from PO …. Check it
  and send it for approval." A user who cannot view sales orders stays on the PO page with the same toast.
- The order is filled from the PO:
  - customer = the PO's Party customer; PO no = the PO / agreement no;
  - start date = the PO date (or the validity start); end date = the validity end, if any;
  - each PO item becomes an order line with the same part, quantity and rate; its delivery date is the PO's validity end,
    or the start date when there is none (the user checks it).
- Once made, the PO page shows **"Sales order SO/… · Draft, made from this PO."** and an **Open sales order** button
  instead of Create Sales Order. Users without sales order view see the number as plain text.
- The sales order page shows **"From PO …"**, linked to the CRM PO page for users with PO / Agreement · View in the PO's
  unit while CRM is on; otherwise plain text. A deleted PO shows "(deleted)".
- The order then follows the usual flow: Send for approval, Approve, and it appears in the **Tax invoice › PO no** list.
  The Tax invoice page now says why that list is empty: "No approved sales order for this party. Make one in Sales ›
  Sales order, or from a customer PO (Create Sales Order)."

## Rules

- The sales order's own rules apply (`SalesOrderService`): an active Customer / Both party (an inactive or Supplier party is
  refused with its name), the PO no not already on another order of the party (S-5, same message as in Sales), and each
  part an active sales item of the customer or of no party (S-4, naming the part).
- **One active order per PO.** The PO row is locked while the order is made, so two clicks at once queue and the second
  gets "Sales order SO/… is already made from this PO." Deleting that order allows a new one.
- Another unit's PO gives 403. A refusal returns to the PO page with "The sales order was not made." and the reason.
- Activity log: `sales_order.created` on the order and `customer_po.sales_order_made` on the PO, in the same transaction.

## Database

- Migration `2026_11_02_000001_add_customer_po_id_to_sales_orders`: `sales_orders.customer_po_id`, nullable foreign key to
  `customer_pos` (null on delete). POs are soft-deleted, so the link stays. Only adds.
- Dev MySQL `nursery`: run on 02/11/2026 (batch 19) after the backup
  `storage/app/backups/nursery-before-po-to-so-20261102.sql`. `migrate:status` has nothing pending.

## Files

- `app/Http/Controllers/Crm/CustomerPoController.php`: `salesOrder()`.
- `routes/web.php`: `POST crm/pos/{customer_po}/sales-order` (`crm.customer-pos.sales-order`), needs
  `customer-po.view` and `sales-order.add`. (The PO menu's `po.sales-order` route was removed with the menu.)
- `app/Services/SalesOrderService.php`: `createFromCustomerPo()`, `whyNoOrderFrom()`; `create()` takes the PO.
- `app/Models/CustomerPo.php`: `salesOrder()`, `unit()`, `pageUrlFor()` (CRM page only).
- `app/Models/SalesOrder.php`: `customerPo()`.
- `app/Support/SalesPage.php`: loads `customerPo`.
- `resources/views/crm/customer-pos/form.blade.php`: the button, its disabled hint and the "made from this PO" line.
- `resources/views/sales/page.blade.php`, `public/js/sales.js`: "From PO" on the order, the empty PO no hint.
- `tests/Feature/PoToSalesOrderTest.php`: 5 tests, all on the CRM page.

## Tests

`tests/Feature/PoToSalesOrderTest.php`:

- the button for Sales order · Add only, disabled without items or for a prospect's PO, then "Open sales order";
- the Draft order's customer, PO no, dates, items, totals, links both ways and logs; a PO with no end date;
- S-5 and S-4 refusals, an inactive customer, one active order per PO, a new one after delete;
- another unit's PO (403), no PO menu route, "From PO" as a link or plain text;
- the approved order in the Tax invoice PO no list and its lines.

Full suite: see the last line of `docs/crm-changes/direct-customer-po-report.md`.

## To try in a browser

1. CRM › 6 PO / Agreement: open a PO against a party's quotation that has items. Click **Create Sales Order**.
2. The Draft order opens in Sales with the PO's customer, PO no, dates and items. Send it for approval and approve it.
3. Back on the PO: "Open sales order" shows. On the order: "From PO …" links back.
4. A PO against a prospect's quotation, or with no items: the button is disabled with the reason.
