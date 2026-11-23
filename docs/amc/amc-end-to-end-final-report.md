# AMC module: final end-to-end test (24/11/2026)

Tested in the built-in browser at http://localhost/nursery-app as Administrator, at desktop width and at 375 px, plus a
temporary limited user. Dev data (AROM TECHNO, Satish, Planter Party, Test Party, RR-0003 / RR-0009, AMC-0009 / 0010 / 0015,
Jade Plant with an empty Rate, Ganesh, Planter master, Monthly) was not changed: table checksums of all `amc_*` tables,
`parties` and `roles` are identical before and after (only `users` differs; see "Records").

## Results

| Area | Result | Notes |
|---|---|---|
| 1. Menus | Pass | AMC: Dashboard, AMC Client Inventory, AMC Tracker, Return & Replace, Masters. "AMC Report" under AMC and under Reports. CRM and Sales menus show their usual items; no layout/menu file changed. |
| 2. Masters | Pass | Masters page lists the five masters; tabs on each master page. Plant Rate: "₹1,234.5" saved as 1,234.50 and shown in the Rate (₹) column; "12.345" refused ("at most 2 decimals"). Employee photo preview on create and on edit (before Submit). Deleting E2E Plant while used by client lines and return rows refused ("used on 4 record(s)"). |
| 3. AMC Client Inventory | Pass | Customer dropdown = active Party customers not yet used (Planter Party left out). Gardener dropdown = active gardeners. Location typed once, Add row / Add Location, 3 entries saved. CSV export correct; CSV import refuses an existing customer and an unknown party, nothing saved. Renaming the E2E party in the Party master renamed the AMC client. "No customer to choose" message covered by the feature tests (dev data has a free party). |
| 4. AMC Tracker | Pass | Customer info (segment + locations) shown, gardener auto-filled, Submit saved AMC-0016; two JPGs uploaded together became "Collage of 2 images.jpg". |
| 5. AMC Report | Pass | Status column shows a badge with the status only ("Active"), coloured by state; no colour name or "Due within 30 days" in the cell. Colour/Status/Search filters correct; CSV export filtered. |
| 6. Dashboard and alerts | Pass | New contract counted in Active clients, Renewals due, Renewal alerts, New orders and monthly revenue; contract page shows the "Renewal due" alert. |
| 7. Return & Replace | Pass (1 bug fixed) | Customer → Location → entries with "Replaced so far" + summary; click / Add makes a row with Location 2, Planter, Plant, Size, Rate as text; only Planter qty, Plant qty, Quality editable; planter qty disabled for an entry without a planter; "of N"; live red mark over the limit; server message on the right row and rows kept on a refused save; Amount and Total live; no return-level Quality; Location change asks first and Cancel keeps the rows; unsaved-changes guard active. Saved return kept 1,234.50 / 2 ft after the E2E Plant became 2,000 / 3 ft (also after a refused update). List shows rows, total qty, row qualities; Quality filter; CSV with Rate, Amount and per-row Quality; Delete. |
| 8. Access | Pass | User with only AMC dashboard/client/return/plant View: menu shows only those; Tracker, AMC Report, other masters, employees, create pages, CRM, Sales, Party master → 403; client and return pages read-only (fieldset disabled, no Submit/Delete); every POST/PUT/DELETE tried (returns, plants, client, import, contract, files, purge, employee photo) → 403. |
| 9. Phone 375 px | Pass | No sideways page scroll on any AMC page (scrollWidth 375); all tables switch to cards (thead hidden, rows as grid). Master tabs scroll inside their own strip. |
| Submit label / radios | Pass | Every AMC form's save button says "Submit"; no `type="radio"` on any AMC page. |

## Bug found and fixed

**Return & Replace edit: a saved row taken out and its entry clicked again showed the Plant master's new rate and size,
but was saved with the old ones** (the service keeps a saved row's rate/size per entry + plant). The page now shows
what will be saved.

- `app/Services/Amc/AmcReturnService.php:101` new `savedRows()` (saved rows of a return keyed by entry + plant)
- `app/Http/Controllers/Amc/AmcReturnController.php:141` passes `savedRows` to the form
- `resources/views/amc/returns/form.blade.php:68-69` entry rows use the saved size / rate in `data-size` / `data-rate`
- `app/Http/Controllers/Amc/AmcClientController.php:114-116,132` client info (`?except=` return) returns the saved size / rate, so re-picking the customer on the edit page agrees too
- Test: `tests/Feature/AmcReturnRowsTest.php` `test_entry_added_again_on_edit_shows_the_saved_rate_and_size`

## Reported, not changed (shared / live code)

- Party master (Admin › Masters › Parties, shared with CRM/Sales) buttons say "Save party" / "Save changes", and Admin
  users/roles say "Create user" / "Save user" / "Create role", not "Submit". Not changed: shared with the live modules.
- The AMC Tracker form hint still explains the report colours by name ("Green while Active, Red when ..."), and the
  contract page header badge says "Due within 30 days". The AMC Report itself follows the rule. Say if those should change too.

## Records created and removed

| Record | Created via | Removed via |
|---|---|---|
| Plant "E2E Plant" (id 8) | Plant master | UI Delete |
| Planter "E2E Planter" (id 6) | Planter master | UI Delete |
| Business segment "E2E Segment" (id 7) | Masters | UI Delete |
| AMC type "E2E Type" (id 5) | Masters | UI Delete |
| AMC employee "E2E Gardener" AE0001 (id 13) + photo | Employee master | UI Delete (photo file removed) |
| Party "E2E Customer" (id 11, renamed "E2E Customer Two" for the rename test) | Party master | SQL DELETE (after checking nothing else referenced it) |
| AMC client "E2E Customer Two" (id 14) + lines 30, 31, 32 | AMC Client Inventory | SQL DELETE in the stated order |
| Contract AMC-0016 (id 16) + collage file | AMC Tracker | UI Delete contract (files deleted with it) |
| Return RR-0012 (id 12) + 2 rows | Return & Replace | UI Delete entry |
| Role "E2E AMC Viewer" (id 11) | Access rights | UI Delete |
| User "E2E Limited" (id 10) | Users | UI Delete user. The app soft-deletes users, so the row stays in `users` with `deleted_at` set (no roles or rights left). |

## Tests

- AMC tests (`--filter="Amc|CombinedChangesReview"`): 88 passed (1800 assertions); was 87 before the new test.
- Full suite: 477 passed, 1 skipped (10167 assertions). `bootstrap/cache/config.php` absent throughout.
