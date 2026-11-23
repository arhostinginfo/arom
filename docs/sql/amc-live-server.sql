-- =====================================================================================================================
-- AMC module: SQL for the LIVE server (MySQL / MariaDB), generated on 24/11/2026 from the Laravel migrations
-- (php artisan migrate --pretend), with the data back-fills written out.
--
-- EASIEST: on the server run   php artisan migrate --force   instead of this file. It runs exactly these steps and
-- records them. Use this file only if you cannot run artisan there (e.g. only phpMyAdmin).
--
-- BEFORE YOU START
--   1. Take a full backup of the live database.
--   2. See which AMC steps the live server already has:
--        SELECT migration, batch FROM migrations WHERE migration LIKE '2026_11_2%' ORDER BY migration;
--   3. Run ONLY the STEP sections below whose migration name is NOT in that result, in order (STEP 1 -> STEP 5).
--      Running a step twice fails ("table already exists" / "duplicate column"): skip the steps already there.
--   4. Each step ends by recording itself in `migrations`, so a later "php artisan migrate" will not run it again.
--
-- AFTER: clear the caches (permissions are cached):   php artisan optimize:clear   and   php artisan permission:cache-reset
--        (or open the app's clear-cache page). The AMC rights then show in Admin > Access rights.
-- Nothing outside the amc_* tables is changed, except: new rows in `permissions` and `migrations`.
-- =====================================================================================================================

SET NAMES utf8mb4;


-- ---------------------------------------------------------------------------------------------------------------------
-- STEP 1: 2026_11_21_000001_create_amc_tables
--         AMC module: masters, clients and their lines, contracts and files, Return & Replace, AMC employees; the AMC permissions
-- ---------------------------------------------------------------------------------------------------------------------
create table `amc_verticals` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(100) not null, `is_active` tinyint(1) not null default '1', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_verticals` add constraint `amc_verticals_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_verticals` add constraint `amc_verticals_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_verticals` add index `amc_verticals_is_active_name_index`(`is_active`, `name`);
alter table `amc_verticals` add unique `amc_verticals_name_unique`(`name`);
create table `amc_planters` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(100) not null, `is_active` tinyint(1) not null default '1', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_planters` add constraint `amc_planters_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_planters` add constraint `amc_planters_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_planters` add index `amc_planters_is_active_name_index`(`is_active`, `name`);
alter table `amc_planters` add unique `amc_planters_name_unique`(`name`);
create table `amc_plants` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(100) not null, `size` varchar(50) null, `is_active` tinyint(1) not null default '1', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_plants` add constraint `amc_plants_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_plants` add constraint `amc_plants_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_plants` add index `amc_plants_is_active_name_index`(`is_active`, `name`);
alter table `amc_plants` add unique `amc_plants_name_unique`(`name`);
create table `amc_types` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(100) not null, `is_active` tinyint(1) not null default '1', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_types` add constraint `amc_types_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_types` add constraint `amc_types_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_types` add index `amc_types_is_active_name_index`(`is_active`, `name`);
alter table `amc_types` add unique `amc_types_name_unique`(`name`);
create table `amc_employees` (`id` bigint unsigned not null auto_increment primary key, `code` varchar(20) not null, `name` varchar(100) not null, `role` enum('Operation Manager', 'Gardener', 'Other') not null, `mobile` varchar(10) null, `photo_path` varchar(255) null, `status` enum('Active', 'Inactive') not null default 'Active', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_employees` add constraint `amc_employees_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_employees` add constraint `amc_employees_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_employees` add index `amc_employees_status_role_name_index`(`status`, `role`, `name`);
alter table `amc_employees` add unique `amc_employees_code_unique`(`code`);
alter table `amc_employees` add unique `amc_employees_name_unique`(`name`);
create table `amc_clients` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(150) not null, `amc_vertical_id` bigint unsigned not null, `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_clients` add constraint `amc_clients_amc_vertical_id_foreign` foreign key (`amc_vertical_id`) references `amc_verticals` (`id`) on delete restrict;
alter table `amc_clients` add constraint `amc_clients_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_clients` add constraint `amc_clients_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_clients` add unique `amc_clients_name_unique`(`name`);
create table `amc_client_lines` (`id` bigint unsigned not null auto_increment primary key, `amc_client_id` bigint unsigned not null, `line_no` smallint unsigned not null, `location_1` varchar(150) null, `location_2` varchar(150) null, `amc_planter_id` bigint unsigned null, `planter_qty` int unsigned null, `amc_plant_id` bigint unsigned null, `plant_qty` int unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_client_lines` add constraint `amc_client_lines_amc_client_id_foreign` foreign key (`amc_client_id`) references `amc_clients` (`id`) on delete cascade;
alter table `amc_client_lines` add constraint `amc_client_lines_amc_planter_id_foreign` foreign key (`amc_planter_id`) references `amc_planters` (`id`) on delete restrict;
alter table `amc_client_lines` add constraint `amc_client_lines_amc_plant_id_foreign` foreign key (`amc_plant_id`) references `amc_plants` (`id`) on delete restrict;
alter table `amc_client_lines` add index `amc_client_lines_amc_client_id_line_no_index`(`amc_client_id`, `line_no`);
create table `amc_contracts` (`id` bigint unsigned not null auto_increment primary key, `amc_client_id` bigint unsigned not null, `amc_type_id` bigint unsigned not null, `start_date` date not null, `end_date` date not null, `billing_cycle` enum('Monthly', 'Quarterly', 'Half-yearly', 'Yearly') not null, `visit_plan` varchar(255) null, `amount` decimal(15, 2) not null, `operation_manager_id` bigint unsigned null, `gardener_id` bigint unsigned null, `status` enum('Active', 'Inactive') not null default 'Active', `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_contracts` add constraint `amc_contracts_amc_client_id_foreign` foreign key (`amc_client_id`) references `amc_clients` (`id`) on delete restrict;
alter table `amc_contracts` add constraint `amc_contracts_amc_type_id_foreign` foreign key (`amc_type_id`) references `amc_types` (`id`) on delete restrict;
alter table `amc_contracts` add constraint `amc_contracts_operation_manager_id_foreign` foreign key (`operation_manager_id`) references `amc_employees` (`id`) on delete restrict;
alter table `amc_contracts` add constraint `amc_contracts_gardener_id_foreign` foreign key (`gardener_id`) references `amc_employees` (`id`) on delete restrict;
alter table `amc_contracts` add constraint `amc_contracts_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_contracts` add constraint `amc_contracts_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_contracts` add index `amc_contracts_status_end_date_index`(`status`, `end_date`);
alter table `amc_contracts` add index `amc_contracts_amc_client_id_status_end_date_index`(`amc_client_id`, `status`, `end_date`);
alter table `amc_contracts` add index `amc_contracts_created_at_index`(`created_at`);
create table `amc_files` (`id` bigint unsigned not null auto_increment primary key, `amc_contract_id` bigint unsigned not null, `category` varchar(20) not null, `path` varchar(255) not null, `original_name` varchar(255) not null, `mime` varchar(100) null, `size` bigint unsigned not null default '0', `uploaded_by` bigint unsigned null, `created_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_files` add constraint `amc_files_amc_contract_id_foreign` foreign key (`amc_contract_id`) references `amc_contracts` (`id`) on delete cascade;
alter table `amc_files` add constraint `amc_files_uploaded_by_foreign` foreign key (`uploaded_by`) references `users` (`id`) on delete set null;
alter table `amc_files` add index `amc_files_amc_contract_id_category_index`(`amc_contract_id`, `category`);
alter table `amc_files` add index `amc_files_category_created_at_index`(`category`, `created_at`);
create table `amc_returns` (`id` bigint unsigned not null auto_increment primary key, `amc_client_id` bigint unsigned not null, `amc_client_line_id` bigint unsigned null, `location_1` varchar(150) null, `location_2` varchar(150) null, `request_date` date not null, `replace_date` date null, `reported_by_id` bigint unsigned null, `amc_type_id` bigint unsigned null, `amc_plant_id` bigint unsigned not null, `plant_size` varchar(50) null, `qty` int unsigned not null, `rate` decimal(15, 2) null, `gardener_id` bigint unsigned null, `return_remark` varchar(500) null, `quality` enum('Reusable', 'Needs Treatment', 'Repairable', 'Dead') not null, `created_by` bigint unsigned null, `updated_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_returns` add constraint `amc_returns_amc_client_id_foreign` foreign key (`amc_client_id`) references `amc_clients` (`id`) on delete restrict;
alter table `amc_returns` add constraint `amc_returns_amc_client_line_id_foreign` foreign key (`amc_client_line_id`) references `amc_client_lines` (`id`) on delete set null;
alter table `amc_returns` add constraint `amc_returns_reported_by_id_foreign` foreign key (`reported_by_id`) references `amc_employees` (`id`) on delete restrict;
alter table `amc_returns` add constraint `amc_returns_amc_type_id_foreign` foreign key (`amc_type_id`) references `amc_types` (`id`) on delete restrict;
alter table `amc_returns` add constraint `amc_returns_amc_plant_id_foreign` foreign key (`amc_plant_id`) references `amc_plants` (`id`) on delete restrict;
alter table `amc_returns` add constraint `amc_returns_gardener_id_foreign` foreign key (`gardener_id`) references `amc_employees` (`id`) on delete restrict;
alter table `amc_returns` add constraint `amc_returns_created_by_foreign` foreign key (`created_by`) references `users` (`id`) on delete set null;
alter table `amc_returns` add constraint `amc_returns_updated_by_foreign` foreign key (`updated_by`) references `users` (`id`) on delete set null;
alter table `amc_returns` add index `amc_returns_request_date_id_index`(`request_date`, `id`);
alter table `amc_returns` add index `amc_returns_amc_client_id_request_date_index`(`amc_client_id`, `request_date`);
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-dashboard.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-client.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-client.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-client.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-client-import.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-contract.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-contract.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-contract.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-contract.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-return.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-return.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-return.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-return.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-vertical.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-vertical.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-vertical.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-vertical.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-planter.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-planter.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-planter.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-planter.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-plant.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-plant.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-plant.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-plant.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-type.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-type.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-type.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-type.delete', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-employee.view', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-employee.add', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-employee.edit', now(), now());
insert ignore into `permissions` (`guard_name`, `name`, `updated_at`, `created_at`) values ('web', 'amc-employee.delete', now(), now());
insert into `migrations` (`migration`, `batch`) select '2026_11_21_000001_create_amc_tables', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- ---------------------------------------------------------------------------------------------------------------------
-- STEP 2: 2026_11_22_000001_add_gardener_to_amc_clients
--         Gardener on the AMC client
-- ---------------------------------------------------------------------------------------------------------------------
alter table `amc_clients` add `gardener_id` bigint unsigned null after `amc_vertical_id`;
alter table `amc_clients` add constraint `amc_clients_gardener_id_foreign` foreign key (`gardener_id`) references `amc_employees` (`id`) on delete set null;
insert into `migrations` (`migration`, `batch`) select '2026_11_22_000001_add_gardener_to_amc_clients', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- ---------------------------------------------------------------------------------------------------------------------
-- STEP 3: 2026_11_22_000002_add_party_to_amc_clients
--         AMC client linked to the Party master (Customer name dropdown)
-- ---------------------------------------------------------------------------------------------------------------------
alter table `amc_clients` add `party_id` bigint unsigned null after `id`;
alter table `amc_clients` add constraint `amc_clients_party_id_foreign` foreign key (`party_id`) references `parties` (`id`) on delete set null;
alter table `amc_clients` add unique `amc_clients_party_id_unique`(`party_id`);
-- Link each AMC client to the Party with the same name (case-insensitive); prefers an Active party, then the lowest id.
update `amc_clients` c join (select lower(p.name) n, substring_index(group_concat(p.id order by p.status = 'Active' desc, p.id), ',', 1) + 0 id from `parties` p where p.deleted_at is null group by lower(p.name)) x on x.n = lower(c.name) set c.party_id = x.id where c.party_id is null;
insert into `migrations` (`migration`, `batch`) select '2026_11_22_000002_add_party_to_amc_clients', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- ---------------------------------------------------------------------------------------------------------------------
-- STEP 4: 2026_11_23_000001_create_amc_return_items
--         Return & Replace rows (one per entry of the Location)
-- ---------------------------------------------------------------------------------------------------------------------
create table `amc_return_items` (`id` bigint unsigned not null auto_increment primary key, `amc_return_id` bigint unsigned not null, `line_no` smallint unsigned not null, `amc_client_line_id` bigint unsigned null, `location_2` varchar(150) null, `amc_planter_id` bigint unsigned null, `planter_qty` int unsigned null, `amc_plant_id` bigint unsigned null, `plant_size` varchar(50) null, `plant_qty` int unsigned null, `rate` decimal(15, 2) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `amc_return_items` add constraint `amc_return_items_amc_return_id_foreign` foreign key (`amc_return_id`) references `amc_returns` (`id`) on delete cascade;
alter table `amc_return_items` add constraint `amc_return_items_amc_client_line_id_foreign` foreign key (`amc_client_line_id`) references `amc_client_lines` (`id`) on delete set null;
alter table `amc_return_items` add constraint `amc_return_items_amc_planter_id_foreign` foreign key (`amc_planter_id`) references `amc_planters` (`id`) on delete restrict;
alter table `amc_return_items` add constraint `amc_return_items_amc_plant_id_foreign` foreign key (`amc_plant_id`) references `amc_plants` (`id`) on delete restrict;
alter table `amc_return_items` add index `amc_return_items_amc_return_id_line_no_index`(`amc_return_id`, `line_no`);
alter table `amc_returns` modify `amc_plant_id` bigint unsigned null;
alter table `amc_returns` modify `qty` int unsigned null;
-- Every existing Return & Replace entry becomes one row, so nothing is lost.
insert into `amc_return_items` (`amc_return_id`, `line_no`, `amc_client_line_id`, `location_2`, `amc_plant_id`, `plant_size`, `plant_qty`, `rate`, `created_at`, `updated_at`) select r.id, 1, r.amc_client_line_id, r.location_2, r.amc_plant_id, r.plant_size, r.qty, r.rate, now(), now() from `amc_returns` r where not exists (select 1 from `amc_return_items` i where i.amc_return_id = r.id);
insert into `migrations` (`migration`, `batch`) select '2026_11_23_000001_create_amc_return_items', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- ---------------------------------------------------------------------------------------------------------------------
-- STEP 5: 2026_11_23_000002_add_rate_and_row_quality_to_amc
--         Rate (Rs) in the Plant master; Quality on each Return & Replace row
-- ---------------------------------------------------------------------------------------------------------------------
alter table `amc_plants` add `rate` decimal(15, 2) null after `size`;
alter table `amc_return_items` add `quality` enum('Reusable', 'Needs Treatment', 'Repairable', 'Dead') null after `rate`;
alter table `amc_returns` modify `quality` enum('Reusable', 'Needs Treatment', 'Repairable', 'Dead') null;
-- Each existing row takes its entry's quality.
update `amc_return_items` i join `amc_returns` r on r.id = i.amc_return_id set i.quality = r.quality where i.quality is null and r.quality is not null;
insert into `migrations` (`migration`, `batch`) select '2026_11_23_000002_add_rate_and_row_quality_to_amc', coalesce(max(`batch`), 0) + 1 from `migrations`;

-- Check: should list all 5 AMC migrations.
SELECT migration, batch FROM migrations WHERE migration LIKE '2026_11_2%' ORDER BY migration;
