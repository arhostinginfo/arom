# Running Nursery in production

On the development PC the app runs on XAMPP (Apache, PHP 8.2, MariaDB 10.4) with database sessions, cache and queue. Production should run as below. Nothing here has to be installed on the PC: the same code switches with `.env` values only (see the commented production block at the end of `.env.example`).

## Recommended set-up (decision A10)

| Part | Production choice | Why |
|---|---|---|
| Web server | Nginx + PHP-FPM 8.3 or 8.4 | Handles many users with less memory than Apache with mod_php |
| PHP | OPcache on (`opcache.enable=1`, `opcache.validate_timestamps=0`, reset on deploy) | Compiled code stays in memory, so pages are much faster |
| App servers | 2 or more behind a load balancer | One server can be updated or fail without downtime |
| Load balancer | Health check on `GET /up` (Laravel's built-in health route); terminates HTTPS | Sends traffic only to servers that are working |
| Sessions, cache, rate limits, permission cache | Redis (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`) | Shared by every app server; spatie's permission cache and the login and download limits use the default cache store |
| Queues | Redis (`QUEUE_CONNECTION=redis`) with a `php artisan queue:work` worker kept running by Supervisor or systemd | Long jobs run in the background |
| Scheduler | `* * * * * php artisan schedule:run` (cron) on one server | Runs `nursery:financial-years` daily at 00:10, which creates next year's financial year row ahead of 1 April |
| Database | MySQL 8.4 LTS (recommended); MariaDB 10.6+ also works | Long-term support; the schema uses InnoDB, utf8mb4 and real foreign keys |
| Backups | Daily `mysqldump` (or managed snapshots) kept off the server, restore tested monthly | |
| Error pages and logs | `APP_DEBUG=false`, `LOG_STACK=daily`, `LOG_LEVEL=warning` | No stack traces shown to users; logs rotate daily |
| HTTPS cookies | `SESSION_SECURE_COOKIE=true` | The session cookie is sent over HTTPS only |
| Proxies | `TRUSTED_PROXIES=<load balancer IPs or CIDR>` (`*` only behind a managed load balancer) | The activity log and rate limits see the real client IP and https |

Set Redis `maxmemory-policy volatile-lru`, so under memory pressure Redis drops keys that have a time limit (cached figures, Page setup, the optional-part switches) before sessions and other keys without one (architecture review S1 of Page setup).

Redis needs the `phpredis` PHP extension on the servers (`REDIS_CLIENT=phpredis`). If that extension cannot be installed, run `composer require predis/predis` and set `REDIS_CLIENT=predis`. Neither is needed on the development PC.

## Deploy steps

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

`db:seed` only adds what is missing (new permissions, currencies, the current financial year). After `config:cache`, settings are read from the cache, so change `.env` and run `config:cache` again to apply a change.

## CRM: documents, mail and queue (added 14/10/2026)

| Item | Setup |
|---|---|
| Uploaded documents | A private S3-compatible bucket with server-side encryption and versioning (or a daily backup). In `.env`: `DOCUMENTS_DISK=s3` and the `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` (and `AWS_ENDPOINT` for a non-AWS provider) values used by the `s3` disk in `config/filesystems.php`. Downloads redirect to a link valid for 5 minutes. The development PC keeps `DOCUMENTS_DISK=local` (files under `storage/app/private/documents`). Installing the `league/flysystem-aws-s3-v3` package is part of setting up the bucket. |
| Queue worker | Quotation emails are sent by the queued job `SendQuotationMail`. Run `php artisan queue:work --tries=3 --max-time=3600` under Supervisor or systemd (1 to 2 processes) and `php artisan queue:restart` on every deploy. Watch `failed_jobs`. |
| Mail | `MAIL_MAILER=smtp` (or a mail service's driver), a real `MAIL_FROM_ADDRESS` on the company's domain, with SPF and DKIM for that domain. The development PC keeps `MAIL_MAILER=log`, so mail goes to the log file. |
| Cache | The CRM bell and dashboard rely on the shared cache; use Redis in production (as above). |

## Limits built into the app

| Limit | Value | Decision |
|---|---|---|
| Sign-in attempts | 5 a minute per email and IP address | U2 |
| Downloads, PDFs and imports | 10 a minute per user | C10 |
| Rows in a report PDF | 2,000 (larger reports: use CSV, which is streamed) | C9 |
| Rows in an import file | 2,000, file up to 2 MB | C4 |
| Type-ahead lists (customer, prospect, entry picker, activity log user) | first 20 matches | A11 |

## Load test plan (summary)

Before go-live, test on a copy of production with realistic data (for example 50,000 prospects, 100,000 quotation revisions, 1 million activity log rows):

1. **Sign in and browse:** 100 users signing in and opening the CRM pages, users list and activity log for 15 minutes. Target: 95 % of pages under 500 ms, no errors.
2. **Saving:** 30 users saving quotations at the same time in the same unit. Target: no duplicate numbers, no deadlocks, saves under 1 s.
3. **Reports and downloads:** 20 users downloading a full-year CSV and PDF. Target: CSV memory stays flat, PDF over 2,000 rows refused with the message, other pages not slowed.
4. **Imports:** 5 users importing 2,000-row files. Target: done in under 30 s, all-or-nothing.
5. **Failure:** stop one app server and restart Redis during test 1. Target: the load balancer moves traffic, users stay signed in after Redis returns.

Watch PHP-FPM workers, MySQL slow query log (queries over 200 ms), Redis memory and the Laravel log during each test.
