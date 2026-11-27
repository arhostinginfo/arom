-- =====================================================================================================================
-- CRM changes of 26/11/2026: SQL for the LIVE server (MySQL / MariaDB, phpMyAdmin > SQL).
-- Generated from the Laravel migration (php artisan migrate --pretend).
--
-- Only ONE of today's changes touches the database:
--   * PO / Agreement: Target delivery date, Revised delivery date + reason, Delivered on (and the Dashboard's
--     Delivery overdue count / Pending deliveries page that read them).
-- The other changes need NO SQL (code only):
--   * Quotation: "Contact" -> "Contact Person", "Site" -> "Site Name" (labels only).
--   * Quotation: BOQ document upload beside Notes (uses the existing documents table).
--   * PO list: Upload Plant Deployment Details (uses the existing documents table).
--
-- BEFORE: take a full backup (phpMyAdmin > Export > Quick > SQL).
-- CHECK first whether it already ran (no row = run the step below):
--   SELECT migration FROM migrations WHERE migration = '2026_11_26_000001_add_delivery_dates_to_customer_pos';
-- Running it twice fails with "Duplicate column name": then it is already there, skip it.
-- AFTER: upload the code, then open  /admin/clear-cache  as an Admin.
-- Existing POs are unchanged: all the new fields are empty until someone fills them.
-- =====================================================================================================================

SET NAMES utf8mb4;

-- STEP 1: 2026_11_26_000001_add_delivery_dates_to_customer_pos
alter table `customer_pos` add `target_delivery_date` date null after `valid_to`;
alter table `customer_pos` add `revised_delivery_date` date null after `target_delivery_date`;
alter table `customer_pos` add `revised_reason` varchar(255) null after `revised_delivery_date`;
alter table `customer_pos` add `delivered_on` date null after `revised_reason`;
alter table `customer_pos` add index `customer_pos_unit_delivery_index`(`unit_id`, `delivered_on`, `target_delivery_date`);
insert into `migrations` (`migration`, `batch`) select '2026_11_26_000001_add_delivery_dates_to_customer_pos', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- Check: the four new columns
SHOW COLUMNS FROM `customer_pos` WHERE Field IN ('target_delivery_date', 'revised_delivery_date', 'revised_reason', 'delivered_on');
