# AMC module: browser re-test report (Sat 21/11/2026)

## Summary

**Result: PASS after one fix.** The four changes made after the first test round work in the browser. Phone photos
with a sideways tag are stored upright, uploads through the real file boxes on the contract page work, and users who are
not Admin see only the AMC parts their rights allow.

- **One bug found and fixed.** When a photo taken with the phone held sideways (or a landscape photo stored sideways) was
  put into a **collage** together with other images, it came out **squashed** (tall photo drawn into a wide box, or the
  other way round). Single photos and employee photos were already correct. Fixed in one AMC file, with a new automated test.
- **Automated tests:** whole suite **1 skipped, 447 passed** (before: 1 skipped, 445 passed; the 2 new tests are the only difference).
- **CRM and Sales:** nothing changed. A user's CRM and Sales menus are the same whether or not they have AMC rights.
- **Dev database "nursery":** not touched (checked before and after, see Clean-up).

## How it was tested

The "nursery" database was copied to a throwaway database, `nursery_amc_retest`, and the app was served from that copy
only (port 8003, database chosen for that one process; `.env` not changed). In the copy I made 5 test users (an Admin,
"AMC view only", "AMC add/edit, no import or delete", "no AMC rights", and "only Return & Replace + Employee view"), each
also with the normal Sales role's rights, plus sample masters, employees and 8 contracts ending on chosen dates (today,
in 20, 45, 60 and 61 days, in 200 days, 5 days overdue, and an Inactive one ending in 30 days). The test users and their
passwords existed only in the copy.

Phone-style photos were made for the test: JPEGs with an EXIF orientation tag 3 (upside down), 6 (turned 90° right),
8 (turned 90° left), one full-size 4000×3000 photo (6.3 MB, tag 6) and six 7.7 MB photos for the size limit. Each picture
has a red band at the top, a blue square bottom-left and a green square bottom-right, so a stored file can be checked
for "which way is up".

## 1. The four changes after the last test round

### a. Activity log names

| Step | Result | Note |
|---|---|---|
| Recent changes box on contract, employee and Return & Replace pages | Pass | e.g. "AMC file uploaded", "AMC updated", "AMC employee updated", "Return / replace added" |
| Admin › Activity log, after doing each kind of action in the browser | Pass | Shown: AMC master entry added / updated / deleted, AMC employee added / updated, AMC client added, AMC added, AMC updated, AMC file uploaded, AMC file deleted, Return / replace added / deleted, "Old deployment images deleted" (from the Admin clean-up button) |
| No code such as `amc_file.uploaded` visible anywhere | Pass | The codes appear only as hidden values of the filter list, where the visible names are plain words |
| Activity log filter list | Pass | Lists the AMC actions by their plain names |

### b. "Renewals due" tile

| Step | Result | Note |
|---|---|---|
| Tile count | Pass | 4: contracts ending today, in 20, 45 and 60 days. Not the one in 61 days, not the Inactive one, not the overdue one |
| Clicking the tile | Pass | Opens the AMC list with `?renewal=1`, showing exactly those 4 |
| Note above the list | Pass | "Renewals due · Active contracts ending in the next 60 days." |
| Search on the filtered list keeps the filter | Pass | The search form carries `renewal=1` along |
| Clear | Pass | Back to all 8 contracts, note gone, CSV link without the filter |
| CSV follows the filter | Pass | Download from the filtered list has the same 4 rows |
| Red colour filter | Pass | Still 30 days: only "today" and "in 20 days" |

### c. Return & Replace gardener

| Step | Result | Note |
|---|---|---|
| Gardener list on the page | Pass | Offers only the active Gardener |
| Operation Manager put into the gardener box (option added in devtools) and saved | Pass | Refused: "Choose an active AMC employee whose role is Gardener." Nothing saved |
| Inactive gardener put in the same way | Pass | Same message, nothing saved |
| Active gardener | Pass | Saved as RR-0003 |

Small observation (no change made): after the refusal, the page shows the refused person as an option in the gardener
box, so the user can see what was rejected; choosing the right gardener and saving works.

### d. Upload limit, 40 MB in total

| Step | Result | Note |
|---|---|---|
| Hint under each of the 4 upload boxes | Pass | "… max 10 MB each, 40 MB in total, up to 20 at a time." |
| Six photos of 7.7 MB (46 MB in all) | Pass | Refused in the page before sending: "The files of one upload can be at most 40 MB in total. Upload them in smaller groups." The box is emptied |
| 21 small photos | Pass | Refused in the page: "Choose at most 20 files at a time." |
| After a refusal, choosing 3 files | Pass | Accepted, no leftover message |
| Five photos of 7.7 MB (36.6 MB) | Pass | Uploaded, one collage of 5 images (1624×3233). Took about **39 seconds** on this PC |

## 2. Real phone-style photos

| Step | Result | Note |
|---|---|---|
| Deployment images, one photo with tag 6 | Pass | Stored 900×1200, upright, tag removed |
| Delivery challan images, one photo with tag 8 | Pass | Stored 900×1200, upright |
| Large photo 4000×3000, 6.3 MB, tag 6 | Pass | Stored 1200×1600, upright; about 3 seconds |
| Employee photo from the contract page (tag 6) | Pass | Stored 450×600, upright |
| Employee photo from the Employee master (tag 8) | Pass | Stored 450×600, upright |
| Employee photo over 5 MB | Pass | Refused in the page: "The file must not be larger than 5 MB." |
| **Collage** of tag-6, tag-3 and tag-8 photos (deployment), and tag-3 + sideways-landscape (challan) | **Fail, then fixed** | Each picture was the right way up, but the sideways ones were **squashed** into the wrong shape. After the fix, a collage of 4 such photos shows 3 portrait tiles and 1 landscape tile, all upright and in proportion |

## 3. Uploads through the browser's file boxes

The files were put into the page's real "Choose Files" boxes and sent with the page's own Upload buttons, the same way
as when a user picks them. (The Windows file-picker window itself cannot be driven by the test tools, so the picking
step is the only part not done by hand.)

| Step | Result | Note |
|---|---|---|
| Customer PO / Agreement: PDF | Pass | "Customer PO / Agreement: 1 file(s) saved" |
| Delivery challan documents: PDF | Pass | |
| Delivery challan images: single and collage | Pass | |
| Deployment images: single, collage, large photo | Pass | Thumbnails shown on the page |
| Download links | Pass | All 8 files open (200). Both PDFs are byte-for-byte the uploaded ones |
| Delete one file | Pass | Row and file removed, logged as "AMC file deleted" |
| Admin clean-up button (one deployment image dated 400 days ago in the copy) | Pass | Confirmation box, then "1 deployment image(s) older than 1 year deleted"; the newer ones and the PDFs kept |

## 4. Signing in as users who are not Admin

| Step | Result | Note |
|---|---|---|
| **AMC view only**: menu | Pass | AMC menu with all 10 pages |
| View only: pages | Pass | All list and detail pages open; contract, client and employee forms are greyed out; no Add, Save, Upload, Replace photo or Delete buttons |
| View only: direct addresses | Pass | Add pages, import template, every save, upload, delete, import, photo replace and clean-up give 403 |
| **AMC add/edit, no import/delete**: buttons | Pass | Add new and Save shown; Upload and Replace photo shown; no Delete anywhere; no Import |
| Add/edit: actions | Pass | Uploaded a challan PDF and added a Return & Replace; import, deleting a master, file, contract, employee, entry, and the clean-up all give 403 |
| **No AMC rights**: menu | Pass | No AMC menu; no AMC link anywhere on the page |
| No AMC rights: direct addresses | Pass | All 27 AMC pages, downloads, photos, files and pickers tried give 403 ("No access to this page"), and all 9 saves tried give 403 |
| **Only Return & Replace + Employee view** | Pass | AMC menu shows just "Return & Replace" and "AMC Employee master"; clicking AMC opens Return & Replace; the AMC dashboard, clients, contracts and masters give 403; the customer pickers on Return & Replace work |
| CRM and Sales menus | Pass | Identical for all four non-Admin users: CRM (Enquiry / Lead, Site visit, BOQ / costing / quotation, Quotation sent, PO / Agreement, Prospect, Contacts) and Sales (Tax Invoice, Sales Order); CRM and Sales pages open as before |

## Bugs found and fixed

1. **Sideways phone photos squashed in a collage.**
   File: `app/Services/Amc/AmcImages.php`, lines 37-41 (tile size) and 142-152 (new helper `angle()`; `upright()` at
   line 127 now uses it). The size of each collage tile was worked out from the photo as stored (sideways) but the photo
   was then turned upright, so width and height were swapped. Now the size is worked out from the upright shape.
   Backup: `C:\Users\ADMIN\AppData\Local\Temp\claude\e8593e4ece1b544f\1b9abdf8-b24e-4544-b984-62f55bc97d60\scratchpad\amc-retest-backup\app\Services\Amc\AmcImages.php`.
   Test: `tests/Feature/AmcRetestFixesTest.php` (2 tests: a collage of two sideways photos keeps their portrait shape
   and the right side up; a single tag-8 photo is stored upright). The collage test fails on the old code and passes now.

No shared, CRM or Sales file was changed.

## Clean-up

- Throwaway database `nursery_amc_retest`: **dropped**. Only "nursery" and the older "nursery_xl_t" remain.
- Files the run wrote under `storage/app/private/amc` (10 files: contract files and employee photos): **deleted**. The
  folder `storage/app/private` has exactly the same files as before the run.
- Generated photos, PDFs, test-user passwords and helper scripts in the scratch folder: **deleted**. `.env` and
  `storage/logs/laravel.log` unchanged.
- Both test web servers (ports 8003 and 8004) stopped. MySQL was already running and was left running; Apache not touched.
- **Dev database "nursery" untouched:** all AMC tables had 0 rows before and 0 after; users 5 and activity log 185
  before and after; a checksum of every table was identical before and after.

## Questions for you (no change made)

1. **Large collages are slow.** Five full-size phone photos (37 MB) took about 39 seconds to combine on this PC. On a
   slower shared server it may take longer, and the user sees only "Saving…" meanwhile. Fine as is, or suggest fewer
   photos per upload in the hint?
2. **Exactly at 40 MB.** The page allows up to 40 MB of files, and the server also allows 40 MB for the whole upload,
   which includes a few hundred bytes of form data. A choice of files totalling just under 40 MB could still hit the
   server's limit and show its general error page. It is a very narrow case. Setting the AMC limit slightly lower (for
   example 39 MB) in `config/amc.php` would close it. Change it?
3. **Server's own error for an oversized upload.** If the page's check is skipped (for example a very old cached copy of
   the page), an upload over 40 MB gets the server's plain "413" error page. This is server behaviour (shared), not AMC code.
4. **Employee photo kept in the browser for 5 minutes.** The photo is sent with "keep for 5 minutes, this browser only".
   On a shared PC, after one user signs out, the next user of the same browser could still see a photo already viewed,
   for up to 5 minutes, if they open that exact address. The server itself refuses them (checked). Acceptable?
5. **Mirrored selfies.** Photos tagged as mirrored (some front cameras, tags 2, 4, 5, 7) are not flipped back. These are
   rare for site photos. Leave as is?

## What is still not covered

- Picking files in the Windows file-picker window by hand (the files were placed in the same page boxes the picker fills).
- Photos straight from a real phone (the test photos had the same orientation tags a phone writes, but were made on the PC).
- Other browsers than the built-in one (Chromium); phone browsers.
- The live server (cPanel, GD, memory limit, cron line).
- Speed with very large data (thousands of clients or contracts).
