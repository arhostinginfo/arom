# AMC masters grouping and employee photo preview: test report (22/11/2026)

Tested in the browser at http://localhost/nursery-app as the seeded admin and as a temporary user with limited rights,
at desktop width and at phone width (375 px). Automated tests: `php artisan test --filter=Amc`, 50 passed.

## Results

| Check | Result |
|---|---|
| AMC menu shows one "Masters" item, not the five master items | Pass |
| Masters page shows five cards (Business segment, Planter, Plant, AMC type, AMC Employee) with counts and working links | Pass |
| Tabs on each master page, on the employee list and on the employee add/edit page; current one marked | Pass |
| "Masters" menu item highlighted on every master page and employee page | Pass |
| User with only Plant view + Employee view: sees only those two cards and tabs; Planter page refused (403) | Pass |
| Phone width: cards stack one per row; tabs scroll sideways; no sideways scroll of the page | Pass |
| Phone width: the current tab is visible | **Failed, fixed** (see below) |
| Photo preview: JPG and PNG shown before saving | Pass |
| Photo preview: GIF refused, file over 5 MB refused; current photo comes back | Pass |
| Photo preview: clearing the file brings back the current photo | Pass |
| Edit an employee that has a photo, preview a new one, save: new photo stored, old file removed | Pass |
| A file named .jpg that is not really an image | **Showed a broken-image icon, fixed** |

## Problems found and fixed (AMC files only)

1. **Current tab hidden on phones.** On a phone the tabs are wider than the screen, so the tab of the page you are on
   (for example AMC type master or AMC Employee master) sat off-screen to the right and you could not see where you were.
   Fix in `public/js/amc.js`: when the page opens, the tab row is scrolled so the current tab is in view.
2. **Broken image in the preview.** Choosing a file named .jpg/.png that is not a real picture showed a broken-image
   icon. Fix in `public/js/amc.js`: if the picture cannot be shown, the current photo comes back (the server refuses
   such a file on save anyway).

`tests/Feature/AmcMastersGroupTest.php` now also checks that both fixes are in `amc.js`.

## Test data created and removed (dev database "nursery")

- AMC employee "ZZ Test Preview (delete me)" (id 6, code AE0001) with a test photo: created, photo replaced, then
  deleted through the app. Both photo files are gone from the AMC disk.
- User "ZZ AMC Tester" (id 6, zz.amctest@nursery.test) and role "ZZ AMC test (delete me)" (id 7): created, then
  permanently deleted.
- Left in place: three activity-log rows for the deleted employee (created / updated / deleted, ids 195, 196, 203),
  as they are the audit trail.

## Notes

- The "All masters" tab is never marked as current, since the Masters page itself has no tabs. That looks intended.
