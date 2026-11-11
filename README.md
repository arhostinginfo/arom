# Nursery app (Laravel 12 + MySQL)

Working modules of the Nursery business app: **admin login, users, roles, permissions and an activity log**, and **CRM (Prospect, Quotation and the CRM reports)**. The look is the Grove design from the prototype (left menu, colours, motion, phone layout). Pages of modules that are not built yet show the prototype's sample screens inside the real app, behind the same permissions.

## What works

| Area | What it does |
|---|---|
| Sign in / sign out | Login email + password, "keep me signed in", 5 tries per minute, inactive users blocked |
| Users list | Search by name, email or mobile (a mobile only when the text is a phone number), filter by role and status, server-side paging |
| Add / edit user | Name, Login email, Mobile, Password, Role, Units allowed, Status, plus extra permissions for that user |
| Access rights | Create, rename and delete roles; tick View / Add / Edit / Delete per page and which report groups the role sees |
| Activity log | Every sign-in, failed sign-in, sign-out and every user / role / permission change, with who, when, IP and before/after values |
| Left menu | Built from the signed-in user's permissions (a user only sees pages and reports they may open; other URLs return "No access") |
| CRM › Prospect | One prospect shown as a full form, with an Entry selector and search, Add new, Import CSV and Download CSV. Delete is blocked while a quotation uses the prospect |
| CRM › Quotation | Enquiry no, Quotation no and Rev no generated per unit and financial year (ENQ/26-27/0001, QT/26-27/0001). Customer or prospect ("Add" creates a prospect in a side drawer), sales person, dates, currency, followup status and item rows. Revise makes Rev + 1 and keeps the earlier revision read-only. Generate PDF, Import CSV, Download CSV |
| CRM reports | Prospects and Enquiries: filters and a download (CSV or PDF) on the same page |
| CRM activity log | Prospect added / updated / deleted / imported; quotation created / updated / revised / deleted / imported, with before and after values |

Default roles (decision D2): **Admin** (everything), **Sales**, **Purchase**, **Accounts**.

## Run it on Windows

You need **PHP 8.2 or newer**, **Composer** and **MySQL 8** (XAMPP / Laragon / WAMP or separate installs all work). In `php.ini` these extensions must be on: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` (and `zip` for Composer). MySQL 8 or the MariaDB 10.4+ that comes with XAMPP both work.

1. Unzip `nursery-app.zip`, open a terminal in the `nursery-app` folder.
2. Create an empty database in MySQL:
   ```sql
   CREATE DATABASE nursery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Install and configure:
   ```bat
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
4. Open `.env` and set `DB_USERNAME` and `DB_PASSWORD` for your MySQL (XAMPP default is `root` with an empty password). Optionally change `ADMIN_EMAIL` / `ADMIN_PASSWORD`.
5. Create the tables, permissions, roles, units and the first Admin:
   ```bat
   php artisan migrate --seed
   ```
6. Start it:
   ```bat
   php artisan serve
   ```
   Open http://127.0.0.1:8000 and sign in with **admin@nursery.local / Admin@12345** (or what you set in `.env`). Change that password from Users list after the first sign-in.

Run the automated tests with `php artisan test` (57 tests, all on in-memory SQLite: login, required fields, users, roles, permissions, protection rules, logging, and for CRM: prospects, quotation numbers, revisions, required fields, permissions, reports, imports and downloads).

PDFs are made with `barryvdh/laravel-dompdf`. It needs the `dom` and `mbstring` PHP extensions, which XAMPP has on. It does not need `gd`, because the PDFs have no images yet.

### Running from XAMPP's htdocs

Put the folder in `htdocs`, keep XAMPP's Apache and MySQL running, set `APP_URL=http://localhost/nursery-app` in `.env`, and open that address (no `/public`). The `.htaccess` and `index.php` in the app's root folder do this without changing XAMPP: files that exist in `public/` (CSS, JS) are served from there, every other request goes to Laravel through the root `index.php`, so nothing outside `public/` (`.env`, `vendor`, `storage`, `config`...) can be opened from the browser. Old `/nursery-app/public/...` addresses redirect (301) to the same page without `/public`; that rule is at the top of `public/.htaccess`. On a production server, point the web root at `public/` directly instead. `phpunit.xml` sets `APP_URL=http://localhost` for test runs so the tests are not affected by the sub-folder.

Architecture and database decisions (A1 to A7) are in `docs/ARCHITECTURE.md` and `docs/DATABASE.md`.

## Where things are

| File | Purpose |
|---|---|
| `config/nursery.php` | Pages, permissions, default roles, left menu, report lists, units |
| `docs/ARCHITECTURE.md` | How a request flows through the app, folders, architecture decisions A1 to A7 |
| `docs/DATABASE.md` | Every table built now (columns, keys, indexes) and the planned tables for all workbook modules |
| `app/Http/Controllers/Admin` | Users list, Access rights, Activity log (thin controllers) |
| `app/Http/Requests` | Validation and required fields for each form |
| `app/Services/UserService.php`, `RoleService.php` | The operations themselves, each in one database transaction with its log rows |
| `app/Support/ActivityLogger.php` | Writes one log row per operation |
| `app/Listeners/LogAuthActivity.php` | Logs sign-in, sign-out and failed sign-in |
| `app/Support/Menu.php` | Builds the left menu from permissions |
| `database/migrations`, `database/seeders/DatabaseSeeder.php` | Tables and first data |
| `resources/views` | Blade screens; `public/css/nursery.css` is the prototype's stylesheet, `public/css/app.css` the additions |
| `public/js/preview.js` | The prototype's sample screens for modules not built yet |
| `app/Http/Controllers/Crm`, `app/Http/Controllers/Reports` | Prospect and Quotation pages, CRM reports |
| `app/Http/Requests/Crm` | Required fields and checks for prospects, quotations and CSV imports |
| `app/Services/ProspectService.php`, `QuotationService.php`, `NumberSeries.php` | CRM operations with their log rows, and automatic document numbers |
| `resources/views/crm`, `resources/views/reports`, `resources/views/pdf` | CRM screens, report download panels, PDF layouts |
| `public/js/crm.js` | Entry selector, item rows, import dialog and the Add prospect drawer |

Roles and permissions use the `spatie/laravel-permission` package. Adding a page later means adding it to `config/nursery.php` and running `php artisan db:seed` again (it only adds what is missing).

## Decisions taken for this module (for the client to confirm)

- **U1 Required user fields:** Name, Login email (unique, used to sign in), Mobile (10 digit Indian number, unique), Password for new users (8+ characters with letters and numbers, typed twice), Role (one per user), Units allowed (at least one), Status (Active / Inactive). Extra permissions are optional.
- **U2 Passwords:** the Admin sets and resets passwords on the user form. No email reset, because the workbook has no email set-up.
- **U3 Extra permissions per user:** the role gives the base rights; an Admin can tick extra pages for one user. Rights from the role show ticked and locked.
- **U4 Safety rules:** the Admin role always has every right and cannot be renamed or deleted; a role still given to users cannot be deleted; the last active Admin cannot lose the Admin role or be made inactive; nobody can delete or deactivate their own user.
- **U5 Rights per page:** View, Add, Edit, Delete (D19). Settings pages use View and Edit; Party ledger, Activity log and report groups are view only. Ticking Add, Edit or Delete also gives View.
- **U6 Activity log:** records sign-in, sign-out, failed sign-in, user created / updated / password changed / role assigned / permissions changed / deleted, role created / renamed / permissions changed / deleted. Each row keeps who, when (India time), IP address, browser and the before/after values. The log is read-only and is kept when a user or role is deleted. It sits under Admin as "Activity log".
- **U8 Admin users:** only an Admin can give the Admin role, or change, reset the password of or delete an Admin user. Anyone else with Users list or Access rights rights can give only permissions they hold themselves, and cannot change their own role. An inactive Admin can be deleted; the last active Admin cannot.
- **U9 Rights a non-Admin can give:** someone who is not an Admin may give only a role whose rights they hold themselves, and only extra permissions they hold. They may change, reset the password of, deactivate or delete only users whose rights they hold themselves; any other user opens read-only. The Role list offers only the roles they may give.
- **U10 Built-in roles:** Admin and Sales keep their names and cannot be deleted, because Admin gives every right and quotations, quotation import and the CRM reports pick their sales person from the Sales role. Both are refused on the server with a message, and the Sales role screen shows its name read-only with no Delete button. Sales' permissions can still be changed. The code refers to both roles through `App\Support\Roles`, not the typed names.
- **U7 Deleting a user:** the user is soft deleted. They can no longer sign in and disappear from the list, but records and log rows that point at them stay intact, and their email and mobile cannot be reused.

## Decisions taken for the CRM module (for the client to confirm)

- **C1 Item no on quotations:** the Item master is not built yet, so Item no is typed text and optional. Each item row needs an Item description, so a new part can be quoted. When the Item master is built, Item no becomes a pick list and Rate defaults to the item price (D14).
- **C2 Customer list:** Customer is picked from the Party master (active parties of type Customer or Both). That master's screen is not built yet, so the list is empty and a quotation is made for a prospect for now. A quotation needs a customer or a prospect (S4).
- **C3 Delete rules:** prospects and quotations are soft deleted, so the activity log and reports keep their history. A prospect used on a quotation cannot be deleted. Deleting a quotation deletes all its revisions, and is offered and allowed only on the current revision. Only the current revision can be changed, revised or deleted; earlier ones are read-only (D24).
- **C4 Import and Download CSV:** downloads are UTF-8 CSV files; imports also accept Excel's plain "CSV (Comma delimited)" files, which are converted from Windows-1252. Text that a spreadsheet would run as a formula (starting with =, @, +, - or a tab) is written with a leading apostrophe and read back without it. Both use CSV files with the columns shown in the template on each page. Import always adds new records and checks the whole file first: if any row is wrong, nothing is saved and the rows to fix are listed. For quotations, rows with the same **Group** value are the items of one quotation, its header is read from the group's first row, the sales person is given by login email, and each quotation gets new numbers in the unit chosen on the top bar. Download CSV for quotations has one row per item of the current revision, for the chosen unit and the current financial year.
- **C5 Report filters:** From date and To date (default the current financial year), Unit (one of your units, or all of them), plus Domestic / Export for Prospects, and Followup status and Sales person for Enquiries. Prospects are filtered by the date they were added, and Enquiries by enquiry receipt date. Enquiries lists the current revision of each quotation. Formats are CSV and PDF (S3).
- **C6 Prospects across units:** prospects are shared by all units, like masters (OTHER POINTS A22). Each prospect remembers the unit it was added in, for the Prospects report's Unit filter. Quotations belong to the unit chosen on the top bar and to the current financial year. Numbers run per unit and financial year (D16), and the Entry list shows that unit's quotations for that year.
- **C7 Sales person:** chosen from active users with the Sales role (D22). Add at least one such user in Users list before making quotations.
- **C9 Report PDF size:** a report PDF holds up to 2,000 rows; above that the page asks for CSV or narrower dates.
- **C10 Download limit:** downloads, PDFs and imports are limited to 10 a minute per user.
- **C11 Customer or prospect:** a quotation has a customer or a prospect, not both, entered in one "Customer or prospect" field group.
- **C12 Number gaps:** an import reserves its quotation and enquiry numbers in one step; if the import then fails, those numbers stay unused.
- **C13 CSV order:** Download CSV lists rows in the order they were added; Excel can sort them.
- **UX2 Entry picker:** the Entry selector is one search field; the latest 20 entries show by default and typing searches the number, party, project or a dd/mm/yyyy date.
- **C8 Quotation PDF:** shows the numbers, the customer or prospect, the header fields and the item rows, as text only. The company name, address and logo will be added when the Company master is built.
