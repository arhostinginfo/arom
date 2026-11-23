# AMC module: end-to-end test with dummy data (24/11/2026)

I tested the whole AMC module in the built-in browser on http://localhost/nursery-app (dev DB `nursery`), signed in as a
temporary admin user. Every dummy record was added through the app's own pages, not SQL, and each name starts with "E2E ".
No source file was changed, and no CRM or Sales code or existing (non-dummy) record was touched. Nothing was committed or
pushed.

## Result in one line

Every area passed. I found no bug in AMC code, so no code was changed and no new test was added. The full test suite
passes (**484 passed, 1 skipped, 0 failed**).

## Results per area

| # | Area | Result | What was checked |
|---|---|---|---|
| 1 | AMC Masters | Pass | Masters page shows the 5 cards with counts (Business segment, Planter, Plant, AMC type, AMC Employee). Added 2 records in each. A duplicate is refused even in a different case ("e2e segment one": "This name is already in the list."; duplicate employee name refused too). Edit works: plant size changed 2 ft → 2.5 ft with the rate kept; gardener mobile changed. Rate accepted as `120.50` and `1,250` and listed under "Rate (₹)" as 120.50 / 1,250.00. Deleting an in-use plant (E2E Rose) is refused: "used on 4 record(s) … Make it inactive instead". Deleting an in-use employee (E2E Gardener One) is refused the same way. The employee photo (PNG) shows as a preview before Submit and is saved. The Gardener dropdowns offer only Gardeners; Operation Manager lists offer only Operation Managers. |
| 2 | Party master | Pass | E2E Customer One and Two added (Customer, Active). Only records were added; no Party code was changed. |
| 3 | AMC Client Inventory | Pass | 2 clients, each with a gardener, 2 Locations × 2 entries. Rate comes from the Plant master. Amount = Rate × Plant qty. Location totals and the Total amount update live when the plant or qty changes. A non-numeric qty or no plant clears the amount. Adding and removing a row or a Location updates the totals. Saved totals were correct (9,307.50 and 10,964.00). A party already used is no longer offered for a new client. CSV download works (one row per entry). Renaming party 13 to "E2E Customer Two Ltd" renamed AMC client 16 the same way; I then renamed it back. |
| 4 | AMC Tracker | Pass | Picking the customer fills in its gardener (both contracts). The 2 contracts were saved with dates, type, billing cycle, amount (`1,25,000` accepted), status and Operation manager. "End before start" is refused. 3 deployment images uploaded together became one "Collage of 3 images.jpg". AMC-0018 (ends in 16 days) shows the "Due within 30 days" badge and the amber "Renewal due" bar. |
| 5 | Return & Replace | Pass | The entries table shows only the chosen Location, with "Replaced so far" and the summary line. "History (n)" links open in a new tab (`target=_blank`), and clicking one does not add the entry. Clicking an entry that is already added does not add it twice. Qty 11 on an entry of 10 is marked red while typing, and the server refuses it with "At most 10 (the plant qty in the AMC Client Inventory)." Quality is required on every row ("Choose the quality."). Rows come back after a refused save. Rate comes from the Plant master; Amount and Total are correct (2,861.50; 1,732.00). Entry Lobby (line 33) was returned twice: RR-0013 (Replaced) and RR-0014 (Open). The same goes for Cafe (line 38): RR-0015 (Replaced) and RR-0016 (Open). RR-0013 and RR-0015 have several rows. Editing RR-0014 (qty 2 → 3, remark) saved. The History button is on the list rows and on the edit page. The History page shows each Location, then each entry, newest first, with Open/Replaced status, row amounts and totals. The Location filter works for one Location and for "All locations" (`?location=`). The CSV export follows the search and quality filter and has Rate and Amount. The quality filter and customer search are correct. Returning an entry any number of times is allowed, as intended. |
| 6 | AMC Report and Dashboard | Pass | Report: AMC-0017 is green, AMC-0018 is red. The colour, renewal and search (by name and by AMC no) filters work. The CSV follows the filter. Dashboard: active clients 2, renewals due 2, overdue 1, new orders 5. Monthly revenue was checked by hand: Nov 2026 21,098.90, Dec 2026 11,579.67, Jan 2027 1,964.29, all correct. AMC-0018 is in Renewal alerts, and the tiles link to the Report with the matching filter. |
| 7 | Access | Pass | A limited user with only `amc-return.view` and `amc-plant.view`. The menu shows only AMC › Return & Replace and Masters. The Masters page shows only the Plant card, with no add form or Edit buttons. The Return list has no "Add new". The return edit page is read-only (fieldset disabled, no Submit or Delete). The History page and the CSV are allowed. Refused with 403: dashboard, planter, vertical and type masters, employees, client pages and CSV, tracker, report and its CSV, return create, Party master. Direct requests to delete a return, update a return or add a plant are also refused with 403. |
| 8 | Phone width (375 px) | Pass | No horizontal page scroll on any of 22 AMC pages: dashboard, all masters, employees, client list and form, tracker (new and both contracts), return list, create, edit, History, AMC Report. The Return form shows only the chosen Location's entries as stacked cards. The viewport was reset to desktop afterwards. |
| 9 | Browser console | Pass | No JS errors on any AMC page. The only console lines were the 403s from my own access checks and one browser notice about a `beforeunload` prompt. That notice was caused by my scripted navigation after typing in a form. No AMC form is marked as changed right after it loads (checked on 9 form pages). |

## Bugs found and fixed

None. No AMC file was changed, so no feature test was added.

## Observations (not bugs; owner may want to decide)

1. **Dashboard tile vs Report list:** "Total active clients" counts clients with an Active contract that runs today (2).
   Its link opens the Report with `status=Active`, which also lists Active contracts that have not started yet or are
   already overdue (5 rows), so the number on the tile and the number of rows differ.
2. **Client Inventory CSV:** it has no Rate and Amount columns, and it always exports every client, ignoring the search.
   This matches the import file format.
3. **Return & Replace quality filter:** it finds a return that has at least one row of that quality. The CSV then lists
   every row of that return, including rows of other qualities. The code says this is on purpose.
4. **Return list search:** it matches only the start of the customer name, not the RR number. The placeholder says so.
5. **Return edit page counts:** the "Replaced so far" badge leaves out the return being edited, but its "History (n)"
   count includes it (e.g. RR-0014 shows "Replaced 1×" with "History (2)").
6. **Old test user:** user id 10 "E2E Limited" (`e2e.limited@example.test`) is a soft-deleted user left over from an
   earlier run today, not created in this run. I left it as it was.

## Automated tests

`D:\xampp\php\php.exe artisan test` (whole suite, sqlite in memory): **Tests: 1 skipped, 484 passed (10249 assertions),
0 failed**, 213 s. `bootstrap/cache/config.php` does not exist.

## Dummy records kept in the dev DB

| Area | Name | id |
|---|---|---|
| Business segment | E2E Segment One | 8 |
| Business segment | E2E Segment Two | 9 |
| Planter | E2E Planter Round | 7 |
| Planter | E2E Planter Square | 8 |
| Plant | E2E Rose (2.5 ft, rate 120.50) | 9 |
| Plant | E2E Areca Palm (5 ft, rate 1,250.00) | 10 |
| AMC type | E2E Type Comprehensive | 6 |
| AMC type | E2E Type Basic | 7 |
| AMC employee | E2E Gardener One (AE0001, Gardener, with photo) | 14 |
| AMC employee | E2E Gardener Two (AE0002, Gardener) | 15 |
| AMC employee | E2E Ops Manager (AE0003, Operation Manager) | 16 |
| AMC employee | E2E Ops Manager Two (AE0004, Operation Manager) | 17 |
| Party | E2E Customer One (Customer, Active) | 12 |
| Party | E2E Customer Two (Customer, Active) | 13 |
| AMC client | E2E Customer One (Locations E2E Tower A / E2E Tower B; lines 33–36) | 15 |
| AMC client | E2E Customer Two (Locations E2E Block 1 / E2E Block 2; lines 37–40) | 16 |
| AMC contract | AMC-0017, E2E Customer One, 01/12/2026–30/11/2027, ₹15,000, visit plan "E2E weekly visit Monday" | 17 |
| AMC contract | AMC-0018, E2E Customer Two, 10/12/2025–10/12/2026, ₹1,25,000 | 18 |
| Contract file | Collage of 3 images.jpg (deployment, AMC-0017) | 9 |
| Return & Replace | RR-0013, E2E Customer One, E2E Tower A, 2 rows, Replaced 08/11/2026 | 13 |
| Return & Replace | RR-0014, E2E Customer One, Lobby again, Open | 14 |
| Return & Replace | RR-0015, E2E Customer Two, E2E Block 1, 2 rows, Replaced 03/11/2026 | 15 |
| Return & Replace | RR-0016, E2E Customer Two, Cafe again, Open | 16 |

Contracts and returns get automatic numbers (AMC-/RR-), so their names do not start with "E2E ". Each one belongs to an
E2E client, and the returns' remarks start with "E2E".

## Temporary login users

- `e2e.admin@nursery.local` (id 11, role Admin) and `e2e.limited2@nursery.local` (id 12, direct rights
  `amc-return.view` and `amc-plant.view`) were **hard-deleted**, along with their role, permission and unit links and their
  sessions. Checked afterwards: no row is left in `users`, `model_has_roles`, `model_has_permissions` or `sessions`.
- Their passwords were only in scratch files, and those files have been deleted.
- Because the users were deleted, the "Added by / Updated by" fields on the dummy records are now empty (the database
  sets them to NULL).
