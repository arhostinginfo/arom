# AMC module: what was built (Sat 21/11/2026)

The AMC (Annual Maintenance Contract) module is a new top menu, **AMC**. It is completely separate from CRM and Sales: it has its own tables, pages, rights and menu. No CRM or Sales screen, rule, table, data, script or style was changed.

## What a user sees

The **AMC** menu sits on the top bar between Accounts and Admin. Each page is shown only to users who have the right for it.

| Menu item | What it does |
|---|---|
| Dashboard | Tiles: total active clients, AMC revenue this month (and for the year), renewals due, overdue contracts, new orders in the last 60 days. Lists of contracts ending in the next 60 days (with days left), overdue contracts, and new orders, plus a 12-month revenue table for the financial year on the top bar. |
| AMC Client Master | A list of customers that you can search and filter by business segment, with Import, Download CSV and a green **Add new** button. Each customer has a name (no two customers can share one), a business segment and any number of lines. Each line has **Location 1** and **Location 2** (two separate boxes, for example "Tower A" and "Floor 5 - Lobby"), a planter with a quantity and a plant with a quantity. **Gardener name** is read-only and comes from the customer's current Active contract. |
| Customer PO / AMC | The contract form. Pick the customer and its segment and its table of locations, planters and plants appear. The AMC start date and end date come next, with a calendar picker. After them come AMC type, billing cycle (Monthly / Quarterly / Half-yearly / Yearly), visit plan, AMC amount, operation manager, gardener and status. Once saved, the contract shows the photos of the operation manager and gardener, with a button to replace each photo, and four file boxes: Customer PO / Agreement, Delivery challan documents, Delivery challan images and Deployment images. |
| AMC list | Every contract, coloured per row, with a legend: **Green** = Active with the end date more than 30 days away, **Red** = due within 30 days, **Black** = overdue (end date passed, still Active), **Grey** = Inactive. You can search by customer or AMC number, filter by status or colour, and download a CSV. |
| Return & Replace | A list (search, quality filter, CSV) and a form. Picking the customer fills its locations: choose a line and Location 1 / Location 2 fill in. It also fills the AMC type and gardener from the customer's Active contract (both can be changed). Picking a plant shows its size from the Plant master. Quality is a dropdown: Reusable / Needs Treatment / Repairable / Dead. |
| Business segment, Planter, Plant, AMC type masters | One screen each: the list on the left and the add / edit form on the right. Names cannot repeat (upper or lower case does not matter). An entry that is in use cannot be deleted; make it inactive instead. Plant also has a size (free text, for example "5 ft"). |
| AMC Employee master | Code (left blank, it becomes AE0001, AE0002 …), name, role (Operation Manager / Gardener / Other), mobile, photo, Active / Inactive. A new photo replaces the old one, and the old file is deleted. |

**Alerts.** If you open an AMC whose end date has passed and which is still Active, a **red pop-up** appears every time, along with a red bar on the page. If the end date is within 60 days, an **amber "Renewal due" banner** shows instead.

**Files and the collage.** Files are kept on the server's private disk, never in a public folder. They are opened through the app, so the user's rights are checked each time.
- If you choose 2 or more images together in one box, they are combined into **one JPEG collage**: 2 columns, each picture at most 800 pixels wide, quality 75. Only the collage is kept.
- A single image is saved again as a JPEG of at most 1,600 pixels.
- PDFs are kept exactly as uploaded.
- If the server has no image tools (PHP "GD"), images are kept one by one, unchanged, and the page says so. An upload never fails because GD is missing.

**Old deployment images.** Deployment images older than 1 year are deleted (both the file and its record) by a nightly job. An Admin also has a button, "Delete deployment images older than 1 year", on the AMC list and on each contract's Files box. PO / Agreement and delivery challan files are never deleted by this.

**Rights** (Admin › Access rights, new group "AMC"):

| Row | Columns |
|---|---|
| AMC dashboard | View |
| AMC Client Master | View, Add, Edit |
| AMC Client Master · Import | Add (= may import) |
| Customer PO / AMC and AMC list | View, Add, Edit, Delete (Delete = delete a contract or one of its files) |
| Return & Replace | View, Add, Edit, Delete |
| Each of the 5 masters | View, Add, Edit, Delete |

Admin has every right. Replacing a person's photo from the contract page needs the contract's Edit right (or AMC Employee Edit).

## Database (new tables only, all starting with `amc_`)

| Table | Holds |
|---|---|
| amc_verticals, amc_planters, amc_plants, amc_types | The four simple masters (name, active; plants also have a size) |
| amc_employees | AMC Employee master (code, name, role, mobile, photo, status) |
| amc_clients, amc_client_lines | AMC Client Master and its lines (Location 1, Location 2, planter + qty, plant + qty) |
| amc_contracts | Customer PO / AMC contracts |
| amc_files | Contract files (contract, category, path, original name, type, size, uploaded by, uploaded at) |
| amc_returns | Return & Replace entries (the location texts and plant size are copied when saved) |

Migration: `database/migrations/2026_11_21_000001_create_amc_tables.php`. It creates the 10 tables above and adds the 33 AMC permissions. No existing table, column or row is changed. Every add, change, delete, upload, import and clean-up also writes a row to the existing activity log.

**Development database:** I backed it up first to `storage/app/backups/nursery-before-amc-20261121.sql` and then migrated it. MySQL was already running, so it was left running.

## Files added

- `app/Providers/AmcServiceProvider.php`: plugs the module in. It loads the AMC routes, adds the AMC pages and menu from `config/amc.php`, registers the short names for the activity log, and registers the nightly clean-up.
- `config/amc.php`: AMC pages, rights, menu, masters, fixed lists and upload sizes.
- `routes/amc.php`: every AMC address, each behind its right.
- `app/Models/Amc/`: AmcMaster, AmcVertical, AmcPlanter, AmcPlant, AmcType, AmcEmployee, AmcClient, AmcClientLine, AmcContract, AmcFile, AmcReturn.
- `app/Http/Controllers/Amc/`: AmcDashboardController, AmcClientController, AmcContractController, AmcReturnController, AmcMasterController, AmcEmployeeController, AmcLookupController.
- `app/Http/Requests/Amc/`: AmcMasterRequest, AmcEmployeeRequest, AmcPhotoRequest, AmcClientRequest, AmcContractRequest, AmcFileRequest, AmcReturnRequest, UniqueName.
- `app/Services/Amc/`: AmcMasterService, AmcEmployeeService, AmcClientService (including the import), AmcContractService, AmcFileService (uploads, collage, clean-up), AmcReturnService, AmcDashboard, AmcImages (the GD picture work), AmcFiles (the private disk).
- `app/Console/Commands/AmcPurgeOldImages.php`: the `php artisan amc:purge-old-images` command.
- `database/migrations/2026_11_21_000001_create_amc_tables.php`
- `resources/views/amc/`: dashboard, masters/list, employees/index and form, clients/index and form, contracts/form and list, returns/index and form, and the partials assets, errors and photo.
- `public/css/amc.css`, `public/js/amc.js`: used only by the AMC pages.
- Tests: `tests/AmcFixtures.php` and `tests/Feature/AmcAccessTest.php`, `AmcMastersTest.php`, `AmcClientTest.php`, `AmcContractTest.php`, `AmcDashboardTest.php`, `AmcReturnTest.php`.
- This report.

## Shared files edited (each backed up before editing)

1. `bootstrap/providers.php`: **one line added**:
   ```php
       App\Providers\AmcServiceProvider::class,
   ```
   This single line makes all three approved touches happen: the AMC top menu, the AMC rows on Access rights, and loading the AMC routes. They are added from `config/amc.php` when the app starts, so `config/nursery.php`, `Menu.php` and `Access.php` themselves were **not** edited.
2. `tests/Feature/MenuBarTest.php`: **one expectation updated**. This test lists the top menus an Admin sees, and AMC is a new one:
   ```php
   private const ALL = ['dashboard', 'crm', 'sales', 'purchase', 'accounts', 'amc', 'admin', 'reports'];
   ```
   (It was the same list without `'amc'`.)

Nothing else that already existed was changed. The module only reuses existing helpers as they are: the page layout, list pager, sort headers, import dialog, icon buttons, activity log, CSV download and import, date and money formats, and the select-with-search and date picker scripts.

## Tests

- Before: 1 skipped, 400 passed.
- After: **1 skipped, 432 passed** (400 existing + 32 new AMC tests). All tests run on the in-memory test database only.
- The AMC tests cover: master names that cannot repeat and entries in use that cannot be deleted; rights per master; employee photo upload and replace (the old file is deleted); client add / edit with two locations per line; client import (rows grouped by customer, masters matched by name, the whole file checked first, nothing saved on any error); client CSV download and template; contract validation (dates, roles, amount); files (wrong types refused, PDF kept, the collage of several images, a single image resized); photo replace from the contract page; the overdue pop-up and 60-day banner; list colours, filters and CSV; dashboard figures; Return & Replace auto-fill, location check and quality; the clean-up command (old deployment images deleted, newer ones kept, PO and challan files never deleted, the Admin button); a user without AMC rights getting "not allowed" and no AMC menu; and CRM / Sales pages still opening.
- Pages were also checked at phone width (375 px): none of the 14 AMC screens scrolls sideways.

## On the live server

1. Upload the new files listed above and the edited `bootstrap/providers.php`.
2. Run `php artisan migrate` (back up the database first).
3. In cPanel › **Select PHP Version** › Extensions, tick **gd**. This makes the collage work; without it uploads still work, just without combining images.
4. Add the cron job, if the server does not have it already (cPanel › Cron Jobs, every minute):
   `* * * * * php /path/to/nursery-app/artisan schedule:run >> /dev/null 2>&1`
   This runs the nightly clean-up of deployment images older than 1 year at 01:30, and the existing financial-year job. Without the cron line, an Admin can use the "Delete deployment images older than 1 year" button instead.
5. Open `/admin/clear-cache` as Admin, so the menu, rights and routes are read again.
6. In Admin › Access rights, tick the AMC rights for the roles that need them. Admin already has all of them.

## Assumptions and things not done

- **Contract number:** shown as AMC-0001, AMC-0002 … from the record number. It is not a financial-year series and has no unit, because AMC contracts are not unit-wise.
- **Return & Replace number:** RR-0001 …, the same way.
- **"Active clients today"** counts customers with an Active contract whose start date ≤ today ≤ end date. An overdue contract still marked Active is shown under Overdue, not counted as an active client.
- **Monthly revenue:** each Active contract's amount is split evenly over the calendar months its period touches. For example, 1 April to 31 March is 12 months, and 15 April to 14 April next year is 13 months. So the months always add up to the amount. Inactive contracts are left out.
- **Client import** adds new customers only. A customer name that is already in the master is refused, with its row number, so that re-importing a downloaded file can never double the lines. Change an existing customer on its page.
- **Plant names** cannot repeat, as asked. So "Rose 2 ft" and "Rose 5 ft" would need different names.
- **AMC Employee names and codes** cannot repeat.
- **Collage for the PO / Agreement box:** the rule "2 or more images chosen together become one collage" applies to every box. A PO scanned as several JPG pages and uploaded together therefore becomes one collage. Upload them one at a time, or as a PDF, to keep the pages separate.
- **Phone layout of the client lines:** a client form holds up to 120 lines, which keeps it within the server's form-size limit. For more lines, use Import.
- **Not done (not asked or not approved):** no AMC entries in CRM Page setup or the module switches, no AMC tiles on the main Dashboard, no AMC alerts in the bell menu, no AMC reports in the Reports menu, and no billing or invoices from AMC contracts.
- The module was checked with automated tests and by viewing the rendered pages at phone width. It was not clicked through by hand on a signed-in browser session.

## Fixes after the test report (21/11/2026)

Approved by the owner: questions 1, 2 and 3 of `amc-test-report.md`.

1. **Activity log labels:** AMC actions now show plain names ("AMC added", "AMC file uploaded", "Return / replace added"...)
   in Recent changes and Admin › Activity log. Shared file `app/Models/ActivityLog.php`: 18 lines added to `ACTIONS`
   (owner approved; nothing else changed).
2. **"Renewals due" tile:** opens the AMC list filtered to Active contracts ending in the next 60 days
   (`?renewal=1`, with a note above the list; the CSV follows the filter). The Red colour filter still means 30 days.
   Files: `AmcContract::scopeDueForRenewal()`, `AmcContractController` filters, `amc/contracts/list`, `amc/dashboard`.
3. **Return & Replace gardener:** must be an active AMC employee whose role is Gardener (as on the contract); one
   already on an entry may stay. File: `AmcReturnRequest`.

Tests: `tests/Feature/AmcReviewFixesTest.php` (3 tests). Whole suite: 1 skipped, 444 passed.
