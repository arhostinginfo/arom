# Purchase › Stock transfer: developer report

27/10/2026. This replaces the prototype sample screen (`preview.js` `R.transfer`, D9) with a working page.

## What was built

- **Entry page** `/purchase/stock-transfers` (menu Purchase › Stock Transfer; the old `/app/transfer` address redirects here). It follows the Sales page layout:
  - entry picker, green **Add new** button, Stock transfer list;
  - approval bar (status, Send for approval / Approve / Reject);
  - header: Transfer date, Location from, Location to, Source type (Purchase / Credit / Debit, "The kind of inward document the stock came from."), Remarks;
  - items: an item picker that shows the stock at the source, the UOM from the Item master, quantity and remarks. While typing, the page warns "Quantity is more than the stock at the source" and "Location from and to are the same";
  - approval history, recent changes, Added / Updated by.
- **List** `/purchase/stock-transfers/list` with the tabs All / Pending approval / Approved. It is paged, searched (transfer no) and sorted on the server, and on phones each row becomes a card.
- **Admin › Approval** has a new "Stock transfer" card: Approval required Yes / No, one level, no amount. **Access rights** has a new "Stock transfer approval" right (View / Edit).
- **Admin › Page setup** has a new page, "Stock transfer" (group Purchase). Source type and Remarks can be shown or hidden and made mandatory; date and both locations are always shown and mandatory.
- **No PDF, CSV or Import.** The prototype's page capability table (CAP) has no entry for this page.

## Schema (migration `2026_10_27_200001_create_stock_transfers_tables`, additive only)

- **`stock_transfers`:**
  - number and dates: `transfer_no` (ST/26-27/0001, unique per source unit and year), `transfer_date`, `financial_year_id`;
  - locations and details: `from_unit_id`, `to_unit_id`, `source_type`, `remarks`;
  - status: `status` (Draft, Pending approval, Approved, Rejected, Cancelled), `status_reason`;
  - approval and cancel fields: `approved_by` / `approved_at`, `cancelled_by` / `cancelled_at`;
  - audit: `created_by` / `updated_by`, timestamps, soft deletes.
- **`stock_transfer_lines`:** `item_id`, `quantity`, `uom_id` (copied from the Item master on save), `remarks`.
- **Seeds:** the `approval_rules` row `stock_transfer` (required) and the permissions `stock-transfer-approval.view` / `.edit`.

## Stock behaviour

- **Draft, Pending and Rejected move no stock.** The quantity must not be more than the stock at the source. This is checked when the transfer is saved and when it is sent for approval.
- **Approve** runs in one transaction:
  1. It locks the `stock_balances` rows of both units, in unit then item order.
  2. It checks the source quantities again.
  3. It writes `StockLedger::move()` out of the source and into the destination, with `source_type` `stock_transfer` and `source_id` = transfer id.

  Two approvals that together need more than the source holds cannot both pass. The second is refused and stays Pending.
- **Approval rule switched off:** Send for approval approves and moves the stock straight away (recorded as "Approved automatically").
- **Cancel** needs a reason, and the number stays in the series. Cancelling an Approved transfer:
  - locks both balances and checks that the destination still holds every quantity it received;
  - then calls `StockLedger::remove()`, the same way a cancelled invoice is undone.

  If the destination does not still hold it, the cancel is refused and names each item that is short.
- **Delete** (soft) is allowed only for a Draft or Rejected transfer, which never moved stock.
- **StockLedger** is unchanged (contract kept).

## Decisions to confirm with the client

1. **Lines:** the prototype has one item per transfer. This build allows several items per transfer, and each item can appear only once.
2. **Purchase GRN field:** the prototype's "Purchase GRN" picker is not built, because GRN does not exist yet. Source type is stored as text and is optional by default.
3. **Unit scope:** both locations must be units the user may work in. A transfer is shown to users of either unit; the entry picker and the list show the year's transfers where the top-bar unit is the source or the destination. Changing, sending, deleting, cancelling, **approving and rejecting** need the **source** unit (review fix, 27/10/2026). A user of the destination only can view the transfer but not act on it.
4. **Location from is fixed after saving**, because the number belongs to that unit's series. To change it, cancel the transfer and add a new one.
5. **Date of the stock movement:** the movements are dated with the **transfer date**, not the approval date. The date must be in the current financial year and not in the future.
6. **Stock is not reserved by Draft or Pending transfers.** The final check is at approval.
7. **Who may cancel:** Stock transfer · Delete can cancel a transfer that is not Approved. Cancelling an Approved transfer also needs Stock transfer approval · Edit, because it reverses stock. Pending and Approved transfers cannot be edited, even by Admin.
8. **Reversal method:** a cancel deletes the transfer's ledger rows (`remove`), as invoice cancel does. Reversing rows were not added; this follows the earlier decision SL-S1, which did not take them.
9. **Default roles:** the Purchase role gets `stock-transfer.*` but not the approval right. Admin approves by default, as in the prototype.

## Not verified here

On this PC, Windows Application Control currently blocks `php_mbstring.dll` and `php_pdo_sqlite.dll`. Because of that, `artisan` (view:cache, route:list, migrate) and the test suite could not run. All PHP files pass `php -l`, and the Blade views were compiled and linted with a stand-alone compiler. `tests/Feature/StockTransferTest.php` (8 tests) still needs a run, and so does the migration on the local database.
