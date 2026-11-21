# AMC module: test report (Sat 21/11/2026)

## Summary

**Result: PASS.** The AMC module does what the client asked. Two full sample entries (Smart Work and Rich Next) went through every AMC screen with no errors. **No bugs were found that needed a code change**, so no AMC file was edited. CRM and Sales are unaffected.

- Automated tests: a new file, `tests/Feature/AmcEndToEndTest.php`, has **9 tests (537 checks), all passing**.
- Whole test suite: **1 skipped, 441 passed** (before: 1 skipped, 432 passed; the 9 new tests are the only difference).
- Browser check: done on a throwaway copy of the development database. The live and development data were never changed.
- Shared files: only the two approved one-line changes are present.
- There are a few points for you to decide, and one cosmetic issue that would need a change to a shared file (see below).

## How it was tested

1. **Automated tests.** These run on a temporary in-memory database that is created and thrown away for each test. They do not touch the dev database "nursery".
2. **Browser run.** The "nursery" database was copied to a throwaway database, `nursery_amc_check`, and the app was served from that copy only (port 8002). I signed in with the seed admin account, added a business segment through the screen, and loaded the sample data into the copy. Then I checked the parts that run in the browser: the customer details appearing on the contract page, the overdue pop-up, the collage thumbnails, the Return & Replace auto-fill, and the phone-width layout. Afterwards the server was stopped, the copy was **dropped**, and the 2 test images it wrote to `storage/app/private/amc` were **deleted**. A check afterwards confirmed the dev database "nursery" still has 0 AMC rows. MySQL was already running, so it was left running.

## Results by area

### 1. Masters (Business segment, Planter, Plant, AMC type, AMC Employee)

| Check | Result | Note |
|---|---|---|
| Add each master through its screen | Pass | AMC, AMC + Rental, Ceramic round 12 inch, Money Plant (2 ft), Rose (5 ft), 2 AMC types |
| Edit (rename, change size, make inactive and active again) | Pass | |
| Duplicate name refused, any upper/lower case and extra spaces | Pass | e.g. "amc + rental", "CERAMIC ROUND 12 INCH", " Indoor   maintenance " |
| Renaming onto another existing name refused | Pass | |
| An entry in use cannot be deleted; an unused one can | Pass | Checked against clients, contracts and Return & Replace |
| Employee code left blank gets AE0001, AE0002 … | Pass | |
| Employee role from the list only; mobile 10 digits; duplicate name or code refused | Pass | |
| Employee photo upload | Pass | A 2000×1500 photo is saved as 600×450 |
| Photo replace: the old file is deleted | Pass | From the Employee master and from the contract page |
| A text file named .jpg is refused as a photo | Pass | The current photo is kept |
| Employee in use on a contract cannot be deleted | Pass | |

### 2. AMC Client Master

| Check | Result | Note |
|---|---|---|
| Smart Work: AMC + Rental, Tower A / Floor 5 - Lobby, planter 10, Money Plant 10; second line Rose 20 | Pass | |
| Rich Next: AMC, ASTP, Rose 10 | Pass | |
| Location 1 and Location 2 are two separate boxes | Pass | Saved, shown and exported separately |
| Empty line ignored; quantity must be a whole number | Pass | |
| Same customer name in another case refused | Pass | |
| Edit keeps the existing lines (same record numbers) | Pass | So Return & Replace entries stay linked |
| Search and business-segment filter | Pass | |
| CSV template | Pass | Opens in Excel (UTF-8), same columns as the import |
| Import of a good file (the template itself) | Pass | 2 clients; Smart Work gets 2 lines |
| Import of a file with an error on the last row | Pass | **Nothing saved**; the message names "Row 4" and the problem (unknown plant "Tulip", quantity "ten") |
| Importing the same file again | Pass | Refused as the customers already exist; nothing doubled |
| CSV download | Pass | One row per line, the same layout as the import |
| Gardener shown read-only from the Active contract | Pass | Empty before a contract exists; Rich Next shows the gardener of the Active contract that ends last |

### 3. Customer PO / AMC

| Check | Result | Note |
|---|---|---|
| One contract for each client | Pass | |
| Customer details appear when a customer is picked | Pass | Checked in the browser: segment, active contract and the locations/planters/plants table |
| Dates are dd/mm/yyyy and come before AMC type | Pass | "2026-04-01" and "31/02/2027" refused |
| End date before the start date refused; the same day allowed | Pass | Message: "The end date must be on or after the start date." |
| Billing cycle dropdown | Pass | Exactly Monthly / Quarterly / Half-yearly / Yearly; any other value refused |
| AMC amount | Pass | "12,000" accepted; "abc", "-5" and "10.555" refused |
| Operation manager / Gardener limited to their role | Pass | Dropdowns list only active people of that role; a gardener in the manager box, a manager in the gardener box and an inactive gardener are all refused |
| PO / Agreement upload and download | Pass | The downloaded PDF is byte-for-byte the uploaded one |
| Wrong file types refused | Pass | A .txt as the PO, an image in the challan documents box, a text file renamed .pdf |
| Delivery challan PDF | Pass | |
| Delivery challan images: 2 at once become one JPEG collage | Pass | 1624×656 px (2 columns of up to 800 px with 8 px gaps), opens correctly; thumbnail shown in the browser |
| Deployment: one image resized | Pass | 3000×2000 becomes 1600×1067 |
| Deployment: 3 images at once | Pass | One collage, 2 columns and 2 rows, 1624×1571 px |
| Photo replace for manager and gardener on the contract page | Pass | Returns to the contract; the old file is deleted |
| Delete one file / delete a contract | Pass | The rows and the files on disk are both removed |

### 4. Alerts and AMC list

| Check | Result | Note |
|---|---|---|
| Overdue Active contract: red pop-up | Pass | Checked in the browser: "AMC overdue … 21 day(s) ago", with New contract and Close buttons, plus a red bar on the page |
| Ends within 60 days: renewal banner | Pass | "Renewal due … in 24 day(s)"; no pop-up |
| No alert far from the end, or when Inactive | Pass | |
| Colour boundaries | Pass | Overdue by 1 day = Black; today, or 30 days left = Red; 31 or more = Green; Inactive = Grey. Renewal banner up to 60 days, not at 61 |
| Legend on the list | Pass | All four colours explained |
| Filters (colour, status, customer, AMC number) | Pass | Each colour filter shows exactly its contract |
| CSV | Pass | Includes the colour and days left (e.g. 130, 24, −21); follows the filters |

### 5. Dashboard (figures checked by hand, date 21/11/2026, FY Apr 2026 to Mar 2027)

Contracts used:
- Smart Work: Rs 12,000, Apr 2026 to Mar 2027 (12 months), so Rs 1,000 a month.
- Rich Next: Rs 13,000, 15 Dec 2025 to 15 Dec 2026 (13 calendar months), so Rs 1,000 a month.
- Rich Next (old): Rs 600, May to Oct 2026, so Rs 100 a month. Overdue.
- Smart Work (old): Inactive, so left out.

| Figure | Expected by hand | App | Result |
|---|---|---|---|
| Total active clients | 2 (the overdue contract does not count) | 2 | Pass |
| Revenue November 2026 | 1,000 + 1,000 = Rs 2,000 | Rs 2,000 | Pass |
| April 2026 | 2,000 | 2,000 | Pass |
| May to October 2026 | 2,100 each | 2,100 | Pass |
| December 2026 | 2,000 | 2,000 | Pass |
| January to March 2027 | 1,000 each | 1,000 | Pass |
| Year total | 12,000 + 9 × 1,000 + 600 = 21,600 | 21,600.00 | Pass |
| Renewals in the next 60 days | 1 (Rich Next, 24 days) | 1 | Pass |
| Overdue | 1 | 1 | Pass |
| New orders, last 60 days | 2 (the two September contracts are 81 days old) | 2 | Pass |

### 6. Return & Replace

| Check | Result | Note |
|---|---|---|
| Picking the customer fills its locations | Pass | Checked in the browser: "Tower A · Floor 5 - Lobby", "Tower A · Floor 6 - Cafeteria" |
| Picking a line fills Location 1 and Location 2 | Pass | Tower A / Floor 5 - Lobby |
| AMC type and gardener from the Active contract | Pass | Indoor maintenance / Gopal Gardener, with a note naming the contract |
| Plant size from the Plant master | Pass | Rose gives 5 ft; saved on the entry (2 ft for Money Plant) |
| Quality dropdown, exact options | Pass | Reusable / Needs Treatment / Repairable / Dead; "Bad" refused |
| Location of another customer refused; replace date before the request date refused; quantity 0 refused | Pass | |
| Two entries saved (RR-0001 Smart Work, RR-0002 Rich Next), one edited | Pass | |
| List search, quality filter, CSV | Pass | |

### 7. Clean-up of old deployment images

| Check | Result | Note |
|---|---|---|
| `php artisan amc:purge-old-images` | Pass | Deleted the deployment images 400 and 366 days old (file and record); kept one 300 days old |
| PO, challan image and challan document files 800 days old are kept | Pass | |
| On the schedule | Pass | `php artisan schedule:list` shows `30 1 * * * php artisan amc:purge-old-images` (01:30 daily) |
| Admin button | Pass | Shown to Admin on the AMC list and the contract page, and works |
| Non-Admin (even with every AMC right) | Pass | Button not shown; a direct request is refused (403) |

### 8. Rights

| Check | Result | Note |
|---|---|---|
| A user without AMC rights | Pass | No AMC menu; **every one of the 50+ AMC addresses** (pages, saves, deletes, files, photos, lookups, CSVs) gives 403 |
| View only | Pass | Can open the pages; no Save / Upload / Delete buttons; each save, upload and delete is refused |
| Add without Edit, Edit without Delete, Delete | Pass | Each right allows only its own action (checked on masters, clients, contracts, files, Return & Replace, employees) |
| Import right | Pass | Import is refused without "AMC Client Master · Import" and works with it |
| Access rights page | Pass | All 10 AMC rows are listed |

### 9. CRM and Sales unaffected

| Check | Result | Note |
|---|---|---|
| Menu | Pass | Dashboard, CRM, Sales, Purchase, Accounts, **AMC**, Admin, Reports, in that order |
| CRM and Sales pages open (enquiries, prospects, quotations, customer POs, sales orders, invoices, Access rights) | Pass | In the tests and on the browser copy |
| AMC style and script not loaded on CRM / Sales pages | Pass | |
| Existing CRM / Sales tests | Pass | All still pass (full suite) |

### 10. Phone width (375 px)

| Check | Result | Note |
|---|---|---|
| 18 AMC screens at 375 px wide | Pass | None scrolls sideways: dashboard, client list/new/edit, contract new/three contracts, AMC list, Return & Replace list/new, employee list/new/edit, and the 4 masters. Lists show as cards |

## Bugs found and fixed

None. No AMC file needed a change, so no backup was made.

## Shared-file check (git status / git diff)

**OK.** The only changed tracked files are the two approved ones:
- `bootstrap/providers.php`: 1 line added (`App\Providers\AmcServiceProvider::class,`).
- `tests/Feature/MenuBarTest.php`: 1 line, with `'amc'` added to the list of menus.

Everything else is new and untracked (the AMC files and these two reports). No CRM or Sales code, view, script, style, table or data was changed.

Two untracked items were not made by the AMC work and are older:
- an empty file named `git` in the project folder, dated 11/11/2026;
- `docs/sql/empty-crm-sales-data.sql`, dated 14/11/2026, a script that empties CRM and Sales data.

Please make sure neither is uploaded to the live server by mistake. The second would delete the live CRM and Sales data if someone ran it.

## Points for you to decide (no change made)

1. **Activity log names.** In "Recent changes" and Admin › Activity log, AMC actions show as raw codes such as "amc_file.uploaded" instead of plain words such as "File uploaded". The list of plain names lives in a shared file (`app/Models/ActivityLog.php`), so I did not change it. Adding the AMC names there is a small change, but it touches a shared file and needs your approval.
2. **"Renewals due" tile.** The tile counts contracts ending in the next **60** days. Clicking it opens the AMC list filtered to **Red** (30 days or less), so a contract with 31 to 60 days left is counted but not shown in that list. The Renewal alerts box on the dashboard does show all of them. Do you want a "next 60 days" filter on the list?
3. **Server memory for collages.** Combining 20 full-size phone photos (12 MP each) at once used about 210 MB of memory. It works on this PC (512 MB limit). On a server with a 128 MB limit it fails with an error page, and nothing is saved. On the live server, please check that the PHP memory limit is at least 256 MB, or ask users to upload fewer photos at a time.
4. **Upload total size.** Each box allows up to 20 files of 10 MB each, but the server accepts at most 40 MB per upload in total (and the same may apply on the live server). Choosing many large files at once is refused by the server with a general error rather than a clear message. You could lower the limits shown on screen, or raise the server limit.
5. **Gardener on Return & Replace.** The gardener picker suggests gardeners only, but saving accepts any AMC employee (on the contract page it must be a Gardener). Keep it as it is, or limit it to the Gardener role?
6. **Photo replace from the contract page** changes that person's photo everywhere (it is the Employee master photo). A user with only the contract Edit right can do this, which matches the requirement. Please confirm that is what you want.
7. **Side menu.** On AMC pages the left side menu says "No reports for this menu." This is how the shared layout treats a menu with no reports. It is harmless.

## What was not covered

- Real phone photos with a sideways orientation tag (the code turns them upright, but I tested only with generated images).
- Uploading files by clicking through the browser's file chooser. Uploads were tested in the automated tests and by a script on the throwaway copy, and the results were viewed in the browser.
- Sign-in as a non-Admin user in the browser. Rights were checked in the automated tests.
- The live server (cPanel, GD extension, the cron line). These are deployment steps in the developer's report.
- Speed with very large data (thousands of clients or contracts).
