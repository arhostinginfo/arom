# AMC module: end-to-end re-test (22/11/2026)

Tester's run through the whole AMC module with today's changes, which are not committed yet. I read the code changes,
clicked through the app in the built-in browser on http://localhost/nursery-app (desktop and 375 px phone width), and
ran the full automated test suite. Nothing was committed or pushed. No CRM, Sales or shared file was changed.

## Result in one line

Everything works, after **two Return & Replace bugs were fixed** in AMC files. **One automated test outside AMC fails**:
the new Return & Replace entry picker uses radio buttons, and an older project rule says there must be no radio buttons
in any screen. You need to decide which rule wins (see Questions).

## Results per area

| Area | Result | Notes |
|---|---|---|
| Menu | Pass | AMC: Dashboard, AMC Client Inventory, AMC Tracker, Return & Replace, Masters; "AMC reports": AMC Report. Reports menu has an "AMC" group with AMC Report. CRM and Sales menus are the same as before (no shared menu file is changed). |
| Party master (Customer parties) | Pass | 3 Customer parties and 1 Supplier party made for the test. |
| Masters page and tabs | Pass | 5 cards with counts; tabs on each master; current tab marked. Names cannot repeat ("zz areca palm" refused). In-use entries cannot be deleted ("used on 5 record(s) … make it inactive"). |
| AMC Employee (photo preview) | Pass | Photo chosen in the file box shows before saving (JPG and PNG); saved photo shows after Submit. Employee in use cannot be deleted. Deleting an employee removes its photo file. |
| AMC Client Inventory: Customer dropdown | Pass | Only active Customer / Both parties; Supplier not offered; a party already used is not offered to a new client but stays on its own client. |
| AMC Client Inventory: Gardener dropdown | Pass | Only active Gardeners; the Operation Manager is not offered. |
| AMC Client Inventory: Locations | Pass | 2 Locations with 2 and 3 entries saved in the right order. Edit: added a row under Location A, removed one entry in B, added Location C, added and removed Location D. Saved order: A1, A2, A3, B Lobby, B Cafe, C Gate (as on screen). A wrong quantity shows the error under the right entry, and all typed rows come back. |
| Client list, CSV download, template | Pass | CSV still has "Location 1" and one row per entry. |
| Client CSV import (party check) | Pass | A file with an unknown name, a Supplier and a client already in the list is refused row by row, nothing saved. A good file (name typed in lower case) imports with the party's spelling and link. |
| AMC Tracker | Pass | Title "AMC Tracker". Picking a customer fills its gardener when empty; switching to a customer with no gardener clears the auto-filled one; a gardener picked by hand is kept. Dates, amount, Submit. |
| Contract files | Pass | PO PDF kept byte for byte; 3 images became one collage. File delete (with confirm box) works; deleting a contract deletes its files from disk. |
| AMC Report | Pass | Status column shows a coloured badge with the status only: black (overdue), red (due in 30 days), green, grey (Inactive). Search, Status and Colour filters, renewals (`?renewal=1`) and CSV (follows the filter) all correct. Old address /amc/contracts/list still opens. |
| Dashboard tiles | Pass | Active clients 3, renewals due 3, overdue 1, new orders 6; monthly revenue checked by hand and correct. Tiles open the AMC Report with the right filter. |
| Overdue / renewal alerts | Pass | Red "AMC overdue" pop-up on an overdue contract; amber "Renewal due" bar on one ending in 23 days. |
| Return & Replace: Location → entries → pick | **Fail, fixed** (2 bugs) | Location list = distinct Location 1 of the customer; its entries show in a table; a Location with one entry picks it by itself; picking fills Location 2 and, when empty, Plant (and its size). Gardener from the Active contract, else from the client (with a note). See bugs 1 and 2. |
| Return & Replace: save, edit, list, CSV, delete | Pass | After the fixes. Server refuses a Location of another customer and an entry that is not under the chosen Location. |
| History / activity log | Pass | "Recent changes" on each page; every add, change, upload, import and delete logged. |
| Access rights (limited user) | Pass | Role with Client view/add/edit, Return view/add, Plant view: menu shows only AMC Client Inventory, Return & Replace and Masters (Plant only). Dashboard, Tracker, Report, Planter, Employees, import: 403. Return edit page is read-only. Delete / edit return, add contract, add plant and import by direct request: 403. CRM page: 403 (no CRM rights). |
| Phone width (375 px) | Pass after fix | No sideways scrolling on client form, return form, AMC Report, contract, Masters, dashboard, employee. Client Locations show as stacked cards. |
| Every AMC form's button says "Submit" | Pass | |

## Bugs found and fixed (AMC files only)

1. **Return & Replace: changing the Location on an existing entry, without picking an entry, kept the old entry.**
   The entry is a radio button; when none is picked the browser sends nothing, so the saved entry (and its Location 1 and
   Location 2) stayed as before, while the screen showed the new Location. Changing the customer had the same effect.
   Fix: `app/Http/Requests/Amc/AmcReturnRequest.php` (`prepareForValidation`): no entry picked now means "no entry".
   Test: `tests/Feature/AmcEndToEndRetestFixesTest.php::test_edit_to_another_location_without_an_entry_clears_the_old_entry`
   (fails on the old code, passes now).
2. **Return & Replace on a phone showed the entries of every Location**, not only the chosen one, and on all widths an
   empty "Entries under this Location" box showed before a Location was chosen. The page hides them with the `hidden`
   attribute, but the AMC table-card and field styles override it.
   Fix: `public/css/amc.css` (2 rules at the end).
   Test: `AmcEndToEndRetestFixesTest::test_hidden_entries_stay_hidden_on_a_phone`.

## Automated tests

`php artisan test` (whole suite, in-memory test database; `bootstrap/cache/config.php` does not exist):

- Before my fixes: **1 failed, 1 skipped, 460 passed.**
- After my fixes: **1 failed, 1 skipped, 462 passed** (the 2 new tests are the only difference; the same 1 failure remains).

The one failure is `CombinedChangesReviewTest::test_source_files_are_clean_utf8_without_conflict_markers_or_po_menu_leftovers`.
It checks that no screen has a radio button (an earlier owner decision: "radio groups to drop-downs"). The new Return & Replace
entry table (`resources/views/amc/returns/form.blade.php`) uses one radio per entry, as asked. I did not change it or the test:
it needs your decision (Question 1).

## Seen, not fixed

- **Random "No application encryption key" errors** in `storage/logs/laravel.log` during the run (known XAMPP + Windows issue
  with .env, reported before). Not AMC.
- **Empty folders** stay on the AMC disk after a contract's files or an employee photo are deleted (the files themselves are
  removed). Harmless; I removed the ones this run created.
- Tampered request only: choosing a Location of another customer together with an entry gives two similar messages
  ("Choose a Location…" and "Choose a location…"). The page itself cannot send this.
- Switching from a customer with an Active contract to one without keeps the first customer's AMC type on Return & Replace
  (the gardener is replaced). Old behaviour, unchanged.
- The client's history compares entries by position ("Line 3: old → new"), so adding a row in the middle shows several lines
  as changed. Old behaviour.
- Client delete does not exist in the app (only View / Add / Edit rights). Test clients had to be removed in the database.

## Throwaway data (database "nursery"): all created and then removed

Created (all names start with "ZZ"):

- Parties: ZZ Cust Alpha, ZZ Cust Beta, ZZ Cust Gamma (Customer), ZZ Supp Delta (Supplier): ids 6 to 9. Deleted in the app,
  then removed from the table.
- AMC masters: plants ZZ Areca Palm (5 ft, id 6), ZZ Snake Plant (2 ft, id 7); segment ZZ Segment (id 6); planters
  ZZ Fibre Pot (id 4), ZZ Ceramic Pot (id 5); AMC type ZZ Comprehensive (id 4). Deleted in the app.
- AMC employees: ZZ Ops Manager (AE0001, id 10, photo), ZZ Gardener One (AE0002, id 11, photo), ZZ Gardener Two (AE0003, id 12).
  Deleted in the app (photos removed from disk).
- AMC clients: ZZ Cust Alpha (id 10, lines 13 to 19) and ZZ Cust Gamma (id 11, imported, lines 20 to 22). Removed in the
  database (the app has no client delete).
- Contracts AMC-0011 to AMC-0014 (ids 11 to 14) with 2 files (PO PDF id 6, collage id 7). File 6 deleted from the page, the
  rest with the contracts in the app; files gone from disk.
- Return & Replace RR-0004 (id 4). Deleted in the app.
- Role "ZZ AMC Limited" (id 10) and user zz.amclimited@nursery.test (id 9) with its unit and role rows. Removed in the
  database; permission cache cleared.
- Activity log rows 238 to 291 (sign-ins, adds, changes, deletes of the items above). Removed.
- Empty folders left by the app: `storage/app/private/amc/contracts/11`, `.../employees/8spcyQNe`, `.../employees/CdfAvI1L`. Removed.

After clean-up the AMC tables, parties, users, roles and activity log have the same rows as before the run (AROM TECHNO,
Satish, Test Party, AMC-0009, AMC-0010, RR-0003, Ganesh and their files untouched). ID counters have moved on (normal).
The browser was left signed out.

## Questions

1. **Radio buttons on Return & Replace.** You asked for "a radio to pick one" entry; an earlier rule (checked by an automated
   test) says the app has no radio buttons. Keep the radios and update that test to allow this one table, or change the
   entry picker to a drop-down (or a "Pick" button per row)?
2. Once an entry is picked on Return & Replace it cannot be un-picked except by choosing another Location. Add a "No entry" choice?
3. Should picking an entry replace the Plant even when one is already chosen (today: only when the Plant box is empty)?
4. Should AMC clients get a Delete (for clients without contracts or returns)?
