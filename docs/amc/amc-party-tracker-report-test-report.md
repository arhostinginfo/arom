# AMC test report: Party dropdown, Submit buttons, AMC Tracker, AMC Report (22/11/2026)

Tester's check of four AMC changes that are not committed yet. Tested in the built-in browser on http://localhost/nursery-app
(desktop and 375px phone width), by reading the code changes, and with the automated AMC tests.

## Result in one line

All four changes work. Two bugs were found and fixed in AMC files (a repeated error message, and the AMC Report not being
marked in the menu when opened from Reports). Nothing was committed.

## 1. Customer name is a Party dropdown: PASS (one fix)

- The "Customer name" box on Add AMC client is a type-ahead list. It shows only **active** parties of type **Customer** or
  **Both**. A Supplier party and an Inactive customer do not show. Search works (for example "zz b" finds only the Both party).
- After a client is saved with a party, that party no longer shows in the list for a new client. On the client's own edit
  page its party still shows and stays selected.
- Saving picks up the party's name as the client name.
- The server refuses a Supplier, an Inactive party, a party already used by another client, an empty choice, and a typed
  name that is not a party. Each gives a clear message.
- AROM TECHNO and Satish (not linked to a party) open with an empty Customer box and the hint `Not linked yet: was "AROM TECHNO"`,
  so the user is asked to choose a customer before saving. They were not changed.
- CSV import: a file with a name that is not a party, a Supplier, an Inactive customer, or a customer already in the master is
  refused, row by row, and nothing is saved. A file with an active customer party (typed in lower case) imports, with the
  party's name and link.
- Phone width: the list opens as a sheet at the bottom of the screen; no sideways scrolling.

**Bug fixed:** submitting with no customer showed two messages for the same field ("The customer name field is required."
and "Choose the customer from the Party master."). Choosing a customer already in the master also showed "already in the
AMC Client Master" twice. Now only the customer (party) message shows.
File: `app/Http/Requests/Amc/AmcClientRequest.php` (in `after()`). Test added: `AmcClientPartyTest::test_wrong_customer_is_reported_once`.
Two older tests were changed to expect the message on the customer field: `AmcClientTest` and `AmcEndToEndTest`.

## 2. Every AMC form's save button reads "Submit": PASS

Checked on AMC client, AMC Tracker (contract), AMC employee, the four simple masters, and Return & Replace, both for new
and existing entries. Other buttons (Delete, Upload, Search) keep their own names.

## 3. "Customer PO / AMC" is now "AMC Tracker": PASS

- AMC menu item reads "AMC Tracker" (desktop bar, side menu and phone menu).
- /amc/contract/create: page title and heading "AMC Tracker".
- Access rights (Admin › Access rights): row label "AMC Tracker and AMC Report".
- "Customer PO / AMC" no longer appears on any AMC screen (only in code comments).

## 4. AMC list is now "AMC Report" under Reports: PASS (one fix)

- No "AMC list" item in the AMC menu. "AMC reports › AMC Report" shows in the AMC menu, and an "AMC" group with "AMC Report"
  shows on the Reports page and in the Reports menu.
- Page title and heading "AMC Report".
- Search, Status and Colour filters, sorting, rows per page, Clear and Download CSV all work. The filter keeps `?in=amc`.
- A user with only `amc-contract.view` sees AMC Report in both places and can open it. A user without it does not see it, and
  opening it directly is refused (403).
- Phone width: the report shows as cards; the phone menu marks "AMC Report".

**Bug fixed:** opened from the Reports menu or page, the AMC Report was not marked in any menu: the side menu fell back to the
full menu with nothing selected (the CRM and Sales reports keep the Reports menu open with the report marked). The reason is
that the shared menu code only marks Reports for pages whose route name starts with `reports.`. Fixed in AMC files only:

- New route `reports.amc.report` at `/reports/amc/amc-report`, the same page as `/amc/contracts/list` (`routes/amc.php`).
  `config/amc.php` now points the AMC Report at it. The old address still works.
- Opened from Reports: Reports menu open, "AMC Report" marked. Opened from the AMC menu (`?in=amc`): AMC menu marked.
- The filter form now submits to the page it is on, and "Clear" keeps `?in=amc` (it used to drop it).
- Links inside AMC (dashboard tiles, the "AMC Report" button on AMC Tracker, back to the report after a contract is deleted,
  and "AMC Tracker" for a user who may only view) open the report inside the AMC menu.

Files: `routes/amc.php`, `config/amc.php`, `resources/views/amc/contracts/list.blade.php`, `resources/views/amc/dashboard.blade.php`,
`resources/views/amc/contracts/form.blade.php`, `app/Http/Controllers/Amc/AmcContractController.php`.
Test added: `tests/Feature/AmcReportMenuTest.php`. Tests updated for the new link: `AmcAccessTest`, `AmcContractTest`,
`AmcEndToEndTest`, `AmcReviewFixesTest`.

## Automated tests

`php artisan test --filter=Amc`: 59 passed after the fixes above.

A later run showed 1 failure in `AmcReturnTest` (entry is saved with locations and plant size copied). Return & Replace files
(`AmcReturnController`, `AmcReturnRequest`, `AmcReturnService`, a new `clients/line-row.blade.php`) were being changed by
someone else while this test ran. The failure comes from that work, not from the four changes tested here.

## Seen, not fixed (not AMC files)

- **Random 500 errors on this XAMPP setup.** One type-ahead request failed with 500. The log shows
  "No application encryption key has been specified" now and then. This is a known problem when the .env file is read on a
  Windows Apache server with several threads. Fix: cache the config on the dev server (`php artisan config:cache`; note this
  creates bootstrap/cache/config.php, which must be removed before running tests), or run PHP outside Apache's threads.
- **The shared type-ahead (public/js/picker.js) remembers a failed answer as "No matches"** for 60 seconds, so typing the same text again
  still shows "No matches" after a server error. It should not keep failed answers. Shared file, so not changed.
- Small: if a party's name is changed in the Party master, the AMC client keeps the old name until the client is saved again.

## Throwaway data (all removed from database "nursery")

Created, then deleted:

- Parties: ZZ Cust Active (Customer), ZZ Both Active (Both), ZZ Supplier Active (Supplier), ZZ Cust Inactive (Customer, Inactive), ids 2 to 5.
- AMC clients: ZZ Cust Active (id 8, added on the form) and ZZ Both Active (id 9, imported from CSV). They had no lines or contracts.
- Roles: "ZZ AMC Report Only" (amc-contract.view) and "ZZ AMC No Report" (amc-client.view), ids 8 and 9, with their permission rows.
- Users: zz.report@nursery.local and zz.noreport@nursery.local (ids 7 and 8), with their role rows.
- Activity log rows 230 to 237 (sign-ins and sign-outs, the client add and the import).

AROM TECHNO, Satish, Test Party and the other existing data were not changed. The permission cache was cleared afterwards.
The browser was left signed out.

## Questions

1. Should a client that is not linked yet (AROM TECHNO, Satish) be linked when someone saves its page, or should someone link
   them now by adding matching parties?
2. Should a party name change in the Party master also update the AMC client's name?
3. For a user who may only view contracts, "AMC Tracker" opens the AMC Report. Is that wanted, or should the item be hidden for them?
