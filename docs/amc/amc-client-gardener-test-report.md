# AMC Client Master: Gardener name dropdown (test report, 22/11/2026)

**Result: PASS, after one small fix.**

## What changed
On the AMC Client Master, "Gardener name" used to be a read-only box that showed the gardener from the client's Active contract. It is now a dropdown with search. It lists active AMC employees whose role is Gardener.

## What was tested (browser, http://localhost/nursery-app, signed in as admin)

| Check | Result |
|---|---|
| Add client: the dropdown lists only active Gardeners (Ganesh, ZZ Test Gardener). The Operation Manager and the Inactive Gardener are not listed. | Pass |
| Search picker: typing "zz" or the code "GD0" finds the right gardener. Searching the codes of the Operation Manager and the Inactive Gardener finds nothing. | Pass |
| Save a new client with a gardener; edit and change to another gardener; clear to None | Pass |
| Sending the Operation Manager or the Inactive Gardener by hand is refused with "Choose an active AMC employee whose role is Gardener." | Pass |
| History: "Gardener" shows before → after for each change (including → empty when cleared) | Pass |
| Client list: Gardener column shows the client's own gardener. If the client has none, it shows the Active contract's gardener (AROM TECHNO → Ganesh). | Pass |
| New contract: picking the customer fills in the client's gardener when the Gardener box is empty | Pass |
| New contract: a gardener you already chose is not replaced when you pick the customer | Pass |
| Return & Replace: customer with no Active contract → the client's gardener is filled in, with the note "gardener taken from the AMC Client Master" | Pass |
| Return & Replace: customer with an Active contract → the contract's gardener is still used, as before | Pass |
| An employee set as a client's gardener cannot be deleted ("used on 1 record(s) … Make it Inactive instead") | Pass |
| Phone width 375 px: client form and list fit the screen with no sideways scrolling; the picker opens as a bottom panel | Pass |
| Automated tests: `php artisan test --filter=Amc` | 53 passed |

## Bug found and fixed
1. **A client's gardener who was later made Inactive (or given another role) was still filled in on a new contract or Return & Replace.** Saving the form was then refused with "Choose an active AMC employee whose role is Gardener." Fix: the client details used to fill those pages now give the gardener only while they are an active Gardener (`AmcClientController::info`). The client keeps the gardener on its own page. New test: `AmcClientGardenerTest::test_client_info_offers_only_an_active_gardener`.

## Small tidy-ups
- The delete confirmation on the AMC Employee page now says that an employee on a **client**, a contract or a Return & Replace entry cannot be deleted.
- `AmcClient.php`: a code comment for the Active-contract relation had ended up above the new gardener relation. It is moved back to the right place.

## Notes / questions
- If you pick customer A on a new contract (gardener filled in), then switch to customer B, the gardener stays A's, because the box is no longer empty. This follows the "fill only when empty" rule. Should switching customers replace a gardener that was filled in automatically?
- Return & Replace: if the customer has an Active contract **with no gardener**, the client's gardener is not used. Should it be used in that case too?
- The gardener search uses the employee lookup. Only users who can view contracts, returns or employees can use it. A user who can only edit clients still sees the full list while there are only a few gardeners, but searching would be refused. Today's roles may never hit this.
- Found while testing but not part of this change: the Activity log page shows "(no longer exists)" for AMC client records that still exist. This is in the admin activity-log page, not in AMC, and was not changed.

## Throwaway data (database "nursery") — all removed
Created, then deleted:
- AMC employees: ZZ Test Gardener (AE0001), ZZ Test OpsManager (AE0002), ZZ Test Inactive Gardener (AE0003)
- AMC client: ZZ Test Client (with 1 location line, "ZZ Lobby")
- Activity log entries 206–212 (the add/edit records for the items above)

No contracts or Return & Replace entries were saved. The existing data (AROM TECHNO, its contract AMC-0009, Ganesh) was not changed.
