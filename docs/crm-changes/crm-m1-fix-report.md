# CRM end-to-end test: M1 fix

Fix made on 14/10/2026 for M1 in `crm-end-to-end-test-report.md`: once an employee linked to a login was deleted, that login could not be linked to another employee. Paths are relative to `D:\xampp\htdocs\nursery-app`.

## What changed

- `app/Services/MasterService.php`, `deleteEmployee()`: deleting an employee now clears its login link (`user_id`) in the same transaction, just before the soft delete. The `employee.deleted` activity log row is written first, so it still records which login the employee had. The unique rule (`EmployeeRequest`) and the unique key `employees_user_id_unique` stay as they are, so a login can still belong to only one current employee.
- No schema change and no migration, so no backup was needed. "nursery" has no deleted employee that still holds a login (checked: 0 rows), so no existing data needed fixing.

## Test

`tests/Feature/CrmFullTestFixesTest.php::test_m1_deleting_an_employee_frees_its_login_for_another_employee` follows the report's steps:
1. Add E004 linked to a login, then delete it. The deleted row has no login, and the log still names it.
2. Add E005 with the same login. It saves and is linked.
3. That login's follow-ups due now show only E005's leads, not the whole unit.

`php artisan test`: **96 passed, 1,142 assertions** (in-memory test database).
