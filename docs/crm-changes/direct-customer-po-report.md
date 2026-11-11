# Direct customer PO and Part master items: report (29/10/2026)

## The request

> Separate the Purchase Order (PO) form from the CRM so it supports two workflows: a **Direct PO** (no CRM process) and a
> **CRM-based PO** (the full CRM workflow, as today). The PO module should work on its own and stay integrated with the CRM.
> The **Part Master** should be a dropdown in the PO form, so users pick parts from the master data when adding items.

"PO" here is the **customer PO / agreement received**: CRM › *6 PO / Agreement* (`crm.customer-pos.*`). It is not the
Purchase › Purchase order (supplier PO). "Part Master" is the **Item master** (`items`).

## What changed for the user

- The PO form now starts with **PO type**:
  - **Against CRM quotation**: works as before. The quotation is required, and the client, enquiry and unit come from it.
    Saving sets the quotation to Accepted and the enquiry to PO received.
  - **Direct PO**: no enquiry and no quotation. You pick a **Customer** from the Party master (Customer or Both parties,
    or any active party that has sales items). Site, contact, terms and the other fields are typed in as usual.
- **Items (Part master)** are on both types. A searchable dropdown lists *Part no · Description · IPN* from the Item
  master. Picking a part fills in the description, UOM and the Item master price as the rate. All three can still be
  edited. Amount = Quantity × Rate.
  - A **Direct PO** needs at least one item. Its **PO value is the sum of the item amounts**, worked out on the server,
    and the field is read-only on the form.
  - On a **CRM PO** items are optional, and the **PO value stays as typed** (backward compatible). The items total is
    shown beside the table for reference.
- You choose the type when you first save the PO. **It cannot change afterwards**: the saved PO shows its type as a badge
  and as read-only text.
- `/crm/pos/create?type=direct` opens a new PO with Direct already chosen.
- Search (entry bar and lookup) also finds direct POs **by customer name**. The lookup shows "Direct PO" in its sub-line.

## Decisions

| Topic | Decision |
|---|---|
| How the type is stored | New column `customer_pos.source` (`crm` / `direct`, default `crm`), so every existing PO is a CRM PO. It is stored explicitly, not worked out from `quotation_id`: a CRM PO's quotation link is nullable today, and an explicit column makes reports and checks simple. |
| Switching type on edit | **Refused** (request and service): "This PO was saved as "…", so its type cannot change. Enter a new PO instead." This is the safe option: a CRM PO keeps its enquiry and quotation history (Accepted / PO received), and a direct PO cannot be pulled into an enquiry afterwards. |
| Which items a PO may have | Active **Sales / Both** items of the customer **plus items of no party**, the same rule as the Sales page (S-4 / A22). For a CRM quotation whose client is still a prospect (no party), any active sales item. An item already on the PO (made inactive later, say) is kept while the customer stays the same. UOM must be active, or already on the PO. All of this is checked on the server. |
| Rate | Left blank, the rate is the Item master price. Item currency is **not** converted (see open questions). |
| Duplicate PO no | CRM PO: unique per enquiry (as before). Direct PO: unique **per customer**, checked against all that customer's POs. A CRM PO is also refused when a *direct* PO of the same customer already has that number, so one customer PO cannot be entered both ways. The customer row is locked during the save, so two POs saved at the same moment cannot both pass the check. |
| Unit | A CRM PO takes its quotation's unit (as before). A direct PO takes the unit on the top bar. With no unit, saving returns 403 (as on the Purchase order page). Show / update / delete still check the PO's unit. The item lookup with `?quotation=` only accepts quotations of the user's units. |
| Independent access | The page needs only the `customer-po.*` rights. It has its own lookups, `crm.customer-pos.parties` and `crm.customer-pos.items` (both `customer-po.view`), so it does not depend on the Sales or Quotation rights. The quotation picker already works with `customer-po.view` (existing route middleware), so it stays available to PO users. The enquiry and quotation links on a saved PO show only with `enquiry.view` / `quotation.view`. |
| Menu | The entry stays under CRM › *6 PO / Agreement*. **It is not added to the Sales menu.** Doing so would make the Sales menu appear for CRM-only users who have `customer-po.*` (this breaks `MenuBarTest::test_crm_only_user_sees_only_the_menus_they_may_open`), and both menus would highlight at once. It is a one-line config change if the client wants it (see open questions). |
| CRM dashboard | The CRM dashboard's "PO received" chart, PO count, PO value and conversion % now count **CRM POs only**. A direct PO is not a converted lead. The dashboard **tile** "PO / Agreements" (CrmCounts) still counts **all** POs of the unit, the same as the list it opens. |
| Items hidden in Page setup | "Items on a CRM PO" in Admin › Page setup can be shown, hidden or made mandatory. While it is hidden, a CRM PO's items are not read or changed (what was saved stays), like any other hidden field. A direct PO always shows its items and needs at least one. |
| Delete | Soft delete, as before. The PO keeps its items (as it keeps its documents). A direct PO does not touch any enquiry. Items on customer POs count as "used" in the Item master, so they cannot be deleted. |
| PDF / CSV | Customer POs have no export, so nothing was added. |

## Files

### New

- `database/migrations/2026_10_29_000001_add_direct_customer_pos.php`
  - `customer_pos.enquiry_id` becomes nullable. It uses `->change()`: MariaDB modifies the column in place, and SQLite
    rebuilds the table keeping FKs and indexes (verified up / down / up on a temporary SQLite database). The FK stays.
  - Adds `customer_pos.source` varchar(10) default `crm`, and the index `customer_pos_party_po_index (party_id, po_no)`.
  - Creates `customer_po_lines` with these columns:
    - `customer_po_id`: FK, cascade on hard delete
    - `line_no`
    - `item_id`: FK items, restrict
    - `description`: snapshot, varchar 255
    - `uom_id`: nullable, FK uoms, restrict
    - `quantity`: decimal(15,3). Named `quantity` rather than `qty` to match `purchase_order_lines` and `sales_order_lines`.
    - `rate`: decimal(15,4)
    - `amount`: decimal(15,2)
    - timestamps
    - index `(customer_po_id, line_no)`
  - `down()` refuses (RuntimeException) while direct POs exist. Otherwise it drops the lines table, the index and the
    column, and makes `enquiry_id` NOT NULL again.
- `app/Models/CustomerPoLine.php`: the line model (item, uom, PO relations).
- `public/js/customer-po.js`: the type toggle, item rows (add / remove / renumber), filling a row from the picked item,
  amounts and items total, the direct PO value, the item pickers following the chosen customer / quotation, and the items
  sent as one `lines_json` field (as on the Purchase order page).
- `tests/Feature/CustomerPoDirectTest.php`: 10 tests (see below).

### Changed

All originals are backed up under the scratchpad `direct-po-backup` folder, with their relative paths kept.

- `app/Models/CustomerPo.php`
  - Adds the `CRM` / `DIRECT` / `SOURCES` constants, `isDirect()`, `sourceLabel()` and `lines()`, and a default `source`.
  - `activityLabel()` uses the customer name when there is no enquiry.
- `app/Http/Requests/Crm/CustomerPoRequest.php`
  - Adds `po_type`. When it is missing, it defaults to `crm` for a new PO, or the saved type on update, so old forms and
    tests still work.
  - Direct: `party_id` required, `quotation_id` ignored (forced to null), `po_value` not required.
  - Lines are parsed from `lines_json` or `lines[]`, with blank rows dropped.
  - Line rules: at least one for a direct PO; for a CRM PO as set in Page setup. Item / party / UOM checks are in `after()`.
  - The type change is refused. New messages are in short British English.
- `app/Services/CustomerPoService.php`
  - create / update / delete work without an enquiry. `markAccepted` / `refreshStatus` run only for CRM POs.
  - Lines are saved in the same transaction (`saveLines`, matched by id), and a direct PO's `po_value` is their sum.
  - Logging: `customer_po.created/updated/deleted` include the items. Update logs the item diff (`items.added/removed`)
    via `ActivityLogger::listDiff`. The snapshot now includes "PO type".
  - Duplicate rules as above. The deadlock retry (`ATTEMPTS`) is kept on every transaction.
  - New optional `?Unit $unit` argument on `create()` for direct POs.
- `app/Http/Controllers/Crm/CustomerPoController.php`
  - `store` passes the top-bar unit and returns 403 with no unit for a direct PO.
  - New `parties()` and `items()` lookups, plus the helpers `customers()` and `itemData()`.
  - Search also matches direct POs by customer name. The lookup sub-line marks direct POs.
  - The form gets the type, customer options, item options (up to 200 for use without typing), UOMs and the item lookup
    URLs.
  - `blank()` accepts `?type=direct`. `?quotation=` still fills a CRM PO.
- `resources/views/crm/customer-pos/form.blade.php`
  - PO type (radio group on a new PO; read-only and hidden input on a saved one), quotation block (CRM), customer block
    (direct).
  - PO value is read-only for a direct PO.
  - Items table with a Part master picker, description, quantity, UOM, rate and amount, plus the items total. The table
    stacks as cards on narrow screens.
  - Type badge, and a quotation link for users with `quotation.view`.
  - The rule line adapts to the type.
- `routes/web.php`: adds `GET crm/pos/parties` (`crm.customer-pos.parties`) and `GET crm/pos/items`
  (`crm.customer-pos.items`), both with `permission:customer-po.view`.
- `config/nursery.php` (`page_fields.customer-po`)
  - Adds `po_type` "PO type" (fixed, mandatory) and `party_id` "Customer (direct PO)" (fixed, mandatory).
  - Adds `crm_lines` "Items on a CRM PO" (optional; can be hidden or made mandatory).
  - "Quotation" is relabelled "Quotation (CRM PO)".
- `app/Services/CrmDashboard.php`: the PO chart and KPIs count `source = crm` only.
- `app/Services/ItemService.php`: `customer_po_lines.item_id` added to `USED`, so an item on a customer PO cannot be deleted.

### Checked, no change needed

- `EnquiryService::refreshStatus()` already returns early for a null enquiry.
- `Quotation::scopePoPending()` correlates on `customer_pos.enquiry_id = quotations.enquiry_id`; NULL never matches, so
  direct POs are ignored.
- `CrmFlow`, `CrmReportController` and the enquiry delete count go through `Enquiry::customerPos()`, so they only see CRM POs.
- `CrmCounts` counts all POs, the same as the list.
- `DocumentController` checks the PO's unit and rights, not its enquiry. Documents on direct POs work.
- `QuotationService` (revisions with a PO) and the `PartyService` / `MasterService` "used" lists already cover
  `customer_pos.party_id` / `received_by`.

## Database

- MySQL (MariaDB 10.4) dev database `nursery` was backed up to
  `storage/app/backups/nursery-before-direct-po-20261029.sql` before migrating.
- `php artisan migrate` ran `2026_10_29_000001_add_direct_customer_pos`. The 1 existing PO is now `source = crm`.
- `php artisan view:clear` was run.

## Tests

`tests/Feature/CustomerPoDirectTest.php` covers:

- a direct PO with items, where value = sum, a blank rate means the item price, a blank description / UOM come from the
  item, plus the log and the page
- the customer is required, at least one item is required, quantity > 0, a supplier is refused, and the quotation and
  value are ignored on a direct PO
- items refused: another party's item, an inactive item, a purchase-only item, an unknown UOM; items allowed: no-party
  items and Both items; the picker lists match the rule
- duplicate PO no per customer, and the cross-check between CRM and direct POs
- a CRM PO is unchanged (no type posted, Accepted / PO received, value as typed) and may take optional items; the
  prospect quotation and party quotation rules; Page setup making CRM items mandatory or hidden
- editing a direct PO's items: the kept row keeps its id, the value is recalculated, the item diff is logged, an inactive
  item is kept, the type change is refused, and the type is kept when not posted
- deleting a direct PO: soft delete, log with items, and the item can no longer be deleted
- a user with only `customer-po.view/add/edit` can open the page, use both lookups, save and view a direct PO, and is
  refused on the enquiry and quotation pages; a view-only user cannot save
- a direct PO next to a CRM PO: the dashboard, CrmCounts (2), CRM dashboard KPI (1), enquiry list / page, PO-pending list,
  PO list, search by name, the documents box and the activity log all work
- unit scoping: show / update / delete of another unit's PO give 403, the lookup hides it, a quotation of another unit
  gives no items and cannot take a PO, and a user with no unit cannot save a direct PO

Full suite: `Tests: 368 passed (7362 assertions)`. It was 358 before; the 10 new tests are added.

## For the tester

1. CRM › 6 PO / Agreement › **New PO / agreement**, choose **Direct PO**.
   - The quotation picker hides, Customer shows, and PO value becomes read-only.
   - Pick a customer, then **Add item**. The part dropdown lists that customer's Sales / Both parts and parts of no party.
   - Pick a part: the description, UOM and rate fill in. Change the rate or quantity and watch the amount, the items total
     and the PO value.
   - Save, then reopen. The type badge says Direct PO and the type cannot be changed.
2. Try saving a direct PO with no customer, with no items, or with a part of another customer: each is refused with a
   message.
3. Enter a direct PO number twice for the same customer (refused), then for another customer (allowed).
4. Enter a PO **against a CRM quotation** exactly as before. Check the quotation becomes Accepted and the enquiry
   PO received. Add a couple of items: the PO value stays as typed.
5. Admin › Page setup › PO / Agreement received: hide "Items on a CRM PO". The CRM PO form no longer shows the items
   (saved items stay), but a direct PO still shows them. Make it mandatory: a CRM PO then needs an item.
6. Give a role only *PO / Agreement received* rights (view / add / edit). Sign in as that user and enter a direct PO.
   The customer and part pickers must load.
7. Dashboard: the CRM dashboard PO numbers include CRM POs only; the "PO / Agreements" tile includes both types.
8. Search the PO entry bar by a direct PO's customer name.

## Open questions for the client

1. **Sales menu**: should "Customer PO" also be listed under the Sales menu (same page)? It is left out because CRM-only
   users would then see a Sales menu. It can be added if wanted.
2. **Which customers on a direct PO**: Customer / Both parties plus any party with sales items. Should it be Customer /
   Both only?
3. **Parts of no party**: these are offered on every customer's PO, as on the Sales page. Should a customer PO list
   *only* that customer's parts?
4. **Currency**: the Item master price is used as the rate as it is, and PO value is in ₹. Are there customer POs in
   another currency, or items priced in USD that need converting?
5. **CRM PO value**: should a CRM PO with items also take its value from the item total (like a direct PO), instead of
   the typed value?
6. **Dashboard**: should direct POs appear on the CRM dashboard's PO chart / conversion %, or in a separate Sales figure?
7. **Next step**: should a customer PO (either type) be able to create a Sales order from its items? Not built.

## Tester review (29/10/2026)

Code read: controller, service, request, both models, form, `customer-po.js` (with `picker.js`, `app.js`, `crm.js` on the
same page), `CrmDashboard`, `ItemService`, routes, `page_fields.customer-po`, the migration, and every use of
`customer_pos` / `CustomerPo` / `customerPos` in `app`, `resources`, `routes`, `config` and `database`. Originals of the
files changed here are in the scratchpad `direct-po-tester-backup` folder, with their relative paths kept.

### Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| T1 | High (rollback) | `down()` failed on MariaDB 10.4: "Cannot drop index 'customer_pos_party_po_index': needed in a foreign key constraint". On a fresh database MariaDB drops the `party_id` foreign key's own index when `(party_id, po_no)` is added. Found by running up / down / up on a scratch MariaDB database (created and dropped again; the dev DB `nursery` was not touched). The dev DB still has its `party_id` index, so it was not affected there. | **Fixed**: `down()` adds a `party_id` index back (MySQL / MariaDB only) when no other index starts with `party_id`. Up / down / up now passes on scratch MariaDB and SQLite (FKs and indexes kept, `PRAGMA foreign_key_check` clean). Only `down()` changed, so the dev DB needs no re-run. |
| T2 | Medium | Line amount and direct PO value are `decimal(15,2)`, but quantity (up to 10^12) × rate (up to 10^11) was not limited. On MariaDB (strict) a large amount or total gives an error page; SQLite saved it. | **Fixed**: the request refuses a line amount over 9,999,999,999,999.99 ("The amount of item n is too large…"), with a blank rate counted as the item's price, and a direct PO whose items total is over that ("The items total is too large for one PO."). |
| T3 | Medium | A UOM picked on a PO item could not be deleted cleanly: `customer_po_lines.uom_id` was missing from the UOM master's "used" list, so Delete hit the foreign key (error page) instead of "is used on n record(s)". | **Fixed**: added to `config/nursery.php` `sales_masters.uoms.used`. |
| T4 | Low | Quantity below 0.001 (for example 0.0004) passed `gt:0` but was saved as 0.000. | **Fixed**: the rule is `min:0.001` ("The quantity must be at least 0.001."). |
| T5 | Low | A tampered row (an array in a line field) or `po_type[]` gave an error page: `htmlspecialchars()` on the redisplayed form, and "Array to string" on `po_type`. | **Fixed**: such values are read as blank (`cleanLines`), the form shows only plain values again, and a non-text `po_type` is refused as "Choose the PO type." |
| T6 | Low (JS) | On a saved PO (or after an error), changing the part on an existing row kept the old part's description and rate: only rows added in the browser were marked "auto". | **Fixed** in `customer-po.js`: a description or rate still equal to the item's own follows the new part; one typed over stays. |
| T7 | Low (JS) | Switching a new PO to Direct and back to CRM replaced the typed PO value with the items total. | **Fixed**: the typed value comes back. |
| T8 | Low (JS, a11y) | After removing a row and adding one, two selects could share an `id` (`cpo_item_n`), and so two picker buttons. | **Fixed**: new rows take ids from a running counter. |
| T9 | Low | Race: a CRM PO and a direct PO of the same customer with the same number, saved at the same moment, can both pass, because the CRM path locks the quotation and not the customer. | Open (rare; lock the party in the CRM path too if wanted). |
| T10 | Low | Item diff in the log uses `array_diff` on line texts, so removing one of two identical lines (same item, quantity, rate) is not logged as removed. | Open. |
| T11 | Low (info) | `crm.customer-pos.items?party=` lists the sales items of any party id, including a supplier; it is not limited to customers. Items are master data, so no PO data is exposed. | Open, question. |
| T12 | Low (pre-existing) | Other tampered array fields (`po_value[]`, `quotation_id[]` on the redisplayed form) can still give an error page. They are older code, not part of this change. | Open. |

Checked and fine: unit scoping (store, show, update, delete, lookups, quotation item lookup), `FIELDS` excludes
`source`, `unit_id`, `enquiry_id`, `party_id`, `prospect_id` and `created_by` (mass assignment), line ids of another PO
are not taken over (a new row is made), the type change is refused in the request and in the service, the duplicate
number rules and the customer lock, Page setup (`po_type`, `party_id`, `po_value` are fixed; hidden CRM items are not
read), rights (`customer-po.add` and `.edit` are separate), the activity log, every null enquiry use (CrmFlow, reports,
PO pending, enquiry status, documents, dashboard), and the soft delete keeping the items.

Questions for the developer / client (design, not changed):

1. When the customer of a direct PO is changed, the rows of the old customer's parts stay until save, which then
   refuses them. Should the page clear or flag them?
2. The same part can be entered twice on one PO. Is that wanted (for example, different delivery lots), or refused as a
   duplicate?
3. Should T9 be closed by locking the customer in the CRM path as well?

### Tests added

`tests/Feature/CustomerPoDirectReviewTest.php` has 10 tests:

- amounts that do not fit the columns (one line, a blank rate taken as the item price, the total, and just within the limit)
- a quantity that rounds to nothing, a negative quantity or rate, a text rate, 201 items, negative or 0 item ids
- tampered rows: the form shows again with a message, and a tampered description or rate is read as blank
- a line id of another PO is not taken over
- type, unit, enquiry, party, prospect and created_by cannot be posted; an unknown or array type is refused; a supplier
  is refused on edit
- changing a direct PO's customer checks the items again, and recalculates the value; an edit without items is refused
- add and edit rights are separate (edit-only can update, not create or delete; add-only sees a read-only saved PO)
- a CRM PO's items are not read while hidden in Page setup, and a direct PO still needs them
- a UOM on a PO item cannot be deleted
- the migration does not roll back while direct POs exist

Before the fixes, 4 of them failed (T2, T3, T4, T5).

Full suite: `Tests: 378 passed (7452 assertions)`. `artisan view:clear` was run, `artisan migrate:status` shows nothing
pending, and `http://localhost/nursery-app/crm/pos` answers 302 to the login page.

Still to check in a browser: the type toggle and the picker on rows added after a remove, the part dropdown following a
changed customer or quotation, description / rate following a new part on a saved row, the totals and the read-only
direct PO value, the PO value coming back after Direct → CRM, the card layout below 900 px, and the page after a
validation error (rows, totals and pickers shown again).


---

# PO as its own module (01/11/2026)

## The request

> "how this will work PO and CRM separately, not done as per requirement"

The client chose:

- **PO gets its own top menu**, right after CRM: Dashboard | CRM | **PO** | Sales | Purchase | … The PO menu has New PO,
  PO / Agreement and PO list. Its reports (the new PO register) show in the side menu when PO is selected.
- PO **must not depend on the CRM sub-menus**. "PO / Agreement" is no longer in the CRM menu. CRM still links to the PO
  page from the enquiry workflow step and from the quotation's "Enter PO / agreement" button.
- PO must **work with CRM switched off**, and has its own module switch.

Extra requirement (same day):

> "PO itself should run independently even if CRM completed steps data like Quotation is not provided."

- A PO never needs CRM data. A customer (Party master), PO no, dates, and items or a value are always enough.
- The hard "PO type" choice is replaced by an **optional link to CRM**: an Enquiry and / or a Quotation.
- **No CRM step is needed** to link. A Draft, a quotation waiting for approval, an unsent or a rejected quotation, and an
  enquiry at any status can all be linked. Only the quotation's current revision can be newly linked.

## What changed for the user

- **Top menu PO** (after CRM) has these pages:
  - **New PO**
  - **PO / Agreement**: the entry page, as before
  - **PO list**
  - In its side menu, **PO register** (CSV / PDF).
- The PO page now lives at **`/po`**. Old `/crm/pos…` addresses (bookmarks, links in emails) redirect permanently (301)
  to the same path under `/po`, with the query string kept: `/crm/pos/create?quotation=5` goes to
  `/po/create?quotation=5`. Breadcrumbs say PO › PO / Agreement, not CRM.
- **PO form**:
  - **Customer** (Party master) is always mandatory.
  - **Link to CRM (optional)** holds an Enquiry picker and a Quotation picker. It is shown only while CRM is on, it is
    shown in Page setup, and the user can view enquiries / quotations; each picker needs its own View right. Leave both
    blank for a direct PO.
  - Picking a quotation gives its enquiry. When the quotation or enquiry has a Party customer, the PO's customer must be
    the same one. Left blank, the customer is taken from it. A prospect's quotation or enquiry still needs a Party
    customer chosen; the prospect is kept on the PO for reference.
  - Opening the page from CRM (`?quotation=` or `?enquiry=`) fills in the link, customer, site, value, terms and
    contact. All of these can still be changed.
  - With CRM off, a linked PO shows its link as read-only text: "CRM is switched off; the link is kept".
- **Items or value**: a PO needs at least one item **or** a typed PO value. With items, the **PO value is the sum of the
  items** (worked out on the server, read-only on the form). Without items, the value is typed.
- **Type** ("Linked to CRM" / "Direct PO") is worked out from the link on every save. It shows as a badge, in the list
  and in the register. Linking and unlinking on edit are allowed.
- **Side effects only when linked**:
  - The enquiry is refreshed: PO received while it has a PO. On re-link or unlink, the old enquiry is refreshed too,
    so it can become Open or Lost again.
  - A quotation already with the customer (Sent, Viewed / Feedback, Negotiation, Hold) becomes **Accepted**.
  - Any other quotation (Draft, Internal approval, Approved but not sent, Rejected, Expired) **keeps its status**, and
    "PO … linked" is logged on it. Unlinking logs "PO … is no longer linked" on the quotation, and its status stays.
- **CRM workflow step "PO / Agreement"**:
  - It ticks whenever a PO is linked to the enquiry.
  - Without a PO it opens `/po/create?quotation=<current quotation>`, or `?enquiry=<id>` when there is no quotation, at
    any step (it used to need a sent quotation).
  - With the PO module off, or without PO rights, it shows its state with no link.
- **PO list** (`/po/list`):
  - Paged on the server, 25 a page, latest PO date first. It can be sorted by PO date, PO no or value.
  - Search by PO no, customer name or enquiry no ("starts with").
  - Views: All / Linked to CRM / Direct PO. A From / To PO date range; a wrong date shows a message and is not applied.
  - **Download CSV** of the filtered list: one row per PO, with Items (count), Items total and PO value.
- **PO register** (PO › reports): From / To PO date (default: current financial year), unit, PO type, CSV or PDF (the
  ReportPdf row cap). It needs only PO View.
- **Dashboard**: the "PO / Agreements" tile moved to its own **PO** section. It counts every PO of the unit and opens
  the PO list. It shows while the PO module is on and the user has PO View, with CRM on or off.
- **Access rights**: the `customer-po.*` rights now show under a new **PO** group. The names did not change, so every
  role keeps its rights.
- **Admin › Page setup › Optional parts** has two new switches, both on by default:
  - **CRM**: off hides the CRM menu, pages, reports, alerts and dashboard. POs keep working without the link, and
    linking is refused.
  - **PO / Agreement**: off hides the PO menu and pages, the PO tile, and the PO link on the CRM step. PO pages say
    "switched off" (404, the same as Site visit).
- Admin › Page setup › PO / Agreement received is now under the **PO** heading:
  - "PO type" was removed.
  - "Customer" is fixed.
  - "Link to CRM (enquiry / quotation)" can be shown or hidden.
  - "Items (Part master)" (key `crm_lines`, kept so saved choices still apply) now applies to **every** PO: shown by
    default; Mandatory means at least one item; hidden means items are not read and the value is typed.

## Decisions

| Topic | Decision |
|---|---|
| CRM switch | There was no CRM switch before, so one was added: module `crm`. Every CRM page right (enquiry, site-visit, quotation, quotation-approval, prospect, contact, reports-crm) and every CRM Page setup page belongs to it. A page may now belong to **several parts** (Site visit = `crm` + `site_visit`): `Modules::allows()` / `offOf()` take a list, and `Access::moduleOf()` returns the first part that is off, so the existing gate (module switch, then Admin, then spatie) refuses it. The CRM routes also have `module:crm`, so they show "CRM is switched off", not 403. |
| PO switch | Module `po` (label "PO / Agreement"), default on. Stored like the other switches (`module_settings`, no row = default), so **no migration or seed**. `customer-po.*` belongs to `po`; `/po/*` has `module:po`. |
| Permissions | Kept as `customer-po.*`. The PO register uses `customer-po.view` (`report_perms.PO`), so no new permission and no role change are needed. |
| Source | `customer_pos.source` stays, but is now worked out on every save: `crm` when `enquiry_id` or `quotation_id` is set, `direct` otherwise. No migration: existing rows already match (CRM POs have an enquiry, direct ones none). |
| Unit | A new linked PO takes the unit of its quotation / enquiry. A new direct PO takes the top-bar unit (403 with none, as before). A saved PO keeps its unit, and a link must be in that unit. |
| When the link is read | Only while CRM is on, the link is shown in Page setup, and the user has `enquiry.view` / `quotation.view` (one per picker). When it is not read, a saved PO **keeps its link** (so saving with CRM off does not unlink), and a posted link that would change it is refused: "CRM is switched off, so a PO cannot be linked to an enquiry or quotation." or "You cannot link enquiries / quotations to a PO." If neither link field is posted (older forms, API-like posts), the saved link is kept. |
| Quotation checks | Any status. Refused: deleted, another unit, a quotation of another enquiry than the one chosen, and an **earlier revision** (unless it is already the PO's quotation). |
| Accepted | Only a quotation already with the customer and not Rejected / Expired becomes Accepted (the old `canTakePo()` rule, now used only for that). |
| Duplicate PO no | Unique **per customer** for every PO, and also per enquiry when linked. The customer row is locked during the save. This closes the T9 race from the 29/10 review. |
| Old `po_type` field | Ignored, except that `po_type=direct` (older forms) means "no link". The "type cannot change" rule is gone, because link / unlink is the new behaviour. |
| Dashboard | The PO tile is no longer in the CRM section (`CrmCounts::poTiles()`, view variable `poTiles`, section `dash-po-h`), so it shows with CRM off. The CRM dashboard KPIs still count linked POs only. |
| PO pending alert | Stays in CRM: it lists quotations, `crm.quotations.list?view=po-pending`. It shows only while CRM is on, and its quotations open the quotation page, whose "Enter PO / agreement" goes to `/po/create?quotation=`. |
| Documents | `DocumentController` type `customer_po` now belongs to the `po` part (enquiry / quotation to `crm`, site visit to both). The document routes were moved out of the `module:crm` group, so PO and sales order documents work with CRM off. Upload / delete return with `back()`, so they land on `/po/{id}`. |
| CRM reports about POs | Left in CRM (Lead report). |

## Behaviour changes (existing tests updated, none deleted)

| Before | Now | Tests updated |
|---|---|---|
| Quotation mandatory for a CRM PO; customer came from it | Customer always mandatory; quotation an optional link (a prospect's quotation needs a Party customer) | CrmChangesTest, CrmFiveEntryFlow (newPo), CrmNoDeleteButtonTest, CrmReviewFixesTest, CustomerPoUploadTest, PageSetupAndModulesTest, CustomerPoDirectTest: payloads now post `party_id` (new fixture `CrmFixtures::poCustomer()`) |
| PO refused for a Draft, pending, Rejected or Expired quotation | Allowed; the status is kept and the link logged | CrmReviewFixesTest::m2 (Draft / pending save; earlier revision still refused), CrmFullTestFixesTest::b2 (Rejected saves, status kept) |
| CRM PO value stayed as typed even with items | With items, value = sum (every PO) | CustomerPoDirectTest::crm_po… |
| Direct PO needed at least one item | Items **or** a typed value | CustomerPoDirectTest, CustomerPoDirectReviewTest |
| Type fixed once saved; unknown `po_type` refused | Type follows the link; link / unlink on edit; `po_type` ignored | CustomerPoDirectTest::editing…, CustomerPoDirectReviewTest::type_unit… |
| Hidden "Items on a CRM PO" applied to CRM POs only | Hidden items apply to every PO (value typed) | CustomerPoDirectReviewTest::items… |
| Number unique per enquiry (CRM) or per customer (direct) | Per customer always, plus per enquiry when linked | CustomerPoDirectTest message |
| PO tile in the CRM dashboard section, opening the PO page | PO section, opening the PO list | DashboardCrmCountsTest |
| Seven top menus; CRM step 6 PO / Agreement | Eight (PO after CRM); no step 6 in the CRM menu | MenuBarTest, UiRound2Test |
| Three module switches | Five (crm, po first) | CrmFiveEntryFlow / PageSetup tests' `setModules` helpers, PageSetupReviewFixesTest toast and cache |

## Files

All originals are backed up in the scratchpad `po-module-backup` folder, with their relative paths kept (tests included).

### New

- `app/Http/Controllers/Po/CustomerPoController.php`
  - Moved from `Crm/`, namespace `App\Http\Controllers\Po`.
  - Adds `list()`, `export()` and the shared `listQuery()`.
  - The optional link: `blank()` takes `?quotation=` / `?enquiry=`, with enquiry / quotation options at any status.
- `app/Http/Controllers/Po/PoRegisterController.php`: PO register panel and CSV / PDF download.
- `app/Http/Requests/Po/CustomerPoRequest.php`
  - Moved from `Requests/Crm` and rewritten.
  - Customer always mandatory; items or value.
  - The optional link: `canLink()`, `link()`, `poData()`, `checkLink()`.
- `resources/views/po/form.blade.php`
  - Moved from `crm/customer-pos/form.blade.php`.
  - Customer always shown; "Link to CRM (optional)" block; no type radio; PO value read-only with items.
- `resources/views/po/list.blade.php`, `resources/views/po/register.blade.php`
- `tests/Feature/PoModuleTest.php`: 11 tests (see below).

### Removed (moved)

- `app/Http/Controllers/Crm/CustomerPoController.php`
- `app/Http/Requests/Crm/CustomerPoRequest.php`
- `resources/views/crm/customer-pos/form.blade.php`

### Changed

- `config/nursery.php`
  - `pages`: new **PO** group with `customer-po`; `module => 'crm'` on CRM pages and `reports-crm`; site visit
    `['crm', 'site_visit']`.
  - `nav`: CRM step 6 removed; new `po` top menu after CRM.
  - `reports` / `report_routes` / `report_perms`: new `PO` group (PO register, `po.reports.register`,
    `customer-po.view`).
  - `modules`: `crm` and `po` added.
  - `page_fields`: CRM pages get `module => 'crm'`. `customer-po` moves to group PO with module `po`: `po_type` removed,
    `party_id` "Customer", `crm_link` added, `po_value` "PO value (when no items)", `crm_lines` relabelled
    "Items (Part master)".
- `routes/web.php`
  - New `po.*` group (`module:po`).
  - `crm/pos/{rest?}` 301 redirect (outside auth).
  - CRM group under `module:crm`; document routes in their own `crm.` group without it; `reports/crm` under
    `module:crm`.
- `app/Services/CustomerPoService.php`: rewritten for the optional link (`linkOf`, `setLink`, `linkQuotation`), link /
  unlink on edit with both enquiries refreshed, value = sum with items, and the per-customer number rule. Still
  in-transaction logging with the deadlock retry.
- `app/Models/CustomerPo.php`:
  - SOURCES labels are now "Linked to CRM" / "Direct PO"; the default source is `direct`.
  - `quotation_id` is out of `FIELDS` (the service sets it).
  - Adds `isLinked()` and the `unit()` relation.
- `app/Support/Modules.php`: `allows()` takes a list; adds `offOf()`.
- `app/Support/Access.php`: `moduleOf()` handles pages of several parts.
- `app/Http/Controllers/Admin/PageSetupController.php`: the "switched off with …" label for pages of several parts.
- `app/Support/CrmFlow.php`: the PO step links to `po.show` / `po.create` (`?quotation=` or `?enquiry=`), at any step,
  following the PO rights and switch.
- `app/Services/CrmCounts.php`: the PO tile is out of `tiles()`; new `poTiles()`.
- `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`: the PO section.
- `app/Services/CrmAlerts.php`: "PO pending" only while CRM is on.
- `app/Http/Controllers/Crm/DocumentController.php`: record types belong to `crm` / `po` parts.
- `resources/views/crm/quotations/status.blade.php`: "Enter PO / agreement" goes to `po.create`.
- `public/js/customer-po.js`: no type toggle; the item pickers follow the customer; PO value = items total (read-only)
  while there are items, typed otherwise (the typed value comes back when the items are removed).
- Tests updated as listed above: `/crm/pos` → `/po` and `crm.customer-pos.*` → `po.*` everywhere, with the same
  meaning.

## Database

No migration. The switches live in `module_settings`, and a missing row means the config default (on). `source` needs no
data change. The dev DB was therefore not backed up again or migrated. `artisan migrate:status` shows nothing pending.

## Tests

`tests/Feature/PoModuleTest.php` (11 tests) covers:

- the PO top menu is right after CRM with New PO / PO / Agreement / PO list, and the CRM menu has no PO entry
- the PO register is in the PO side menu, and Access rights shows the PO group
- `/crm/pos`, `/crm/pos/create?quotation=`, `/crm/pos/{id}` and `/crm/pos/list?…` redirect with 301
- a PO saves with no CRM data while CRM is on: value = items sum; a typed value without items; neither is refused; the
  customer is required; the number is unique per customer
- **CRM off**:
  - the PO menu is still there, and the CRM pages say switched off
  - the link is hidden and refused, `?quotation=` is ignored, and a direct PO saves
  - the list and the PO page open
  - the dashboard PO tile shows without the CRM section, and there is no PO pending alert
  - a PO linked earlier keeps its link when edited
- **PO off**:
  - the menu is hidden, and `/po`, create, list, export, register and store answer 404
  - the gate refuses `customer-po.view`, and Access rights has no PO group
  - the enquiry and quotation pages open without a PO link, the CRM step has no URL, and there is no PO tile
- the enquiry step link opens `/po/create?quotation=` (a Draft quotation too), or `?enquiry=` with no quotation, and the
  page is filled from it
- linking a Draft quotation keeps Draft, logs `quotation.po_linked`, gives the enquiry PO received and ticks the step; a
  Sent quotation becomes Accepted; an earlier revision is refused
- the customer must match the quotation's or enquiry's customer (message names it); a blank customer is taken from the
  quotation; a prospect's quotation needs a Party customer
- link → move → unlink an enquiry on edit: both enquiries refresh (PO received / Open), and the source follows
- PO list: latest first; search by PO no, customer and enquiry no; Linked / Direct views; date range; wrong dates shown;
  25 a page; CSV as filtered with items count, items total and value; PO register CSV and PDF
- a user with only `customer-po.*`:
  - sees Dashboard and PO only
  - can use the pickers, save, view, list, CSV, register and the dashboard tile
  - is refused CRM pages and linking an enquiry
  - a PO document upload returns to `/po/{id}`

Full suite: `Tests: 389 passed (7687 assertions)`. It was 378 before; the 11 new tests are added and no test was removed.

`artisan view:clear` was run. `curl http://localhost/nursery-app/po` answers 302 to the login page, and
`http://localhost/nursery-app/crm/pos` answers 301 to `/nursery-app/po`.

## To try in a browser

1. The top bar shows **PO** after CRM. Open PO › New PO, pick a customer, add two items: the value fills in and is
   read-only. Remove the items: the typed value comes back. Save.
2. PO › PO list: search by the customer, switch Linked to CRM / Direct PO, set a date range, Download CSV.
3. In CRM, open an enquiry with a **Draft** quotation. The "PO / Agreement" step opens the PO page with the quotation,
   customer and value filled in. Save, and check that the quotation is still Draft and the enquiry is PO received.
4. Do the same with a **Sent** quotation: it becomes Accepted.
5. Edit a PO: change its Enquiry to another one, then clear it. The first enquiry opens again, and the badge changes to
   Direct PO.
6. Pick a quotation of customer A and choose customer B: refused with a message.
7. Admin › Page setup › Optional parts: switch **CRM** off. The CRM menu disappears, PO still works, and the link block
   is gone; a linked PO shows its link as read-only. Switch it on again.
8. Switch **PO / Agreement** off. The PO menu and the dashboard PO tile disappear, the enquiry step has no link, and
   `/po` says switched off.
9. Access rights: the PO / Agreement rights are under **PO**.
10. Old bookmark `/nursery-app/crm/pos` lands on `/nursery-app/po`.

## Open points

- The `crm.quotations.lookup` route still accepts `customer-po.view` (from 29/10). The new form shows the quotation
  picker only to users with `quotation.view`, so this is harmless, but it could be narrowed.
- The quotation status page shows "Enter PO / agreement" only on Accepted quotations (unchanged). The enquiry step now
  links at any step; the button could follow if wanted.
- Linking a PO to a quotation that is not yet sent does not change it to Accepted (as asked). Should a later "Sent" turn
  it Accepted automatically because a PO exists? Not built.
- 29/10 open questions 2 to 7 still stand. Question 1 (Sales menu) is replaced by the own PO menu.

# Tester review — PO module (01/11/2026)

Reviewed the "PO as its own module" change against the requirement (PO works on its own, with CRM switched off and
without CRM data; CRM still links to it; items from the Part master). Originals of the files fixed here are in the
scratchpad `po-module-tester-backup` folder, with their relative paths kept.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| T1 | Medium (security) | **Another unit's quotation / enquiry shown on the PO form.** After a refused save, the posted `quotation_id` / `enquiry_id` comes back as old input, and `quotationOptions()` / `enquiryOptions()` added it as an option with `withTrashed()->find()` and no unit check. Posting any id showed that quotation's number, customer and status (or the enquiry's number, client and status) from a unit the user cannot see. | Fixed: the chosen option is added only from the user's units (`CustomerPoController`). |
| T2 | Medium | **Error page for a tampered PO value or quantity.** `prepareForValidation()` cast `po_value` / `po_quantity` to text; an array (`po_value[]=1`) raised "Array to string conversion", a 500. | Fixed: an array is kept as it is, so the numeric rule refuses it with the usual message. |
| T3 | Low | **PO value with items was accepted unchecked.** With items the rule was only `nullable`, so any text (`abc`, a huge number) was written to `po_value` before the items' sum replaced it. On MariaDB in strict mode that insert fails with an error page. | Fixed: with items the field is `exclude` (not read at all); the value is always the items' sum. |
| T4 | Low | **CRM step opened an empty PO page.** For a user with Enquiry and PO rights but no Quotation view, the "PO / Agreement" step linked to `/po/create?quotation=…`, which the PO page ignores for that user, so nothing was filled in. | Fixed: `CrmFlow` passes `?quotation=` only when the user may link quotations, otherwise `?enquiry=`. |
| — | OK | Switches: with CRM off every `crm.*` GET route and `reports.crm.*` route answers 404 "switched off", for Admin too; every CRM right is refused by the gate (module switch runs before the Admin rule); CRM menu, dashboard section and alerts are hidden. PO keeps working in full: save with documents, view, list, CSV, register CSV / PDF, document upload / download, pickers. Both off: the dashboard still opens. PO off: CRM enquiry / quotation pages open with no PO link. | Tests added |
| — | OK | `/crm/pos…` redirect is built with `url()`, so it always stays on this site (`//evil.example`, `%2F%2F`, `%5C%5C` tried). | Test added |
| — | OK | Link checks: another unit, a deleted enquiry, no View right, link hidden in Page setup — all refused; a Quotation-only user's link brings the quotation's enquiry. Line ids of another PO make new lines; other customers' / purchase items refused; list, CSV, lookup, show / update and register are scoped to the user's units. | Tests added |
| — | OK | Nothing still uses `crm.customer-pos.*`, `Crm\CustomerPoController`, `Requests\Crm\CustomerPoRequest` or `crm/customer-pos` views. Null enquiry is handled in the list, CSV, register, form, snapshot and CrmFlow. | — |

## Questions (not changed)

1. **CRM off and a linked PO's customer**: with CRM off the saved link is kept and still checked, so changing the
   customer of a linked PO is refused ("The customer must be …") and the user cannot unlink it until CRM is on again.
   Is that wanted, or should the customer check be skipped while the link cannot be edited?
2. **One picker only**: a user who can view quotations but not enquiries (or the reverse) cannot clear the other link.
   Clearing the enquiry of a PO that has a quotation silently keeps the quotation's enquiry. Acceptable?
3. **Duplicate PO no** is checked per customer across **all units**. Should two units be allowed the same customer PO
   no? The check is also case-sensitive on SQLite and case-insensitive on MariaDB (collation).
4. **Changing the customer** in the browser leaves item rows of the old customer in place; the server then refuses them
   item by item. Should the page clear (or warn about) those rows when the customer changes?
5. Picking a quotation in the link block does not fill the customer or the item picker in the browser; it is filled on
   save when the customer is blank. Fine as is?
6. The Page setup "Link to CRM" hidden case answers "You cannot link quotations to a PO." — the same text as a missing
   right. A separate message ("The link to CRM is hidden in Page setup.") would be clearer.

## Files changed

- `app/Http/Controllers/Po/CustomerPoController.php` (T1)
- `app/Http/Requests/Po/CustomerPoRequest.php` (T2, T3)
- `app/Support/CrmFlow.php` (T4)
- `tests/Feature/PoModuleReviewTest.php` (new)

## Tests added

`tests/Feature/PoModuleReviewTest.php`, 11 tests:

- CRM off: every CRM GET route and CRM report route is 404 for Admin, every CRM right refused, `lookup.cities` 403, no
  CRM menu; PO save with a document, show, list, CSV, register CSV and PDF, document upload / download, item and
  customer pickers, employee lookup all work
- CRM and PO both off: no CRM / PO menu, dashboard opens with neither section, both say switched off, other rights kept
- `/crm/pos` redirect never leaves the site
- link refused for another unit's quotation / enquiry (and not shown back on the form, T1), a deleted enquiry, a user
  with no CRM view rights; a Quotation-only user links a quotation and gets its enquiry; `?quotation=` of another unit
  is ignored; items lookup for another unit's quotation is empty
- link hidden in Page setup: not shown, `?quotation=` ignored, a posted link refused
- tampered fields (arrays, bad JSON, text value with items) give messages, not errors (T2, T3)
- lines: another PO's line id, another customer's item, a purchase item, a customer change with old items
- unit scoping of show / update / list / CSV / lookup / register; register type, unit and date-range checks
- link → move to another quotation → unlink on edit: both enquiries refreshed, Sent quotation Accepted and stays,
  `quotation.po_unlinked` logged, source follows; duplicate PO no on edit; same no for another customer allowed
- CRM step for a user without Quotation view opens `?enquiry=` and fills the page (T4)
- PO off: enquiry / quotation pages and the PO pending list open without a PO link; a PO document says switched off

## Result

Full suite: `Tests: 400 passed (7907 assertions)` (389 before; 11 added, none removed or changed).
`artisan view:clear` run; `artisan route:list --path=po` lists the 13 `po.*` routes and `crm/pos/{rest?}`;
`migrate:status` has nothing pending. `curl http://localhost/nursery-app/po` → 302 to `/nursery-app/login`;
`/nursery-app/crm/pos` → 301 to `/nursery-app/po`; `/crm/pos/create?quotation=5` → 301 to `/po/create?quotation=5`.

## To check in a browser

1. PO › New PO: pick a customer, add items — value read-only and summed; remove the items — the typed value returns.
2. Change the customer with items already chosen, then save: each old item is refused with a message (question 4).
3. Open a PO, type a quotation id of another unit in the link field with the browser tools and save: the error shows and
   the field does not show that quotation (T1).
4. As a user with Enquiry and PO rights but no Quotation view, the enquiry step "PO / Agreement" opens the PO page
   with the enquiry and customer filled in (T4).
5. Switch CRM off and on, then PO off and on, in Admin › Page setup, and walk through the menus.

# PO in CRM and as its own menu (01/11/2026)

## The request

> "That's what I have asked: in CRM also PO want, and separate menu also want, and separate should work independently."

So the customer PO now has **two entry points on the same POs** (`customer_pos`), and each one works on its own:

- **CRM › 6 PO / Agreement** is back in the CRM menu, in its old place after 5 Quotation sent. It is the CRM step: the
  PO is linked to an enquiry and / or a quotation. It needs only the **CRM** switch, so the CRM process can reach
  "PO received" even when the PO module is off.
- The **PO menu** (`/po`) stays as built. It needs only the **PO** switch, works with CRM off, and lists every PO,
  direct or linked.

## What changed for the user

- **CRM › 6 PO / Agreement** (`/crm/pos`, no longer a 301 redirect to `/po`):
  - The breadcrumb is CRM › PO / Agreement.
  - The block is called **Enquiry / quotation\***. An enquiry, a quotation or both is required. Any enquiry status and
    any quotation status can be linked (current revision only), as in the PO menu. The customer comes from it; a
    prospect's quotation still needs a Party customer chosen.
  - The pickers need only the PO / Agreement rights (CRM is on there). The Page setup choice "Link to CRM" applies to
    the PO menu only.
  - Saving with no link is refused: "Choose the enquiry or quotation this PO is for."
  - Editing cannot remove the link: "A PO in CRM must stay linked to an enquiry or quotation. To unlink it, use the PO
    menu." Moving the link to another enquiry / quotation is allowed.
  - Saved PO pages have **Open in PO menu** while the PO module is on.
  - **PO list** (`/crm/pos/list`) shows only POs linked to CRM, with a "Linked to CRM" badge and no Type filter. It
    keeps search, sort, the date range and **Download CSV**. The entry selector and its search also show only linked
    POs.
- The enquiry workflow step **PO / Agreement** and the quotation's **Enter PO / agreement** button open
  `/crm/pos/create?quotation=…` (or `?enquiry=…` when there is no quotation). A saved PO opens `/crm/pos/{id}`.
- **PO menu** (`/po`): no change. The link to CRM is optional and shown only while CRM is on and the user can view
  enquiries / quotations. The list shows all POs with the Type filter. The PO register stays here.
- **Saves stay where they came from**:
  - Save, update and delete from CRM return to `/crm/pos/…`, and from the PO menu to `/po/…`.
  - A CRM-linked PO opens in both places.
  - A **direct PO is not a CRM record**. `/crm/pos/{id}` sends it to `/po/{id}` while PO is on, and answers 404 while
    PO is off. Changing or deleting it through `/crm/pos/{id}` answers 404.
- **Dashboard**: the PO / Agreements tile shows while either module is on.
  - With PO on, it counts every PO and opens the PO list.
  - With only CRM on, it counts the linked POs and opens CRM › PO / Agreement › PO list.
  - "PO pending" stays a CRM alert.
- **Documents** (Customer PO / Agreement) can be uploaded and downloaded from both pages with either switch on. A
  direct PO's documents say "switched off" while PO is off.
- **Access rights**: there is still one **PO** group, with the same `customer-po.*` rights. The row now says "Used by
  CRM › PO / Agreement and the PO menu". It is shown while either module is on.
- **Page setup**: the "PO / Agreement received" page is shown while either module is on. The CRM / PO switch texts now
  describe the two entry points.

## Switch matrix

| CRM | PO | CRM menu | PO menu | `/crm/pos` | `/po` | CRM step / Enter PO | Dashboard tile | `customer-po.*` rights |
|---|---|---|---|---|---|---|---|---|
| On | On | 6 PO / Agreement | New PO, PO / Agreement, PO list, PO register | Linked POs only, link required | All POs, link optional | `/crm/pos/create?…` | All POs → PO list | Kept |
| On | Off | 6 PO / Agreement | Hidden (register too) | Works in full | 404 "switched off" | `/crm/pos/create?…` | Linked POs → CRM PO list | Kept |
| Off | On | Hidden | Shown | 404 "switched off" | Direct POs; no linking; a saved link is kept | — (CRM off) | All POs → PO list | Kept |
| Off | Off | Hidden | Hidden | 404 | 404 | — | None (dashboard opens) | Refused by the gate |

## Decisions

| Topic | Decision |
|---|---|
| One controller | `Po\CustomerPoController` serves both route groups. A new `App\Support\PoContext` reads the context from the route name (`crm.customer-pos.*` = CRM, otherwise PO) and builds the routes (`$ctx->route('show', $po)`). There is one form view and one list view, each with a `$ctx` flag, plus one request and one service. |
| Rights gate | `customer-po` belongs to `po|crm`. In `Modules`, a key with `|` means "either": `on('po|crm')` is true while either is on. `offOf()` gives `po` when both are off. The existing `Gate::before` module check needed no change. Each route group keeps its own switch: `module:crm` on `/crm/pos`, `module:po` on `/po`. |
| Menu | Menu items now also respect their own `module` key, because the rights are no longer refused for one switch alone. The CRM item `6 PO / Agreement` has `module => crm`. The three PO items have `module => po`. A new `report_modules` (`PO => po`) hides the PO register (menu, Reports page, preview route) while PO is off. |
| Linking on the CRM page | It needs only the PO / Agreement rights, because the page is the CRM step (as before 01/11). `crm.enquiries.lookup` now also accepts `customer-po.view`, as `crm.quotations.lookup` already did. In the PO menu, linking still needs Enquiry / Quotation view and the Page setup "Link to CRM". |
| Link required in CRM | It is checked in `CustomerPoRequest::checkLink()` (error key `crm_link`). On edit, a link that is not posted keeps the saved one, as in the PO menu. A posted "Direct PO" type (`po_type=direct`) clears the link, so it is refused. |
| CRM list | `listQuery($request, crmOnly: true)` filters on `source = crm`, and the Type filter is ignored. The entry selector and lookup use the same filter. |
| Direct PO in CRM | `show` redirects to `po.show` while PO is on and answers 404 otherwise. `CustomerPoRequest::authorize()` and the controller answer 404 for update / delete, so validation messages are never shown for it. |
| Dashboard link | The tile opens a list (PO list, or CRM › PO list), as before, rather than the entry page. |
| CRM step | It always passes `?quotation=` when there is a quotation, because the CRM page always links. The tester's T4 case (no Quotation view) now gets a page filled from the quotation. |
| Database | No migration and no data change. `source` already tells linked from direct. |

## Behaviour changes (existing tests updated, none deleted)

| Test | Before | Now |
|---|---|---|
| `PoModuleTest::test_po_is_its_own_top_menu_after_crm_and_crm_no_longer_lists_it` → `…_and_crm_keeps_its_po_step` | CRM menu had no PO item | `6 PO / Agreement` right after 5 Quotation sent and is the only PO item in CRM. PO menu and register assertions are unchanged. |
| `PoModuleTest::test_old_crm_addresses_redirect_permanently_to_po` → `test_crm_po_addresses_are_crm_pages_again_and_po_has_its_own` | `/crm/pos…` answered 301 to `/po…` | Both need a sign-in. `/crm/pos` opens the CRM page (CRM context, CRM list link). |
| `PoModuleTest::test_po_module_off_…_crm_step_has_no_link` → `…_crm_step_opens_crm` | PO off: rights refused, no PO group, step with no link, no tile | PO off: `/po` still 404 and PO menu hidden; rights and PO group kept; the step opens `crm.customer-pos.create?quotation=`; the tile opens the CRM PO list |
| `PoModuleTest::test_enquiry_step_opens_the_po_page_filled_from_the_quotation` | Step opened `po.create` | Step opens `crm.customer-pos.create` (same fill-in checks) |
| `PoModuleTest::test_a_user_with_only_po_rights_uses_the_whole_po_module` | Top menus: Dashboard, PO | Dashboard, CRM (only `6 PO / Agreement`), PO. The rest is unchanged. |
| `PoModuleReviewTest::test_old_address_redirect_stays_on_this_site` → `test_crm_po_addresses_never_leave_this_site` | 301 stayed on this host | No 301; any redirect (sign-in) stays on this host |
| `PoModuleReviewTest::test_crm_step_opens_the_po_with_the_enquiry_for_a_user_who_cannot_view_quotations` | `po.create?enquiry=` | `crm.customer-pos.create?quotation=`, filled with quotation, enquiry and customer (T4's aim kept: never an empty page) |
| `PoModuleReviewTest::test_po_off_crm_pages_show_no_po_link…` → `…_link_to_crm_po…` | PO off: no PO link, PO documents "switched off" | PO off: links go to `/crm/pos/{id}`; a linked PO's document upload / download works; a direct PO's document still says "switched off" |
| `PoModuleReviewTest::test_crm_off_refuses_every_crm_page_route…` | Skipped the `crm.customer-pos.moved` route | The route is gone. The new `crm.customer-pos.*` GET routes are now in the loop and answer 404 with CRM off. |
| `UiRound2Test::test_crm_menu_follows_the_enquiry_steps…` | Asserted no `>6 PO / Agreement<` | `>6 PO / Agreement<` is back in the step order, between 5 Quotation sent and Prospect |

## Files

Originals are in the scratchpad `po-dual-backup` folder, with their relative paths kept.

### New

- `app/Support/PoContext.php`: the CRM / PO context, read from the route; route names and URLs per context.
- `tests/Feature/PoDualEntryTest.php`: 10 tests (see below).

### Changed

- `routes/web.php`
  - Removed the `crm/pos/{rest?}` 301 redirect.
  - Added `crm.customer-pos.*` in the `module:crm` group: index, list, export, lookup, parties, items, create, store,
    show, update and destroy, on the same controller and rights.
  - `crm.enquiries.lookup` also accepts `customer-po.view`.
- `config/nursery.php`
  - `pages.PO.customer-po`: `module => 'po|crm'`, plus a `note` for Access rights.
  - `nav`: CRM `6 PO / Agreement` (`module => crm`); PO items `module => po`.
  - New `report_modules`.
  - `page_fields.customer-po.module => 'po|crm'`.
  - CRM / PO switch texts.
- `app/Support/Modules.php`: `on()` / `offOf()` understand "either" keys (`po|crm`).
- `app/Support/Menu.php`: a menu item is hidden while its own module is off, and a report group while its module is
  off.
- `app/Http/Controllers/AppPageController.php`: report preview pages follow `Menu::reportGroups()`, so the module is
  checked too.
- `app/Http/Controllers/Po/CustomerPoController.php`
  - Context-aware redirects and the lookup URL.
  - In CRM: only linked POs in the search, list and CSV (`listQuery(..., $crmOnly)`).
  - A direct PO opened from CRM goes to the PO menu or 404; it cannot be changed or deleted there.
  - Passes `ctx` to the views.
- `app/Http/Requests/Po/CustomerPoRequest.php`: `canLink($field, $user, $crm)`; on the CRM page the link is readable on
  PO rights, required, and cannot be removed (`crm_link` error); 404 for a direct PO saved through CRM.
- `app/Support/CrmFlow.php`: the PO step links to `crm.customer-pos.show` / `create`.
- `resources/views/crm/quotations/status.blade.php`: "Enter PO / agreement" → `crm.customer-pos.create`.
- `app/Http/Controllers/Crm/DocumentController.php`: `customer_po` belongs to `po|crm`; a direct PO's documents are
  refused while PO is off.
- `app/Services/CrmCounts.php`: `poTiles()` follows the switches (count and link).
- `resources/views/po/form.blade.php`
  - Context routes and breadcrumb.
  - "Enquiry / quotation\*" block in CRM, with the `crm_link` error.
  - "Open in PO menu".
- `resources/views/po/list.blade.php`: context routes, breadcrumb and badge; no Type tabs in CRM.
- `resources/views/partials/matrix.blade.php`: shows a page's `note` under its name on Access rights.
- Tests updated as listed above: `PoModuleTest`, `PoModuleReviewTest`, `UiRound2Test`.

### Checked, no change needed

- `app/Services/CustomerPoService.php`: same save and link logic for both pages.
- `app/Support/Access.php`: `moduleOf()` already returns the first part that is off, and `po|crm` works through
  `Modules`.
- `PageSetupController`: it uses `offOf()`, so the page shows while either module is on.
- `CrmAlerts`: unchanged.
- `PoRegisterController`: unchanged.
- `public/js/customer-po.js`: it reads URLs from the page.

## Database

No migration and no data change: the switches live in `module_settings`, and `source` already marks linked POs, so
the dev MySQL was not backed up again. `artisan migrate:status` shows nothing pending.

## Tests

`tests/Feature/PoDualEntryTest.php`, 10 tests:

- **Both on**: CRM has `6 PO / Agreement` and the PO menu has its three pages. The CRM page shows "Enquiry /
  quotation", and the PO page shows "Link to CRM (optional)". The register is in the PO menu, and Access rights shows
  the note.
- **CRM on, PO off**:
  - No PO menu, and `/po`, create, list and register answer 404.
  - The flow step opens the CRM page filled from the quotation.
  - Saving with a document stays in CRM, gives the enquiry PO received and the quotation Accepted, and ticks the step,
    which then links to `crm.customer-pos.show`.
  - View, edit, list, CSV, lookup, document upload and download work.
  - The quotation's Enter PO goes to `/crm/pos/create`.
- **CRM off, PO on**: no CRM menu; every `/crm/pos…` address and the store answer 404 "switched off"; a direct PO is
  saved, viewed and listed in `/po`.
- **Link rules on the CRM page**:
  - No link, or `po_type=direct`, is refused, and an enquiry alone is enough.
  - Clearing the link on edit is refused; moving it is allowed.
  - "Open in PO menu" is shown, and unlinking from the PO menu works (direct, enquiry back to Open).
- **Lists**:
  - The CRM list, CSV, lookup and entry selector show only linked POs, and `?view=direct` is ignored there.
  - The PO list shows both and filters by type.
  - A linked PO opens in `/po` too.
- **Saves in context**: store, update, refused save and delete return to the page they came from, for the same PO from
  either side.
- **Direct PO via CRM**: show redirects to `po.show` and index `?id=` stays in CRM; update and delete answer 404 and
  nothing changes; with PO off, show answers 404.
- **Dashboard tile**: both on 2 → PO list; PO off 1 linked → CRM PO list; CRM off 2 → PO list; both off: none.
- **Rights and Page setup** for all four switch states: `customer-po.view`, the Access rights PO group, and the Page
  setup page (404 only with both off).
- **Rights-only user** (only `customer-po.*`), PO off:
  - CRM menu shows only `6 PO / Agreement`, and there is no PO menu.
  - The pickers show, `?quotation=` fills the page, and the enquiry / quotation / items / parties lookups open.
  - Save, view and list work; the other CRM pages are 403.
  - With PO on, linking from the PO menu is still refused.

Full suite: `Tests: 410 passed (8114 assertions)`. It was 400 before; the 10 new tests are added, and no test was
removed.

`artisan view:clear` was run. `artisan route:list` lists 11 `crm.customer-pos.*` and 13 `po.*` routes.
`curl http://localhost/nursery-app/crm/pos`, `/po` and `/crm/pos/create?quotation=5` each answer 302 to
`/nursery-app/login` (no 301 any more).

## To try in a browser

1. With both switches on:
   - CRM › **6 PO / Agreement** is back after 5 Quotation sent, and the PO menu is still there.
   - Open an enquiry with a quotation, click the workflow step **PO / Agreement**, and save. The page stays on
     `/crm/pos/…`, and the enquiry is PO received.
2. On that CRM page:
   - Clear both Enquiry and Quotation and save: refused.
   - Click **Open in PO menu**, clear the link there and save: it becomes a Direct PO.
3. CRM › PO / Agreement › **PO list** shows only linked POs. PO › PO list shows all, with the Type tabs.
4. Admin › Page setup › Optional parts:
   - Switch **PO / Agreement** off. The PO menu and register disappear, and CRM › 6 PO / Agreement still works end to
     end, with documents.
   - The dashboard tile now opens the CRM PO list.
5. Switch PO on and **CRM** off. The CRM menu disappears, and `/crm/pos` says "switched off". PO › New PO saves a
   direct PO.
6. Type `/crm/pos/<id of a direct PO>`: it opens the same PO under `/po/<id>`.
7. Give a role only the PO / Agreement rights. With CRM on, it sees CRM › 6 PO / Agreement only, and can pick an
   enquiry or quotation there.

## Open questions

1. **Linking on PO rights alone in CRM**: a user with only the PO rights can see the enquiry and quotation numbers and
   customers of their unit in the CRM page's pickers. This is how CRM PO worked before 01/11, but it is wider than the
   PO menu, which needs Enquiry / Quotation view. Keep it?
2. **Unlinking only from the PO menu**: with the PO module off, a CRM PO can never be unlinked. That seems right,
   because it is the CRM step, but please confirm.
3. **Dashboard tile with both on**: it opens the PO list (all POs). Should CRM users rather land on the CRM list?
4. The CRM page has no **PO register**. With PO off there is no register at all. Should CRM get a "PO register (linked
   POs)" under CRM reports?
5. Old **bookmarks** to `/crm/pos/{id}` of a direct PO now redirect to `/po/{id}`, and answer 404 while PO is off.
   Fine?
6. The tester questions of the PO-module review (1 to 6) still stand.

# Tester review — PO in CRM and own menu (01/11/2026)

Reviewed "PO in CRM and as its own menu" against the request (PO in CRM, its own PO menu, each working on its own).
Originals of the file fixed here are in the scratchpad `po-dual-tester-backup` folder, with their relative paths kept.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| D1 | Low | **CRM page lost its link checks while "Link to CRM" is hidden in Page setup.** That choice is meant for the PO menu only, but `PageSetupService::rules()` still dropped the `enquiry_id` / `quotation_id` rules on the CRM page. A tampered `enquiry_id=12abc` was read as 12 and saved, and an array got no "Choose … from the list." message. The unit, deleted and revision checks still ran, so nothing outside the user's units could be linked. | Fixed: on the CRM page the link keeps its rules whatever Page setup says (`CustomerPoRequest::rulesForFields()`). |
| D2 | Low (security) | **Another unit's PO was validated before the unit check.** `update` on `/po/{id}` or `/crm/pos/{id}` for a PO of another unit sent back validation messages (for example "Choose a quotation of your units.") rather than 403. From CRM, a direct PO of another unit answered 404, which told the user it was direct. Nothing could be changed (the controller still refused it). | Fixed: `CustomerPoRequest::authorize()` refuses another unit's PO first (403), before the direct-PO 404 and before validation. |
| — | OK | **Switch matrix** (all four states, as Admin and as a Sales user): every `crm.customer-pos.*` GET route answers 200 only with CRM on and 404 "switched off" otherwise; every `po.*` route, including the register page and its CSV / PDF, follows the PO switch only; store / update / delete in each page follow that page's switch. CRM menu `6 PO / Agreement` shows only with CRM on; the PO menu and the PO register (menu and preview page) only with PO on. Dashboard tile: PO on 2 → PO list, CRM only 1 linked → CRM PO list, both off none. CRM step and quotation "Enter PO / agreement" open `/crm/pos…`. Documents: a linked PO's upload / download with either switch on, a direct PO's upload / download / delete only with PO on, none with both off. `customer-po.view` (Admin too, because the switch check runs before the Admin rule), the Access rights note and the Page setup page follow "either on". | Tests added |
| — | OK | **Context**: it is read only from the route name. Hidden fields or query values (`ctx`, `context`, `_route`, `source`, `po_type=crm`) do not lift the CRM link rule or change where saves return. A direct PO cannot be linked, changed or deleted through `/crm/pos` (404). The CRM list, CSV, lookup and entry search ignore `view` / `source`. A `?ctx=crm` on the PO menu changes nothing. | Tests added |
| — | OK | **Link checks on the CRM page for a PO-rights-only user**: another unit's quotation / enquiry is refused and not shown back on the form. `?quotation=` / `?enquiry=` of another unit is not filled in, and its items lookup is empty. A deleted enquiry or quotation, an earlier revision, a quotation of another enquiry and another customer are all refused. Moving the link to another unit's enquiry is refused; moving it to the current revision is allowed, and the enquiry statuses follow. Another customer's or a purchase item is refused. The enquiry / quotation lookups give only this unit's records, with `id`, `label`, `sub` and `url` only. | Tests added |
| — | OK | **Unit scoping of the CRM page** with PO on and off: show, update and delete of another unit's PO are 403, and its PO is not in the list, CSV or lookup. | Tests added |
| — | OK | **CRM workflow end to end** with PO on and off: sent quotation → step opens `/crm/pos/create?quotation=` → save stays in CRM → enquiry PO received, quotation Accepted, step ticked and linked to `/crm/pos/{id}` → "Open in PO menu" only with PO on → delete from CRM opens the enquiry again. | Tests added |
| — | Note | Breadcrumbs (`crumbs`) passed by the CRM views are not shown any more (the menu bar replaced them), so the "CRM" crumb linking to the enquiry list is harmless for a PO-only user. | — |

## Questions (not changed)

1. **"PO pending" alert for a PO-rights-only user**: `CrmAlerts` shows it to anyone with `customer-po.view` while CRM
   is on, but it opens `crm.quotations.list`, which needs Quotation view, so such a user gets 403. Before this change
   was made, such a user had no CRM menu. Should the alert need `quotation.view` too, or open CRM › PO / Agreement instead?
2. **Enquiry lookup on PO rights**: `crm.enquiries.lookup` now answers PO-rights-only users, and it also searches by
   contact phone number. Within their unit they can find an enquiry number and client name from a phone number. This
   is part of open question 1 of the build (linking on PO rights alone); please confirm.
3. **"Enter PO / agreement" on the quotation page** still shows on an Accepted quotation that already has a PO (as
   before). Should it hide, or open the saved PO, when one exists?
4. **CRM list Type column**: the CRM PO list keeps the Type column, which always says "Linked to CRM" there. Keep it or
   drop it on the CRM page?

## Files changed

- `app/Http/Requests/Po/CustomerPoRequest.php` (D1, D2)
- `tests/Feature/PoDualEntryReviewTest.php` (new)

## Tests added

`tests/Feature/PoDualEntryReviewTest.php`, 6 tests:

- the switch matrix in all four states, as Admin and a Sales user: every `crm.customer-pos.*` and `po.*` route
  (register CSV / PDF included), a direct PO via CRM, menus, the register in the menu and its preview page, the
  dashboard tile, the CRM step, the quotation button, documents (upload / download / delete, linked and direct), the
  Admin right, Access rights note, Page setup, and store / update / delete in each page
- context only from the route: forged hidden fields and query values, a direct PO via CRM, the CRM list / CSV / lookup /
  entry search, a `?ctx=crm` on the PO menu, and `PoContext::current()` with a misleading query
- the CRM page's link checks for a PO-rights-only user (other unit, deleted, earlier revision, other enquiry, other
  customer, moving the link, items), and the minimal lookups
- unit scoping of the CRM page with PO on and off; another unit's PO refused before validation (D2)
- "Link to CRM" hidden in Page setup: the CRM page still shows, needs and checks the link (D1); the PO menu hides it
- the CRM workflow end to end with the PO module on and off

## Result

Full suite: `Tests: 416 passed (8727 assertions)` (410 before; 6 added, none removed or changed).
`artisan view:clear` run. `artisan route:list --path=crm/pos` lists the 11 `crm.customer-pos.*` routes, and
`--name=po.` lists the 13 `po.*` routes. `migrate:status` has nothing pending. `curl http://localhost/nursery-app/crm/pos`
and `/nursery-app/po` each answer 302 to `/nursery-app/login`. `bootstrap/cache/config.php` does not exist; tests ran on
SQLite `:memory:`.

## To check in a browser

1. As a role with only the PO / Agreement rights, CRM on: open the dashboard. Check the "PO pending" alert (question 1),
   then CRM › 6 PO / Agreement, the pickers' search, and saving.
2. Admin › Page setup › PO / Agreement received: hide "Link to CRM". The PO menu has no link block, and CRM ›
   6 PO / Agreement still has "Enquiry / quotation*" and needs it (D1).
3. Walk the four switch states in Admin › Page setup › Optional parts and look at the menu bar, the dashboard PO tile
   and a PO's Documents box in each.

# Separate PO switch and CRM PO as before (01/11/2026)

## The request

> "From the super admin: if separate PO option is off, then PO will work as it was working previously from the CRM
> option. If separate PO is on, then also from the CRM it should work as it did previously. And a new dropdown menu will
> be visible, PO, and this PO should work independently apart from CRM, and the user will select whatever CRM options
> are there if required, and that should be optional for the new PO menu / new PO generation."

## What changed for the user

- **Admin › Page setup › Optional parts**:
  - The `po` switch is now called **Separate PO menu**: "Adds a PO menu where POs can be entered without CRM (the link
    to an enquiry or quotation is optional there), with the PO list and the PO register. CRM › PO / Agreement works
    either way."
  - It is **off by default**: no saved row means off.
  - Only users with Page setup · Edit can change it. By default that is only the **Admin** role, which has every right;
    Sales, Purchase and Accounts do not have it.
- **CRM › 6 PO / Agreement** works **as before 29/10/2026**, whether the switch is on or off:
  - The **Quotation is mandatory**. There is no Customer field and no Enquiry field: the client and the enquiry come from
    the quotation, and so does the unit. A prospect's quotation gives a PO for the prospect, as before.
  - Only a quotation **made from an enquiry**, in its **current revision**, **sent to the customer** and **not Rejected
    or Expired** can take a PO. The picker lists only those. `?quotation=` fills the form only for such a quotation.
  - The old messages are used:
    - "Choose the quotation this PO is for."
    - "Choose a quotation made from an enquiry of your units."
    - "Q… is Draft. A PO can be entered only against the current revision of a quotation sent to the customer."
    - "Q… is Rejected. Set its customer status to Accepted, or Revise it, before entering a PO."
  - The quotation becomes **Accepted**, and the enquiry becomes **PO received**.
  - The **PO / agreement no is unique per enquiry** (no per-customer check on the CRM page).
  - The PO value is typed.
  - Kept on top, as the user asked: optional **items from the Item master** (Part master drop-down: the quotation
    customer's sales items, or any sales item for a prospect's quotation) and the **documents** chosen with a new PO.
  - On edit, a changed quotation is checked the same way. The saved quotation can stay as it is, but it cannot be
    cleared.
- **CRM workflow step "PO / Agreement"** links to `/crm/pos/create?quotation=` only when the current quotation can take
  a PO (as before). Once a PO exists, it opens that PO. The quotation page's "Enter PO / agreement" is unchanged (Accepted
  quotations).
- **Dashboard**:
  - With the switch off, the PO / Agreements tile is a **CRM tile again**, opening CRM › PO / Agreement (as before
    29/10).
  - With the switch on, the tile is in the PO section and opens the PO list.
- **PO menu** (switch on): unchanged. It is independent of CRM, the enquiry / quotation link is optional and accepts any
  status, and it has the PO list (all POs) and the PO register.
- **PO pending alert** (tester question 1 of the PO in CRM review): it now needs Quotation view too, as the quotation
  list it opens. A user with only PO rights no longer sees an alert that leads to a 403.
- The `crm.enquiries.lookup` route no longer accepts `customer-po.view` (the CRM page has no Enquiry picker any more).
  This answers tester question 2 of that review.

## Items on a CRM PO: value

With items, the CRM PO value is **the sum of the items** (current behaviour, kept). The old CRM tests (29/10 versions,
restored unchanged) post no items, so they pass either way. Without items, the value is typed and mandatory, as before.

## Dev database

- `module_settings` in the dev MySQL `nursery` has **no rows at all**, so there is no `po` row. With the new default,
  the Separate PO menu is therefore **off** on the dev site until an Admin switches it on.
- CRM, site visit, BOQ and costing have no row either, so they stay on (their defaults).
- Nothing was inserted or changed in the dev database, and there is no migration.

## Decisions

| Topic | Decision |
|---|---|
| Where the CRM rules live | One request and one service, as before. `CustomerPoRequest` (CRM context) has its own rules: quotation required with the old `exists` rule (made from an enquiry, user's units, not deleted), no `party_id` / `enquiry_id`, and `checkStepQuotation()` using `CustomerPoService::whyNoPo()`. `CustomerPoService::create/update(..., $crmStep)` takes the client, enquiry and unit from the quotation, checks it again under lock (`stepQuotation()`, the old `lockQuotation()` messages) and checks the number per enquiry only. |
| `canTakePo()` | `canTakePo($q, $fromEnquiry = false)`. The CRM page, the CRM step and the CRM picker use `true` (needs an enquiry, as before). The PO menu still uses it only to decide whether a linked quotation becomes Accepted. |
| Form | CRM page: Quotation\* picker (with the hint "The client and enquiry come from the quotation…"), no Customer, no Enquiry, no "Link to CRM" block. The item pickers follow the quotation (`customer-po.js` reads `#quotation_id[data-items-url]` when there is no Customer field). The PO menu form is unchanged. |
| Tests needing the PO menu | New `tests/SeparatePo.php` trait: `separatePo()` writes the `po` row and clears the cache. It is used in `setUp()` of the PO-menu test classes (`PoModuleTest`, `PoModuleReviewTest`, `PoDualEntryTest`, `PoDualEntryReviewTest`, `CustomerPoDirectTest`, `CustomerPoDirectReviewTest`). |
| Old tests | The CRM test files changed on 01/11 were put back to their 29/10 versions, which were backed up in `po-module-backup`. That is the latest state with the CRM PO rules; no pre-29/10 test backup exists. `direct-po-backup` has no tests folder. |

## Tests

### Restored to the 29/10 versions (assertions unchanged)

- Unchanged from 29/10: `CrmChangesTest`, `CrmReviewFixesTest` (M2: Draft / pending / earlier revision refused, number
  per enquiry), `CrmFullTestFixesTest` (B2: Rejected / Expired refused), `CustomerPoUploadTest`, `CrmNoDeleteButtonTest`,
  `CrmFullFlowFiveEntriesTest`, `DashboardCrmCountsTest` (PO tile in the CRM section, opening CRM › PO / Agreement), and
  `FixRound1Test`. They post to `/crm/pos` with only `quotation_id`, as before.
- Only what the new switches force was changed:
  - `tests/CrmFiveEntryFlow.php`, `PageSetupAndModulesTest` and `PageSetupReviewFixesTest`: the `setModules()` helpers
    also send `crm` (on) and `po` (off), because the switch form must send every part.
  - `PageSetupReviewFixesTest`: the toast text now names "Separate PO menu" and "CRM", and the cached settings include
    `crm` / `po`.
  - `MenuBarTest`: the Reports page count leaves out the PO register, which belongs to the Separate PO menu (off).
    Seven menus again, as on 29/10.

### Updated for the restored rules or the default-off switch

- **`PoDualEntryTest`**:
  - The CRM page shows Quotation\* and no Customer / Enquiry.
  - `test_crm_page_requires_a_link_…` became `test_crm_page_takes_a_po_only_against_a_sent_quotation_as_before`:
    - Refused: no quotation, enquiry only, `po_type=direct`, and a Draft (old message, not offered or filled).
    - A sent quotation saves, with the client and enquiry from it (a posted customer is ignored) and the value typed.
      The quotation becomes Accepted and the enquiry PO received.
    - The number is unique per enquiry, and the same number on another enquiry is allowed.
    - Items give the sum.
    - The quotation cannot be cleared on edit; unlinking in the PO menu still works.
  - The saves-in-context test uses a sent quotation.
  - Dashboard tile: with the PO menu off, it is the CRM tile → `crm.customer-pos.index`.
  - PO-rights-only user: quotation picker only, the enquiry lookup is 403, items by quotation.
- **New in `PoDualEntryTest`**: `test_separate_po_switch_is_off_by_default_and_crm_po_works_without_it`.
  - With no `po` row, the switch is off, with label "Separate PO menu" and its hint.
  - There is no PO menu, and `/po` says "switched off".
  - The CRM step links and a pre-29/10 payload (quotation only) saves, and the enquiry becomes PO received.
  - There is no "Open in PO menu", and the PO tile is in the CRM section.
  - A Sales user cannot change the switch (403, no `page-setup.edit`).
- **`PoDualEntryReviewTest`** (tester's):
  - Switch matrix: the CRM store uses the quotation, and the dashboard tile follows the CRM-tile rule when the PO menu is
    off.
  - Forged context: the error is now `quotation_id`.
  - `…link_checks_for_a_user_with_po_rights_only` became `…quotation_checks_…`: another unit's or deleted quotation
    ("made from an enquiry of your units"), enquiry only, Draft and earlier revision (old messages), a posted customer
    or enquiry ignored, moving to another unit's quotation refused and to another sent one allowed, items refused.
  - Hidden "Link to CRM": the quotation is still required and checked.
- **`PoModuleTest`**:
  - PO menu off: the PO tile is in the CRM section.
  - The enquiry step has no link for a Draft quotation or no quotation, and opens the CRM page once the quotation is
    sent; the PO menu still takes the Draft.
- **`PoModuleReviewTest`**: the CRM step for a user without Quotation view uses a sent quotation.

### Result

Full suite: `Tests: 417 passed (8746 assertions)` (416 before, plus 1 new; none removed). One unrelated flaky failure was
seen once: `PurchaseReportsReviewTest` creates users with `'98225'.random_int(10000, 99999)` as the mobile number, and
two can collide. It passed 3 of 3 times on its own; the code was not touched.

`artisan view:clear` was run. `curl http://localhost/nursery-app/crm/pos`, `/po` and `/crm/pos/create?quotation=5`
each answer 302 to `/nursery-app/login`. `migrate:status` has nothing pending. `bootstrap/cache/config.php` does not
exist.

## Files

Originals are in the scratchpad `po-dual-v2-backup` folder.

- `config/nursery.php`: `modules.po` label, hint, off text, `default => false`.
- `app/Services/CustomerPoService.php`: `$crmStep` on create / update; `stepQuotation()`, `whyNoPo()`, `canTakePo($q,
  $fromEnquiry)`; the number per enquiry on the CRM page.
- `app/Http/Requests/Po/CustomerPoRequest.php`: CRM rules (quotation required, old messages, `checkStepQuotation()`),
  no customer / enquiry read there, items by the quotation's customer. The tester's D1 / D2 fixes are kept.
- `app/Http/Controllers/Po/CustomerPoController.php`:
  - Passes `$crmStep`.
  - CRM quotation options (sent, not Rejected / Expired, made from an enquiry); `?quotation=` only when it can take a PO.
  - No `?enquiry=`, Enquiry picker or Customer list on the CRM page; items by quotation.
- `resources/views/po/form.blade.php`: CRM page Quotation\* block instead of the Customer field and link block.
- `public/js/customer-po.js`: the items follow `#quotation_id` when there is no Customer field.
- `app/Support/CrmFlow.php`: the step links only when the quotation can take a PO.
- `app/Services/CrmCounts.php`: with the PO menu off, the PO tile is a CRM tile (`crm.customer-pos.index`); `poTiles()`
  only while it is on.
- `app/Services/CrmAlerts.php`: PO pending needs Quotation view too.
- `routes/web.php`: `crm.enquiries.lookup` back to enquiry / quotation / site-visit view.
- `tests/SeparatePo.php` (new); the tests listed above.

## To try in a browser

1. Admin › Page setup › Optional parts: **Separate PO menu** is unticked (dev DB has no row). There is no PO menu.
2. CRM › 6 PO / Agreement:
   - The Quotation picker lists only sent quotations.
   - Pick one: the client shows on save, and the quotation becomes Accepted and the enquiry PO received.
   - Try `/crm/pos/create?quotation=<a Draft>`: nothing is filled, and saving it is refused with the old message.
3. Add two items: the value becomes their sum.
4. Tick **Separate PO menu** and save. The PO menu appears:
   - New PO with only a customer and value saves (direct).
   - Linking a Draft quotation there is allowed (optional link).
   - CRM › 6 PO / Agreement still behaves as in step 2.
5. Untick it again: the PO menu disappears, and the dashboard tile returns to the CRM section.

## Open questions

1. A PO entered in the PO menu with **only an enquiry** (no quotation) opens in CRM › 6 PO / Agreement, but saving it
   there asks for a quotation. Fine, or should such POs be read-only in CRM?
2. A PO entered in the PO menu against a **Draft** quotation can be opened and saved in CRM unchanged, because a saved
   quotation is not checked again, as before. Fine?
3. With the Separate PO menu on, the CRM page still has **"Open in PO menu"** to unlink. Keep it?

# Final tester review — Separate PO switch (02/11/2026)

Reviewed "Separate PO switch and CRM PO as before" against the request: with the switch off, POs work from CRM as
before; with it on, CRM still works as before and a PO menu that is independent of CRM is added, where the enquiry /
quotation link is optional. The CRM page was compared with the pre-29/10 originals (`direct-po-backup`: service, request,
form). Originals of the files fixed here are in the scratchpad `po-final-tester-backup` folder, with their relative
paths kept.

## Findings

| # | Severity | Finding | Status |
|---|---|---|---|
| F1 | Low (wording) | **The CRM switch's "When off" text still promised a PO menu.** It said "POs still work in the PO menu…", but the PO menu is now off by default. An Admin switching CRM off was told POs keep working when they would not. | Fixed: "POs can still be entered only while the Separate PO menu is on, without the link to an enquiry or quotation." |
| F2 | Low (wording) | **With CRM and the PO menu both off, the wrong part was named.** The PO / Agreement rights, page and documents use `po|crm`, and the first part is named when both are off. So a PO document link said "Separate PO menu is switched off", and Page setup said "Switched off with Separate PO menu". POs are entered in CRM by default. | Fixed: the order is now `crm|po` (Access rights, Page setup and documents), so CRM is named. Either one being on still keeps the rights. |
| — | OK | **Off by default**: there is no `po` row, so it is off. There is no PO menu and no PO register in Reports, for Admin too. `/po`, the PO list, the register and its download answer 404 "Separate PO menu is switched off". The dashboard PO tile is in the CRM section and opens CRM › PO / Agreement. A Sales user gets 403 trying to switch it, and nothing is saved. Switched on by an Admin, the PO menu, the register and the tile (→ PO list) appear, the CRM tile goes, and "Open in PO menu" shows. Switched off again, everything goes back. | Tests added |
| — | OK | **CRM page vs pre-29/10**: the messages match: required, "made from an enquiry of your units", Draft, Rejected, earlier revision, "Add at least one item, or enter the PO value.". The PO no is unique per enquiry, so the same no on another enquiry of the same customer is allowed. Moving to another quotation on edit refreshes both enquiries: the old one is Open and the new one PO received, and the new quotation becomes Accepted. Moving to a Draft is refused. Delete opens the lead again. A prospect's quotation gives a PO with no customer and the prospect kept, which shows in the list. | Tests added |
| — | OK | **Items on a CRM PO**: for a party quotation, that customer's sales items and items of no party are allowed; another customer's item or a purchase item is refused. A posted `party_id` does not widen this. A prospect's quotation allows any sales item, never a purchase item. With items the value is their sum, even when a bad value is typed. The items lookup follows the quotation, and with no quotation it gives none. | Tests added |
| — | OK (documented) | **PO menu POs after the switch is turned off**: nothing is deleted. A **direct** PO cannot be reached at all: CRM show / update / delete give 404, `/po` gives 404, and its documents say "Separate PO menu is switched off". It is left out of the CRM list, CSV, lookup and dashboard count, which all agree (linked POs only). An **enquiry-only** PO opens in CRM with its documents. Saving it there asks for a quotation; with a sent one it moves to that quotation's enquiry, and the old enquiry is refreshed. A **Draft-linked** PO opens and saves in CRM unchanged, items included, and the quotation stays Draft. Switched on again, the direct PO and its documents are back untouched. No error pages. | Tests added; see questions 1–2 |
| — | OK | **Security**: another unit's quotation is refused ("made from an enquiry of your units") and not filled from `?quotation=`, and its items lookup is empty. Posted `party_id`, `enquiry_id`, `unit_id`, `source` and `ctx` are ignored. Tampered ids (`12abc`, an array, `-1`, `0`) give messages, not errors. Moving to another unit's quotation is refused. Another unit's PO is 403 for show / update / delete and is not in the lookup. A PO-rights-only user gets 403 from the enquiry lookup, only this unit's quotations from the quotation lookup, and no PO pending alert. | Tests added |
| — | Note | Line endings: the changed PHP files are CRLF, as the rest of the project. The `po-dual-v2-backup` copies are LF, so use `diff --strip-trailing-cr` to compare them. | — |
| — | Note | `PurchaseReportsReviewTest` (random mobile numbers) did not fail in this run, so it was not changed. | — |

## Questions (not changed)

1. **Direct POs while the switch is off** cannot be opened anywhere, and neither can their documents, until an Admin
   switches the PO menu on again. Should they be read-only in CRM › PO / Agreement instead (for example in the list with
   a "Direct PO" mark)?
2. Open questions 1–3 of the build still stand. Q1: an enquiry-only PO needs a quotation to save in CRM. Q2: a
   Draft-linked PO saves unchanged in CRM. Q3: "Open in PO menu" on the CRM page.
3. An old quotation made **without an enquiry** (before quotations needed one) that was linked in the PO menu shows in
   CRM, but cannot be saved there even unchanged: the pre-29/10 `exists` rule needs an enquiry. A Draft-linked PO, by
   contrast, saves unchanged. New quotations always have an enquiry, so this is for old data only.
4. The Access rights note still says "Used by CRM › PO / Agreement and the PO menu" while the PO menu is off. Fine?

## Files changed

- `config/nursery.php` (F1, F2)
- `app/Http/Controllers/Crm/DocumentController.php` (F2)
- `tests/Feature/SeparatePoFinalReviewTest.php` (new)

## Tests added

`tests/Feature/SeparatePoFinalReviewTest.php`, 6 tests:

- off by default for Admin and Sales: menus, register, `/po` 404, dashboard tile, Page setup text; only `page-setup.edit`
  switches it; on and off again
- CRM and PO menu both off: CRM is named (F2)
- PO menu POs (direct, enquiry only, Draft quotation, with documents) after the switch is turned off, and on again
- CRM page rules as before 29/10: messages, Rejected, earlier revision, move to another quotation, number per enquiry,
  delete, prospect quotation
- items on a CRM PO: party and prospect quotations, forged customer, items lookup
- security: units, forged fields, tampered ids, another unit's PO, lookups, PO pending alert

## Result

Full suite: `Tests: 423 passed (8953 assertions)` (417 before; 6 added, none removed or changed). `artisan view:clear`
was run, and `migrate:status` has nothing pending. `curl http://localhost/nursery-app/crm/pos` and `/nursery-app/po` each
answer 302 to `/nursery-app/login`. `bootstrap/cache/config.php` does not exist; tests ran on SQLite `:memory:`.

## To check in a browser

1. Admin › Page setup › Optional parts: **Separate PO menu** is unticked. Untick CRM and read the confirm text (F1);
   cancel it.
2. CRM › 6 PO / Agreement: type a Draft quotation in the picker's search and save. The old message shows.
3. Pick a sent quotation and add items: the item picker lists that customer's items. For a prospect's quotation, it lists
   any sales item.
4. Tick the Separate PO menu, enter a direct PO with a document in the PO menu, then untick it. The PO is gone from
   CRM and the dashboard count (question 1). Tick it again: it is back.

# Separate PO menu removed (02/11/2026)

## The request

> "Need to REMOVE newly developed menu PO with its pages from the menu and its dependencies, make sure rest
> functionality should not get affected."

The client chose:

1. Remove the separate PO menu entirely: the `/po` pages, PO list, PO register, the "Separate PO menu" switch and its
   Page setup row, the menu / dashboard / Access rights references, "Open in PO menu", and the PO-menu-only tests.
   Direct POs (no quotation) are no longer possible anywhere.
2. Keep CRM › 6 PO / Agreement exactly as it works now: quotation required, the rules from before 29/10/2026, optional
   Part Master item lines, documents.
3. Keep the **Create Sales Order** button, on the CRM PO page only. A CRM PO for a prospect (no party) keeps the button
   disabled with its hint.
4. Delete the one direct PO on the dev database.
5. Run the pending migration (`customer_po_id` on `sales_orders`).

## What changed for the user

- **No PO top menu**, and no PO register under Reports. The CRM menu has **6 PO / Agreement**, as before 01/11/2026.
- **Old bookmarks**:
  - `/po/{id}` and `/po/create?…` redirect permanently (301) to `/crm/pos/{id}` and `/crm/pos/create?…`, with the query
    kept.
  - Every other `/po` address answers **404**: `/po`, the PO list, CSV, lookups and the PO register.
  - A guest goes to the login page first, as for every page.
- **Admin › Page setup**:
  - Optional parts no longer has "Separate PO menu".
  - The CRM switch text no longer mentions a PO menu: "Everyone loses the CRM menu (with CRM › PO / Agreement), its pages,
    reports, alerts and dashboard. POs cannot be entered while CRM is off."
  - "PO / Agreement received" is under **CRM** again. Its fields are: Quotation (fixed, mandatory, as before 29/10/2026),
    PO / Agreement no, PO date, Site, PO value (when no items), PO quantity, Validity, Payment terms, GST / tax, Customer
    contact, Received by, Received date, Remarks and Items (Part master). There are no Customer or "Link to CRM" rows.
- **Admin › Access rights**: "PO / Agreement received" is back in the **CRM** group with its original label and no note.
  The rights are still `customer-po.*`, so roles keep them. They belong to the CRM switch again: with CRM off they are
  refused (Admin too) and the PO pages and documents say "CRM is switched off", as before 01/11/2026.
- **Dashboard**: the PO / Agreements tile is a CRM tile only, opening CRM › PO / Agreement. It counts the unit's POs. There
  is no PO section.
- **CRM › 6 PO / Agreement**: unchanged.
  - The rules are as before 29/10/2026: Quotation\*, the client and enquiry from it, a quotation sent to the customer in
    its current revision and not Rejected / Expired, and the number unique per enquiry.
  - The page keeps the items from the Item master, the documents with a new PO, the PO list (CRM POs, now without the Type
    column), the CSV and Create Sales Order.
  - There is no "Open in PO menu" button and no "Linked to CRM / Direct PO" badge.
- **Alerts**: PO pending is unchanged. It is CRM only, and needs PO / Agreement and Quotation view.

## Kept, by decision

| Topic | Decision |
|---|---|
| CRM switch (`crm`, 01/11/2026) | Kept, on by default. Removing it would mean editing every CRM page's `module` entry and the CRM route group. It never blocks anything while on, and Page setup still offers it. |
| CRM PO list (`/crm/pos/list`, CSV) | Kept, because the CRM page has had a "PO list" button since 01/11/2026, and point 2 asks to keep the CRM page as it is. Only the PO menu's list, with its Type filter, was removed. It can be dropped later if the client wants. |
| `customer_pos.source` column | Kept in the database. It is always `crm` now: it is the model default, and the service sets it on every save. |
| Item master and UOM "used" checks for `customer_po_lines` | Kept. |
| Modules: list form | `Modules::allows()` / `offOf()` still take a list (Site visit = `crm` + `site_visit`). Only the `crm|po` "either" form was removed. |
| Controller, request and view | Moved back under CRM (the namespace and folder from before 01/11/2026). `PoContext` was removed: there is one context. |

## Files

Originals are in the scratchpad `remove-po-menu-backup` folder, with their relative paths kept (the whole `tests` folder
too).

### Removed

- `app/Http/Controllers/Po/CustomerPoController.php` (moved, see New)
- `app/Http/Controllers/Po/PoRegisterController.php` (PO register)
- `app/Http/Requests/Po/CustomerPoRequest.php` (moved, see New)
- `app/Support/PoContext.php`
- `resources/views/po/form.blade.php`, `list.blade.php` (moved, see New), `register.blade.php`
- `tests/SeparatePo.php`
- The PO-menu tests: `PoModuleTest`, `PoModuleReviewTest`, `PoDualEntryTest`, `PoDualEntryReviewTest`,
  `SeparatePoFinalReviewTest`, `CustomerPoDirectTest` and `CustomerPoDirectReviewTest` (see Tests)

### New (moved back under CRM)

- `app/Http/Controllers/Crm/CustomerPoController.php`
  - CRM routes only.
  - No direct PO, Customer picker (`parties()` and its route removed), Enquiry options, Type filter or PO register query.
  - `items()` reads `?quotation=` only.
  - `blank()` fills from `?quotation=` only when it can take a PO.
  - `salesOrder()` kept.
- `app/Http/Requests/Crm/CustomerPoRequest.php`: only the CRM rules (quotation required with the old `exists` rule and
  messages, `checkQuotation()`), items and documents. Removed: `canLink()`, `link()`, `checkLink()`, the Customer rule and
  the old `po_type`.
- `resources/views/crm/customer-pos/form.blade.php`: Quotation\* block only. No Customer field, link block, "Open in PO
  menu" or type badge. Routes are `crm.customer-pos.*`.
- `resources/views/crm/customer-pos/list.blade.php`: the CRM PO list, without the Type column or filter.
- `tests/Feature/CrmCustomerPoTest.php` (see Tests).

### Changed

- `app/Services/CustomerPoService.php`:
  - Always the CRM step: the quotation is locked and checked (`stepQuotation()`), and gives the unit, client and enquiry
    (`setQuotation()`).
  - The quotation becomes Accepted, and the number is unique per enquiry.
  - Removed: `lockParty()`, `setCustomer()`, `linkOf()`, the "status kept" link log, the per-customer number rule, and
    the `$unit` / `$crmStep` arguments.
  - `canTakePo($q)` always needs a quotation made from an enquiry.
  - The log snapshot no longer has "PO type".
- `app/Models/CustomerPo.php`:
  - Removed `DIRECT`, `SOURCES`, `isDirect()`, `sourceLabel()` and `isLinked()`. The default source is `crm`.
  - `pageUrlFor()` (for "From PO" on a sales order) gives the CRM page only.
- `public/js/customer-po.js`: the items follow `#quotation_id` (there is no Customer field).
- `app/Support/Modules.php`: the `crm|po` either form was removed.
- `app/Support/Menu.php`: back to the version from before 01/11/2026. There is no `report_modules` filter and no per-sub
  module check; the gate already hides pages of a part that is off.
- `app/Support/CrmFlow.php`: `canTakePo($qt)`, comment.
- `app/Services/CrmCounts.php`: the PO tile is a CRM tile again, counting the unit's POs; `poTiles()` was removed.
- `app/Services/CrmAlerts.php`: comment only.
- `app/Http/Controllers/DashboardController.php`, `resources/views/dashboard.blade.php`: no PO section and no `poTiles`.
- `app/Http/Controllers/Crm/DocumentController.php`: `customer_po` documents belong to `crm`; the direct-PO refusal was
  removed.
- `config/nursery.php`:
  - `pages`: `customer-po` back in CRM (label "PO / Agreement received", `module => 'crm'`); the PO group was removed.
  - `nav`: the PO top menu was removed; step 6 is as before.
  - `reports`, `report_routes`, `report_perms`: the PO group was removed. `report_modules` was removed.
  - `modules`: `po` was removed and the `crm` texts updated.
  - `page_fields.customer-po`: group CRM, module `crm`, Quotation fixed; the Customer and Link to CRM rows were removed.
- `routes/web.php`:
  - The `po.*` group and the `crm.customer-pos.parties` route were removed.
  - Two unnamed 301 redirects were added (`po/create`, `po/{id}`).
  - Imports and comments were updated.
  - A stray non-UTF-8 byte in the Create Sales Order comment was fixed.
- `database/migrations/2026_11_02_000001_add_customer_po_id_to_sales_orders.php`: comment only (it no longer names the PO
  menu).
- Tests changed for the removed switch:
  - `tests/CrmFiveEntryFlow.php`, `PageSetupAndModulesTest` and `PageSetupReviewFixesTest`: the `setModules()` helpers no
    longer send `po`.
  - `PageSetupReviewFixesTest`: the toast and the cached settings no longer name the Separate PO menu.
  - `MenuBarTest`: the Reports count is the full config count again.
  - `PoToSalesOrderTest`: every case uses the CRM page.

### Checked, no change needed

`SalesOrderService`, `SalesOrder`, `SalesPage`, `sales/page.blade.php`, `sales.js` (Create Sales Order),
`ItemService` / `SalesMasterService` (used checks), `Access` and `PageSetupController` (list form still used), the
`partials/matrix.blade.php` note support (generic, now unused for PO), `crm/quotations/status.blade.php` ("Enter PO /
agreement" already opens the CRM page) and `CrmDashboard` (counts `source = crm`, which is now every PO).

A grep of `app`, `resources`, `routes`, `config`, `public/js`, `tests` and `database/seeders` finds no `po.*` route,
`PoContext`, `SeparatePo`, `isDirect`, `poTiles`, `crm|po` or `po` module reference left, only comments recording the
removal.

## Dev database

1. **Backup**: `storage/app/backups/nursery-before-remove-po-menu-20261102.sql` (mysqldump of `nursery`, before any
   change).
2. **Deleted record** (scratchpad script `delete_direct_po.php`, in a transaction, after checks):
   - The checks:
     - The only direct or unlinked PO was id 2.
     - Its `po_no` was 21312312, `source` direct, `quotation_id` and `enquiry_id` null, and its party "Test Party".
     - No sales order was linked to it.
   - Deleted (hard delete):
     - `customer_pos` id 2;
     - its 2 `customer_po_lines` rows;
     - its 1 `documents` row: id 7, "Screenshot 2026-10-23 223353.png", Customer PO.
   - File deleted from storage: `storage/app/private/documents/customer_po/2/Q3OpsSgJyEjvPshtbo11evH6PQTAmuEanj3GxksZ.png`.
     The now-empty folder `documents/customer_po/2` was removed.
   - Its activity log rows were kept as history.
   - No other direct PO exists. The only PO left is id 1 (`21312312`, source crm, quotation 1, enquiry 3).
3. **Switch row**: `module_settings` held a saved `po` row (id 2, on), written when an Admin ticked the Separate PO menu.
   It was deleted, and the `module-settings` cache key was cleared. The app ignores unknown rows anyway; this only tidies
   up. The other four rows (crm, site_visit, boq, costing, all on) are unchanged.
4. **Migration**: `2026_11_02_000001_add_customer_po_id_to_sales_orders` had **already been run** by the previous
   developer, in batch 19, after the backup `nursery-before-po-to-so-20261102.sql`. So there was nothing to run.
   `migrate:status` has nothing pending. No sales order has `customer_po_id` set yet.

## Tests

### Removed, after moving what still applies

| Removed file | Moved to `CrmCustomerPoTest` | Dropped (PO menu / direct PO only) |
|---|---|---|
| `SeparatePoFinalReviewTest` | CRM rules as before 29/10 (messages, Rejected, earlier revision, move to another quotation, number per enquiry, delete, prospect); items follow the quotation; security (units, forged fields, tampered ids, another unit's PO, lookups, PO pending alert); CRM off names CRM | switch off / on, PO-menu POs after the switch is turned off |
| `PoDualEntryTest` | the CRM page takes a PO only against a sent quotation; a PO-rights-only user uses the CRM page | switch matrix, PO list Type filter, contexts, the direct PO not being a CRM record |
| `PoDualEntryReviewTest` | the CRM page scoped to the user's units; tampered quotation id messages | the forged context, hidden "Link to CRM", the PO module on / off |
| `CustomerPoDirectTest` | items of another party, inactive or purchase-only refused; a CRM PO with items (sum); Page setup items mandatory or hidden; a used item cannot be deleted | direct PO creation, editing and deleting, the per-customer number |
| `CustomerPoDirectReviewTest` | amounts beyond decimal(15,2); quantities that round to nothing, negative or text rates, more than 200 items, bad item ids; tampered rows; a line id of another PO; forged unit, source, prospect and creator; add and edit rights separate; items hidden in Page setup; a used UOM cannot be deleted | customer change on a direct PO; the 29/10 migration rollback guard (it needs a direct PO) |
| `PoModuleTest`, `PoModuleReviewTest` | CRM off refuses the PO pages | the PO menu, the optional link, the PO list and register, the PO switch |

### Added

`tests/Feature/CrmCustomerPoTest.php`, 9 tests:

- **The PO menu is gone**:
  - no `po.*` route, `po` module, PO report group or PO rights group;
  - for Admin and Sales, no PO top item and the CRM menu has "6 PO / Agreement";
  - `/po`, `/po/list`, `/po/export`, `/po/lookup`, `/po/parties`, `/po/items` and `/po/reports/register(/download)`
    answer 404, and so does a POST to `/po`;
  - `/po/{id}` and `/po/create?quotation=` redirect 301 to the CRM page;
  - the CRM page has no "Open in PO menu" or "Direct PO";
  - the dashboard has no PO section, and the tile opens CRM › PO / Agreement;
  - Page setup has no "Separate PO menu" or "Link to CRM", and Access rights has the label without the PO-menu note;
  - an old switch form that posts `po` is refused and nothing is saved.
- **CRM switched off**: the PO rights (Admin too), the menu, the page, the PO and its documents all say "CRM is switched
  off", and the dashboard tile goes. Switched on again, the PO and its document are back.
- **CRM rules as before 29/10/2026**:
  - the form has Quotation\* only, and `?quotation=` fills it only for a sent quotation;
  - the old messages;
  - a posted customer or `po_type=direct` does not make a PO;
  - Accepted and PO received;
  - the number per enquiry;
  - the quotation cannot be cleared, and moving it refreshes both enquiries;
  - the list and CSV, delete, and a prospect's PO.
- **Items follow the quotation**:
  - the party's items and items of no party; another customer's, purchase or inactive items are refused;
  - the value is the sum;
  - the items lookup;
  - a forged customer does not widen the items;
  - a prospect's quotation allows any sales item;
  - Page setup mandatory or hidden.
- **Item limits, tampered rows and line ids.**
- **Security**:
  - another unit's quotation;
  - forged customer, enquiry, unit, source, type, prospect and creator are ignored;
  - tampered ids give messages;
  - another unit's PO is 403 for Sales and the clerk, and is not in the list, CSV or lookup;
  - lookups;
  - no PO pending alert on PO rights alone.
- **Rights**: a PO-rights-only user, and Add and Edit as separate rights.
- **Documents saved with a new PO**: the toast, storage, the box and download; a bad type saves nothing.
- **Used items and UOMs** cannot be deleted.

`PoToSalesOrderTest` (5 tests, rewritten for the CRM page) keeps the Create Sales Order coverage: see
`docs/sales/po-to-sales-order-report.md`.

### Result

Full suite: `Tests: 1 skipped, 386 passed (8052 assertions)`.

- 64 PO-menu tests were removed with their seven files, and 9 were added (`CrmCustomerPoTest`). No other test was
  removed.
- The skipped test is the existing "write sample pdf" (it needs `TAX_INVOICE_SAMPLE_PDF=1`).
- One earlier full run had 4 failures. Each passed when run alone, and the next full run was green:
  - `CrmUploadTest` and `CustomerPoUploadTest` (storage-fake file counts);
  - `TaxInvoiceFixesTest` (the PDF memory ceiling, and the logo case without GD).
  None of them touches the changed code.

Other checks:

- `artisan view:clear` was run.
- `artisan route:list --name=po.` finds no routes.
- `migrate:status` has nothing pending.
- `bootstrap/cache/config.php` does not exist; the tests ran on SQLite `:memory:`.
- `curl http://localhost/nursery-app/crm/pos` answers 302 to `/nursery-app/login`.
- `/nursery-app/po` and `/nursery-app/po/list` answer 404.
- `/nursery-app/po/1` and `/nursery-app/po/create` answer 302 to the login page for a guest. Signed in, they redirect
  301 to the CRM page, as the tests check.

## To check in a browser

1. The top bar has no PO menu, and Reports has no PO register. CRM shows "6 PO / Agreement".
2. Admin › Page setup › Optional parts: no "Separate PO menu". "PO / Agreement received" is under CRM, with Quotation
   fixed.
3. Admin › Access rights › a role: "PO / Agreement received" is in the CRM group.
4. CRM › 6 PO / Agreement: PO 21312312 (id 1) opens. There is no "Open in PO menu" button. Its client is a prospect (no
   Party customer), so Create Sales Order shows disabled, with the hint "Add this client to the Party master first…".
5. Open `/nursery-app/po/1`: it lands on `/nursery-app/crm/pos/1`. `/nursery-app/po` gives "Not found".
