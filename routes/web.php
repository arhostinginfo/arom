<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AppPageController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\ConfigController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Admin\MasterController;
use App\Http\Controllers\Admin\PageSetupController;
use App\Http\Controllers\Crm\ContactController;
use App\Http\Controllers\Crm\CustomerPoController;
use App\Http\Controllers\Crm\DocumentController;
use App\Http\Controllers\Crm\EnquiryController;
use App\Http\Controllers\Crm\ProspectController;
use App\Http\Controllers\Crm\SiteVisitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\Crm\QuotationController;
use App\Http\Controllers\Reports\CrmReportController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\PartyController;
use App\Http\Controllers\Admin\SalesMasterController;
use App\Http\Controllers\Reports\SalesReportController;
use App\Http\Controllers\Sales\SalesInvoiceController;
use App\Http\Controllers\Sales\SalesOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::post('/unit', [UnitController::class, 'switch'])->name('unit.switch');

    // Landing page: blank, with the user's reports in the left menu (OTHER POINTS A17, decision S1).
    // Named-route redirect so it keeps the subfolder when served from XAMPP (/nursery-app).
    Route::get('/', fn () => redirect()->route('app.page', ['page' => 'reports']))->name('home');

    Route::prefix('admin')->name('admin.')->group(function () {
        // Clear config, route, view, event, application and permission caches from the browser (Admin only).
        Route::get('clear-cache', [MaintenanceController::class, 'clearCache'])->name('clear-cache');
        Route::get('users', [UserController::class, 'index'])->name('users.index')->middleware('permission:users.view');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create')->middleware('permission:users.add');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.add');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit')->middleware('permission:users.view');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.edit');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.delete');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index')->middleware('permission:access-rights.view');
        Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create')->middleware('permission:access-rights.add');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store')->middleware('permission:access-rights.add');
        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit')->middleware('permission:access-rights.view');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('permission:access-rights.edit');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('permission:access-rights.delete');

        // Masters, Page setup and Approval (CRM changes, Sat 13/10/2026).
        Route::get('masters', [MasterController::class, 'index'])->name('masters.index')->middleware('permission:masters.view');
        Route::get('masters/employees', [EmployeeController::class, 'index'])->name('employees.index')->middleware('permission:masters.view');
        Route::get('masters/employees/create', [EmployeeController::class, 'create'])->name('employees.create')->middleware('permission:masters.add');
        Route::post('masters/employees', [EmployeeController::class, 'store'])->name('employees.store')->middleware('permission:masters.add');
        Route::get('masters/employees/{employee}', [EmployeeController::class, 'edit'])->name('employees.edit')->middleware('permission:masters.view');
        Route::put('masters/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update')->middleware('permission:masters.edit');
        Route::delete('masters/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy')->middleware('permission:masters.delete');
        $lists = array_keys(\App\Services\MasterService::LISTS);
        Route::get('masters/{master}', [MasterController::class, 'show'])->name('masters.show')->whereIn('master', $lists)->middleware('permission:masters.view');
        Route::post('masters/{master}', [MasterController::class, 'store'])->name('masters.store')->whereIn('master', $lists)->middleware('permission:masters.add');
        Route::put('masters/{master}/{id}', [MasterController::class, 'update'])->name('masters.update')->whereIn('master', $lists)->whereNumber('id')->middleware('permission:masters.edit');
        Route::delete('masters/{master}/{id}', [MasterController::class, 'destroy'])->name('masters.destroy')->whereIn('master', $lists)->whereNumber('id')->middleware('permission:masters.delete');

        // Masters the Sales module needs (ADMIN tab, built 15/10/2026): Party, Item, Company and the shared master screen.
        Route::get('masters/parties', [PartyController::class, 'index'])->name('parties.index')->middleware('permission:masters.view');
        Route::get('masters/parties/create', [PartyController::class, 'create'])->name('parties.create')->middleware('permission:masters.add');
        Route::get('masters/parties/export', [PartyController::class, 'export'])->name('parties.export')->middleware(['permission:masters.view', 'throttle:downloads']);
        Route::get('masters/parties/import-template', [PartyController::class, 'template'])->name('parties.template')->middleware(['permission:masters.add', 'throttle:downloads']);
        Route::post('masters/parties/import', [PartyController::class, 'import'])->name('parties.import')->middleware(['permission:masters.add', 'throttle:downloads']);
        Route::post('masters/parties', [PartyController::class, 'store'])->name('parties.store')->middleware('permission:masters.add');
        Route::get('masters/parties/{party}', [PartyController::class, 'edit'])->name('parties.edit')->middleware('permission:masters.view');
        Route::put('masters/parties/{party}', [PartyController::class, 'update'])->name('parties.update')->middleware('permission:masters.edit');
        Route::delete('masters/parties/{party}', [PartyController::class, 'destroy'])->name('parties.destroy')->middleware('permission:masters.delete');
        Route::get('masters/items', [ItemController::class, 'index'])->name('items.index')->middleware('permission:masters.view');
        Route::get('masters/items/create', [ItemController::class, 'create'])->name('items.create')->middleware('permission:masters.add');
        Route::get('masters/items/export', [ItemController::class, 'export'])->name('items.export')->middleware(['permission:masters.view', 'throttle:downloads']);
        Route::get('masters/items/import-template', [ItemController::class, 'template'])->name('items.template')->middleware(['permission:masters.add', 'throttle:downloads']);
        Route::post('masters/items/import', [ItemController::class, 'import'])->name('items.import')->middleware(['permission:masters.add', 'throttle:downloads']);
        Route::get('masters/items/hsn-lookup', [ItemController::class, 'hsnLookup'])->name('items.hsn-lookup')->middleware('permission:masters.view');
        Route::post('masters/items', [ItemController::class, 'store'])->name('items.store')->middleware('permission:masters.add');
        Route::get('masters/items/{item}', [ItemController::class, 'edit'])->name('items.edit')->middleware('permission:masters.view');
        Route::put('masters/items/{item}', [ItemController::class, 'update'])->name('items.update')->middleware('permission:masters.edit');
        Route::delete('masters/items/{item}', [ItemController::class, 'destroy'])->name('items.destroy')->middleware('permission:masters.delete');
        Route::get('masters/company', [CompanyController::class, 'edit'])->name('company.edit')->middleware('permission:masters.view');
        Route::put('masters/company', [CompanyController::class, 'update'])->name('company.update')->middleware('permission:masters.edit');
        $salesMasters = array_keys(config('nursery.sales_masters'));
        Route::get('masters/lists/{master}', [SalesMasterController::class, 'show'])->name('sales-masters.show')->whereIn('master', $salesMasters)->middleware('permission:masters.view');
        Route::post('masters/lists/{master}', [SalesMasterController::class, 'store'])->name('sales-masters.store')->whereIn('master', $salesMasters)->middleware('permission:masters.add');
        Route::put('masters/lists/{master}/{id}', [SalesMasterController::class, 'update'])->name('sales-masters.update')->whereIn('master', $salesMasters)->whereNumber('id')->middleware('permission:masters.edit');
        Route::delete('masters/lists/{master}/{id}', [SalesMasterController::class, 'destroy'])->name('sales-masters.destroy')->whereIn('master', $salesMasters)->whereNumber('id')->middleware('permission:masters.delete');

        $pages = array_keys(config('nursery.page_fields'));
        Route::get('page-setup', [PageSetupController::class, 'index'])->name('page-setup.index')->middleware('permission:page-setup.view');
        // Switches for the optional parts of the CRM flow: site visit, BOQ, costing (Sat 14/10/2026).
        Route::put('page-setup-modules', [PageSetupController::class, 'updateModules'])->name('page-setup.modules')->middleware('permission:page-setup.edit');
        Route::get('page-setup/{page}', [PageSetupController::class, 'edit'])->name('page-setup.edit')->whereIn('page', $pages)->middleware('permission:page-setup.view');
        Route::put('page-setup/{page}', [PageSetupController::class, 'update'])->name('page-setup.update')->whereIn('page', $pages)->middleware('permission:page-setup.edit');

        Route::get('approval', [ApprovalController::class, 'edit'])->name('approval.edit')->middleware('permission:approval.view');
        Route::put('approval', [ApprovalController::class, 'update'])->name('approval.update')->middleware('permission:approval.edit');
        Route::put('approval/sales-order', [ApprovalController::class, 'updateSalesOrder'])->name('approval.sales-order')->middleware('permission:approval.edit');
        Route::put('approval/stock-transfer', [ApprovalController::class, 'updateStockTransfer'])->name('approval.stock-transfer')->middleware('permission:approval.edit');
        Route::put('approval/purchase-order', [ApprovalController::class, 'updatePurchaseOrder'])->name('approval.purchase-order')->middleware('permission:approval.edit');

        // Admin > Config: installation-wide settings, now the maximum upload size (22/10/2026).
        Route::get('config', [ConfigController::class, 'edit'])->name('config.edit')->middleware('permission:config.view');
        Route::put('config', [ConfigController::class, 'update'])->name('config.update')->middleware('permission:config.edit');

        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('activity.index')->middleware('permission:activity-log.view');
        Route::get('activity-log/{log}', [ActivityLogController::class, 'show'])->name('activity.show')->middleware('permission:activity-log.view');
    });

    // Type-ahead lists for big pickers, first 20 matches as JSON (decision A11).
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('customers', [LookupController::class, 'customers'])->name('customers')->middleware('permission:quotation.view|contact.view');
        Route::get('prospects', [LookupController::class, 'prospects'])->name('prospects')->middleware('permission:quotation.view|prospect.view|contact.view');
        Route::get('users', [LookupController::class, 'users'])->name('users')->middleware('permission:activity-log.view');
        Route::get('sales-people', [LookupController::class, 'salesPeople'])->name('sales-people')->middleware('permission:quotation.view|reports-crm.view');
        Route::get('employees', [LookupController::class, 'employees'])->name('employees')->middleware('permission:enquiry.view|site-visit.view|customer-po.view');
        Route::get('cities', [LookupController::class, 'cities'])->name('cities')->middleware('permission:enquiry.view');
        Route::get('login-users', [LookupController::class, 'loginUsers'])->name('login-users')->middleware('permission:masters.view');
    });

    // CRM (workbook CRM tab): Prospect and Quotation entry pages.
    Route::get('dashboard', DashboardController::class)->name('dashboard')->middleware('permission:dashboard.view');

    // Documents of enquiries, site visits, quotations, POs and sales orders. Outside the CRM switch (01/11/2026): sales
    // order documents work with CRM off; the controller refuses a record of a part that is switched off (a PO's with CRM,
    // 02/11/2026).
    Route::prefix('crm')->name('crm.')->group(function () {
        // Who may view, upload or delete is checked against the record's page by the controller.
        Route::post('documents/{type}/{id}', [DocumentController::class, 'store'])->name('documents.store')->whereIn('type', ['enquiry', 'site_visit', 'quotation', 'customer_po', 'sales_order'])->whereNumber('id')->middleware('throttle:uploads');
        Route::get('documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // CRM can be switched off as a whole (01/11/2026): its pages then say "CRM is switched off".
    Route::prefix('crm')->name('crm.')->middleware('module:crm')->group(function () {
        // Enquiry (lead), follow-ups, site visit, quotations and PO / agreement (CRM changes, Sat 13/10/2026).
        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index')->middleware('permission:enquiry.view');
        Route::get('enquiries/list', [EnquiryController::class, 'list'])->name('enquiries.list')->middleware('permission:enquiry.view');
        Route::get('enquiries/lookup', [EnquiryController::class, 'lookup'])->name('enquiries.lookup')->middleware('permission:enquiry.view|quotation.view|site-visit.view');
        Route::get('enquiries/client-info', [EnquiryController::class, 'clientInfo'])->name('enquiries.client-info')->middleware('permission:enquiry.add|enquiry.edit');
        Route::get('enquiries/create', [EnquiryController::class, 'create'])->name('enquiries.create')->middleware('permission:enquiry.add');
        Route::post('enquiries', [EnquiryController::class, 'store'])->name('enquiries.store')->middleware('permission:enquiry.add');
        Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show')->middleware('permission:enquiry.view');
        Route::put('enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('enquiries.update')->middleware('permission:enquiry.edit');
        Route::delete('enquiries/{enquiry}', [EnquiryController::class, 'destroy'])->name('enquiries.destroy')->middleware('permission:enquiry.delete');
        Route::post('enquiries/{enquiry}/activities', [EnquiryController::class, 'storeActivity'])->name('enquiries.activities.store')->middleware('permission:enquiry.edit');

        // Site Visit / Requirement Gathering is an optional part: refused while it is switched off.
        Route::middleware('module:site_visit')->group(function () {
            Route::get('site-visits', [SiteVisitController::class, 'index'])->name('site-visits.index')->middleware('permission:site-visit.view');
            Route::get('site-visits/list', [SiteVisitController::class, 'list'])->name('site-visits.list')->middleware('permission:site-visit.view');
            Route::get('site-visits/lookup', [SiteVisitController::class, 'lookup'])->name('site-visits.lookup')->middleware('permission:site-visit.view');
            Route::get('site-visits/create', [SiteVisitController::class, 'create'])->name('site-visits.create')->middleware('permission:site-visit.add');
            Route::post('site-visits', [SiteVisitController::class, 'store'])->name('site-visits.store')->middleware('permission:site-visit.add');
            Route::get('site-visits/{site_visit}', [SiteVisitController::class, 'show'])->name('site-visits.show')->middleware('permission:site-visit.view');
            Route::put('site-visits/{site_visit}', [SiteVisitController::class, 'update'])->name('site-visits.update')->middleware('permission:site-visit.edit');
            Route::delete('site-visits/{site_visit}', [SiteVisitController::class, 'destroy'])->name('site-visits.destroy')->middleware('permission:site-visit.delete');
        });

        // Contact / POC of a client (CRM changes Word file, built 14/10/2026).
        Route::get('contacts', [ContactController::class, 'index'])->name('contacts.index')->middleware('permission:contact.view');
        Route::get('contacts/create', [ContactController::class, 'create'])->name('contacts.create')->middleware('permission:contact.add');
        Route::post('contacts', [ContactController::class, 'store'])->name('contacts.store')->middleware('permission:contact.add');
        Route::get('contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show')->middleware('permission:contact.view');
        Route::put('contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update')->middleware('permission:contact.edit');
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy')->middleware('permission:contact.delete');


        Route::get('prospects', [ProspectController::class, 'index'])->name('prospects.index')->middleware('permission:prospect.view');
        Route::get('prospects/lookup', [ProspectController::class, 'lookup'])->name('prospects.lookup')->middleware('permission:prospect.view');
        Route::get('prospects/create', [ProspectController::class, 'create'])->name('prospects.create')->middleware('permission:prospect.add');
        Route::get('prospects/export', [ProspectController::class, 'export'])->name('prospects.export')->middleware(['permission:prospect.view', 'throttle:downloads']);
        Route::get('prospects/import-template', [ProspectController::class, 'template'])->name('prospects.template')->middleware(['permission:prospect.add', 'throttle:downloads']);
        Route::post('prospects/import', [ProspectController::class, 'import'])->name('prospects.import')->middleware(['permission:prospect.add', 'throttle:downloads']);
        Route::post('prospects/quick', [ProspectController::class, 'quickStore'])->name('prospects.quick')->middleware('permission:prospect.add');
        Route::post('prospects', [ProspectController::class, 'store'])->name('prospects.store')->middleware('permission:prospect.add');
        Route::get('prospects/{prospect}', [ProspectController::class, 'show'])->name('prospects.show')->middleware('permission:prospect.view');
        Route::put('prospects/{prospect}', [ProspectController::class, 'update'])->name('prospects.update')->middleware('permission:prospect.edit');
        Route::delete('prospects/{prospect}', [ProspectController::class, 'destroy'])->name('prospects.destroy')->middleware('permission:prospect.delete');

        Route::get('quotations', [QuotationController::class, 'index'])->name('quotations.index')->middleware('permission:quotation.view');
        Route::get('quotations/list', [QuotationController::class, 'list'])->name('quotations.list')->middleware('permission:quotation.view|quotation-approval.view|customer-po.view');
        Route::get('quotations/lookup', [QuotationController::class, 'lookup'])->name('quotations.lookup')->middleware('permission:quotation.view|customer-po.view');
        Route::get('quotations/create', [QuotationController::class, 'create'])->name('quotations.create')->middleware('permission:quotation.add');
        Route::get('quotations/export', [QuotationController::class, 'export'])->name('quotations.export')->middleware(['permission:quotation.view', 'throttle:downloads']);
        Route::get('quotations/import-template', [QuotationController::class, 'template'])->name('quotations.template')->middleware(['permission:quotation.add', 'throttle:downloads']);
        Route::post('quotations/import', [QuotationController::class, 'import'])->name('quotations.import')->middleware(['permission:quotation.add', 'throttle:downloads']);
        Route::post('quotations', [QuotationController::class, 'store'])->name('quotations.store')->middleware('permission:quotation.add');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show')->middleware('permission:quotation.view|quotation-approval.view');
        Route::get('quotations/{quotation}/pdf', [QuotationController::class, 'pdf'])->name('quotations.pdf')->middleware(['permission:quotation.view|quotation-approval.view', 'throttle:downloads']);
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update')->middleware('permission:quotation.edit');
        Route::post('quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise')->middleware('permission:quotation.edit');
        // Approval workflow and quotation status (CRM changes).
        Route::post('quotations/{quotation}/submit', [QuotationController::class, 'submit'])->name('quotations.submit')->middleware('permission:quotation.edit');
        Route::post('quotations/{quotation}/approve', [QuotationController::class, 'approve'])->name('quotations.approve')->middleware('permission:quotation-approval.edit');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject')->middleware('permission:quotation-approval.edit');
        Route::post('quotations/{quotation}/email', [QuotationController::class, 'email'])->name('quotations.email')->middleware(['permission:quotation.edit', 'throttle:downloads']);
        Route::post('quotations/{quotation}/sent', [QuotationController::class, 'markSent'])->name('quotations.sent')->middleware('permission:quotation.edit');
        Route::post('quotations/{quotation}/customer-status', [QuotationController::class, 'customerStatus'])->name('quotations.customer-status')->middleware('permission:quotation.edit');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy')->middleware('permission:quotation.delete');

        // CRM › 6 PO / Agreement, the CRM step, against a quotation sent to the customer. Separate PO menu removed
        // (02/11/2026): this is the only PO page again (no Customer picker route).
        Route::get('pos', [CustomerPoController::class, 'index'])->name('customer-pos.index')->middleware('permission:customer-po.view');
        Route::get('pos/list', [CustomerPoController::class, 'list'])->name('customer-pos.list')->middleware('permission:customer-po.view');
        Route::get('pos/export', [CustomerPoController::class, 'export'])->name('customer-pos.export')->middleware(['permission:customer-po.view', 'throttle:downloads']);
        Route::get('pos/lookup', [CustomerPoController::class, 'lookup'])->name('customer-pos.lookup')->middleware('permission:customer-po.view');
        Route::get('pos/items', [CustomerPoController::class, 'items'])->name('customer-pos.items')->middleware('permission:customer-po.view');
        Route::get('pos/create', [CustomerPoController::class, 'create'])->name('customer-pos.create')->middleware('permission:customer-po.add');
        Route::post('pos', [CustomerPoController::class, 'store'])->name('customer-pos.store')->middleware(['permission:customer-po.add', 'throttle:uploads']);
        Route::get('pos/{customer_po}', [CustomerPoController::class, 'show'])->name('customer-pos.show')->whereNumber('customer_po')->middleware('permission:customer-po.view');
        Route::put('pos/{customer_po}', [CustomerPoController::class, 'update'])->name('customer-pos.update')->whereNumber('customer_po')->middleware('permission:customer-po.edit');
        Route::delete('pos/{customer_po}', [CustomerPoController::class, 'destroy'])->name('customer-pos.destroy')->whereNumber('customer_po')->middleware('permission:customer-po.delete');
        // Create Sales Order from a PO (02/11/2026): needs Sales order · Add as well as PO / Agreement · View.
        Route::post('pos/{customer_po}/sales-order', [CustomerPoController::class, 'salesOrder'])->name('customer-pos.sales-order')->whereNumber('customer_po')->middleware(['permission:customer-po.view', 'permission:sales-order.add']);
    });

    // Separate PO menu removed (02/11/2026): old bookmarks of a PO page or New PO open CRM › 6 PO / Agreement (301, query
    // kept); every other /po address (the menu, PO list, PO register) no longer exists (404). No route names, so no po.*.
    Route::get('po/create', fn (\Illuminate\Http\Request $r) => redirect()->to(route('crm.customer-pos.create').($r->getQueryString() ? '?'.$r->getQueryString() : ''), 301));
    Route::get('po/{id}', fn (string $id) => redirect()->route('crm.customer-pos.show', (int) $id, 301))->whereNumber('id');

    // Sales (SALES tab, built 15/10/2026): tax invoice and sales order on one page (A24).
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('invoices', [SalesInvoiceController::class, 'index'])->name('invoices.index')->middleware('permission:tax-invoice.view');
        Route::get('invoices/list', [SalesInvoiceController::class, 'list'])->name('invoices.list')->middleware('permission:tax-invoice.view');
        Route::get('invoices/lookup', [SalesInvoiceController::class, 'lookup'])->name('invoices.lookup')->middleware('permission:tax-invoice.view');
        Route::get('invoices/create', [SalesInvoiceController::class, 'create'])->name('invoices.create')->middleware('permission:tax-invoice.add');
        Route::get('invoices/export', [SalesInvoiceController::class, 'export'])->name('invoices.export')->middleware(['permission:tax-invoice.view', 'throttle:downloads']);
        Route::post('invoices', [SalesInvoiceController::class, 'store'])->name('invoices.store')->middleware('permission:tax-invoice.add');
        Route::get('invoices/{invoice}', [SalesInvoiceController::class, 'show'])->name('invoices.show')->middleware('permission:tax-invoice.view');
        Route::get('invoices/{invoice}/pdf', [SalesInvoiceController::class, 'pdf'])->name('invoices.pdf')->middleware(['permission:tax-invoice.view', 'throttle:downloads']);
        Route::put('invoices/{invoice}', [SalesInvoiceController::class, 'update'])->name('invoices.update')->middleware('permission:tax-invoice.edit');
        // Delete = cancel: the number stays in the series (D16, C12).
        Route::post('invoices/{invoice}/cancel', [SalesInvoiceController::class, 'cancel'])->name('invoices.cancel')->middleware('permission:tax-invoice.delete');

        Route::get('orders', [SalesOrderController::class, 'index'])->name('orders.index')->middleware('permission:sales-order.view');
        Route::get('orders/list', [SalesOrderController::class, 'list'])->name('orders.list')->middleware('permission:sales-order.view|sales-order-approval.view');
        Route::get('orders/lookup', [SalesOrderController::class, 'lookup'])->name('orders.lookup')->middleware('permission:sales-order.view');
        Route::get('orders/create', [SalesOrderController::class, 'create'])->name('orders.create')->middleware('permission:sales-order.add');
        Route::get('orders/export', [SalesOrderController::class, 'export'])->name('orders.export')->middleware(['permission:sales-order.view', 'throttle:downloads']);
        Route::get('orders/import-template', [SalesOrderController::class, 'template'])->name('orders.template')->middleware(['permission:sales-order.add', 'throttle:downloads']);
        Route::post('orders/import', [SalesOrderController::class, 'import'])->name('orders.import')->middleware(['permission:sales-order.add', 'throttle:downloads']);
        Route::post('orders', [SalesOrderController::class, 'store'])->name('orders.store')->middleware('permission:sales-order.add');
        Route::get('orders/{order}', [SalesOrderController::class, 'show'])->name('orders.show')->middleware('permission:sales-order.view|sales-order-approval.view');
        Route::get('orders/{order}/po-lines', [SalesOrderController::class, 'poLines'])->name('orders.po-lines')->middleware('permission:tax-invoice.view');
        Route::put('orders/{order}', [SalesOrderController::class, 'update'])->name('orders.update')->middleware('permission:sales-order.edit');
        Route::delete('orders/{order}', [SalesOrderController::class, 'destroy'])->name('orders.destroy')->middleware('permission:sales-order.delete');
        Route::post('orders/{order}/submit', [SalesOrderController::class, 'submit'])->name('orders.submit')->middleware('permission:sales-order.edit');
        Route::post('orders/{order}/approve', [SalesOrderController::class, 'approve'])->name('orders.approve')->middleware('permission:sales-order-approval.edit');
        Route::post('orders/{order}/reject', [SalesOrderController::class, 'reject'])->name('orders.reject')->middleware('permission:sales-order-approval.edit');

        // Pickers of the Sales page and the Item master.
        Route::get('parties', [SalesInvoiceController::class, 'parties'])->name('parties.lookup')->middleware('permission:tax-invoice.view|sales-order.view|masters.view');
        Route::get('parties/{party}/info', [SalesInvoiceController::class, 'partyInfo'])->name('parties.info')->middleware('permission:tax-invoice.view');
        Route::get('items', [SalesInvoiceController::class, 'items'])->name('items.lookup')->middleware('permission:tax-invoice.view|sales-order.view');
    });

    // Sales reports: download panel on the same page (A21, S3).
    Route::prefix('reports/sales')->name('reports.sales.')->middleware('permission:reports-sales.view')->group(function () {
        Route::get('orders', [SalesReportController::class, 'panel'])->name('orders')->defaults('report', 'orders');
        Route::get('sales', [SalesReportController::class, 'panel'])->name('sales')->defaults('report', 'sales');
        Route::get('export', [SalesReportController::class, 'panel'])->name('export')->defaults('report', 'export');
        Route::get('credit-debit', [SalesReportController::class, 'panel'])->name('credit-debit')->defaults('report', 'credit-debit');
        Route::get('{report}/download', [SalesReportController::class, 'download'])->name('download')->whereIn('report', ['orders', 'sales', 'export', 'credit-debit'])->middleware('throttle:downloads');
    });

    // Stock report and Minimum stock report: one report listed under both Sales and Purchase (the Purchase menu opens it
    // with ?in=purchase), so either reports right opens it. Same addresses as before (Purchase reports, 27/10/2026).
    Route::prefix('reports/sales')->name('reports.sales.')->middleware('permission:reports-sales.view|reports-purchase.view')->group(function () {
        Route::get('stock', [SalesReportController::class, 'panel'])->name('stock')->defaults('report', 'stock');
        Route::get('stock/download', [SalesReportController::class, 'download'])->name('stock-download')->defaults('report', 'stock')->middleware('throttle:downloads');
    });

    // Purchase reports (27/10/2026): options, on-screen paged rows and CSV / PDF download on the same page (A21, S3).
    Route::prefix('reports/purchase')->name('reports.purchase.')->middleware('permission:reports-purchase.view')->group(function () {
        $pr = \App\Http\Controllers\Reports\PurchaseReportController::class;
        foreach (['po-summary', 'grn', 'credit-debit', 'stock-transfers', 'stock-ledger'] as $r) {
            Route::get($r, [$pr, 'panel'])->name($r)->defaults('report', $r);
        }
        Route::get('suppliers', [$pr, 'suppliers'])->name('suppliers');
        Route::get('items', [$pr, 'items'])->name('items');
        Route::get('{report}/download', [$pr, 'download'])->name('download')->whereIn('report', ['po-summary', 'grn', 'credit-debit', 'stock-transfers', 'stock-ledger'])->middleware('throttle:downloads');
    });

    // CRM reports: download panel on the same page (OTHER POINTS A21, decision S3).
    Route::prefix('reports/crm')->name('reports.crm.')->middleware(['module:crm', 'permission:reports-crm.view'])->group(function () {
        Route::get('prospects', [CrmReportController::class, 'prospects'])->name('prospects');
        Route::get('enquiries', [CrmReportController::class, 'enquiries'])->name('enquiries');
        Route::get('lead-ageing', [CrmReportController::class, 'leadAgeing'])->name('lead-ageing');
        Route::get('lead-report', [CrmReportController::class, 'leadReport'])->name('lead-report');
        Route::get('{report}/download', [CrmReportController::class, 'download'])->name('download')->whereIn('report', ['prospects', 'enquiries', 'lead-ageing', 'lead-report'])->middleware('throttle:downloads');
    });

    // Purchase › Stock transfer (27/10/2026). Stock moves only on Approve; Delete = a Draft or Rejected transfer only.
    Route::prefix('purchase')->name('purchase.')->group(function () {
        $st = \App\Http\Controllers\Purchase\StockTransferController::class;
        Route::get('stock-transfers', [$st, 'index'])->name('stock-transfers.index')->middleware('permission:stock-transfer.view|stock-transfer-approval.view');
        Route::get('stock-transfers/list', [$st, 'list'])->name('stock-transfers.list')->middleware('permission:stock-transfer.view|stock-transfer-approval.view');
        Route::get('stock-transfers/lookup', [$st, 'lookup'])->name('stock-transfers.lookup')->middleware('permission:stock-transfer.view|stock-transfer-approval.view');
        Route::get('stock-transfers/items', [$st, 'items'])->name('stock-transfers.items')->middleware('permission:stock-transfer.add|stock-transfer.edit');
        Route::get('stock-transfers/stock', [$st, 'stock'])->name('stock-transfers.stock')->middleware('permission:stock-transfer.view');
        Route::get('stock-transfers/create', [$st, 'create'])->name('stock-transfers.create')->middleware('permission:stock-transfer.add');
        Route::post('stock-transfers', [$st, 'store'])->name('stock-transfers.store')->middleware('permission:stock-transfer.add');
        Route::get('stock-transfers/{transfer}', [$st, 'show'])->name('stock-transfers.show')->middleware('permission:stock-transfer.view|stock-transfer-approval.view');
        Route::put('stock-transfers/{transfer}', [$st, 'update'])->name('stock-transfers.update')->middleware('permission:stock-transfer.edit');
        Route::delete('stock-transfers/{transfer}', [$st, 'destroy'])->name('stock-transfers.destroy')->middleware('permission:stock-transfer.delete');
        Route::post('stock-transfers/{transfer}/submit', [$st, 'submit'])->name('stock-transfers.submit')->middleware('permission:stock-transfer.edit');
        Route::post('stock-transfers/{transfer}/approve', [$st, 'approve'])->name('stock-transfers.approve')->middleware('permission:stock-transfer-approval.edit');
        Route::post('stock-transfers/{transfer}/reject', [$st, 'reject'])->name('stock-transfers.reject')->middleware('permission:stock-transfer-approval.edit');
        Route::post('stock-transfers/{transfer}/cancel', [$st, 'cancel'])->name('stock-transfers.cancel')->middleware('permission:stock-transfer.delete');
    });

    // Purchase › Purchase order (27/10/2026). Approvers without Purchase order View see it read-only with Approve / Reject.
    Route::prefix('purchase')->name('purchase.')->group(function () {
        $po = \App\Http\Controllers\Purchase\PurchaseOrderController::class;
        Route::get('orders', [$po, 'index'])->name('orders.index')->middleware('permission:purchase-order.view|purchase-order-approval.view');
        Route::get('orders/list', [$po, 'list'])->name('orders.list')->middleware('permission:purchase-order.view|purchase-order-approval.view');
        Route::get('orders/lookup', [$po, 'lookup'])->name('orders.lookup')->middleware('permission:purchase-order.view|purchase-order-approval.view');
        Route::get('orders/create', [$po, 'create'])->name('orders.create')->middleware('permission:purchase-order.add');
        Route::post('orders', [$po, 'store'])->name('orders.store')->middleware('permission:purchase-order.add');
        Route::get('orders/{order}', [$po, 'show'])->name('orders.show')->middleware('permission:purchase-order.view|purchase-order-approval.view');
        Route::get('orders/{order}/pdf', [$po, 'pdf'])->name('orders.pdf')->middleware(['permission:purchase-order.view|purchase-order-approval.view', 'throttle:downloads']);
        Route::put('orders/{order}', [$po, 'update'])->name('orders.update')->middleware('permission:purchase-order.edit');
        Route::delete('orders/{order}', [$po, 'destroy'])->name('orders.destroy')->middleware('permission:purchase-order.delete');
        Route::post('orders/{order}/submit', [$po, 'submit'])->name('orders.submit')->middleware('permission:purchase-order.edit');
        Route::post('orders/{order}/approve', [$po, 'approve'])->name('orders.approve')->middleware('permission:purchase-order-approval.edit');
        Route::post('orders/{order}/reject', [$po, 'reject'])->name('orders.reject')->middleware('permission:purchase-order-approval.edit');
        Route::post('orders/{order}/cancel', [$po, 'cancel'])->name('orders.cancel')->middleware('permission:purchase-order.delete');
        // Pickers of the Purchase order page: suppliers, a supplier's details, its purchase items.
        Route::get('parties', [$po, 'parties'])->name('parties.lookup')->middleware('permission:purchase-order.view');
        Route::get('parties/{party}/info', [$po, 'partyInfo'])->name('parties.info')->middleware('permission:purchase-order.view');
        Route::get('items', [$po, 'items'])->name('items.lookup')->middleware('permission:purchase-order.view');
    });

    // Purchase › GRN entry (27/10/2026). Quality approvers without GRN entry · View see it read-only with the quality bar.
    // Delete = a GRN pending match only (no number); Cancel = a matched GRN, reversing PO quantities and stock.
    Route::prefix('purchase')->name('purchase.')->group(function () {
        $grn = \App\Http\Controllers\Purchase\GrnController::class;
        $po = \App\Http\Controllers\Purchase\PurchaseOrderController::class;
        Route::get('grns', [$grn, 'index'])->name('grns.index')->middleware('permission:grn.view|grn-quality.view');
        Route::get('grns/list', [$grn, 'list'])->name('grns.list')->middleware('permission:grn.view|grn-quality.view');
        Route::get('grns/lookup', [$grn, 'lookup'])->name('grns.lookup')->middleware('permission:grn.view|grn-quality.view');
        // Pickers of the GRN page: open POs with pending lines, suppliers, a supplier's GST split, items, customers and their SOs.
        Route::get('grns/orders', [$grn, 'orders'])->name('grns.orders')->middleware('permission:grn.view');
        Route::get('grns/sales-orders', [$grn, 'salesOrders'])->name('grns.sales-orders')->middleware('permission:grn.view');
        Route::get('grns/parties', [$po, 'parties'])->name('grns.parties')->middleware('permission:grn.add|grn.edit');
        Route::get('grns/parties/{party}/info', [$grn, 'partyInfo'])->name('grns.party-info')->middleware('permission:grn.view');
        Route::get('grns/items', [$po, 'items'])->name('grns.items')->middleware('permission:grn.add|grn.edit');
        Route::get('grns/supplier-items', [$grn, 'supplierItems'])->name('grns.supplier-items')->middleware('permission:grn.add|grn.edit');
        Route::get('grns/customers', [\App\Http\Controllers\LookupController::class, 'customers'])->name('grns.customers')->middleware('permission:grn.add|grn.edit');
        Route::get('grns/create', [$grn, 'create'])->name('grns.create')->middleware('permission:grn.add');
        Route::post('grns', [$grn, 'store'])->name('grns.store')->middleware('permission:grn.add');
        Route::get('grns/{grn}', [$grn, 'show'])->name('grns.show')->middleware('permission:grn.view|grn-quality.view');
        Route::put('grns/{grn}', [$grn, 'update'])->name('grns.update')->middleware('permission:grn.edit');
        Route::delete('grns/{grn}', [$grn, 'destroy'])->name('grns.destroy')->middleware('permission:grn.delete');
        Route::post('grns/{grn}/quality', [$grn, 'quality'])->name('grns.quality')->middleware('permission:grn-quality.edit');
        Route::post('grns/{grn}/cancel', [$grn, 'cancel'])->name('grns.cancel')->middleware('permission:grn.delete');
    });

    // Sample screens from the prototype for modules not built yet.
    Route::get('/app/{page}/{arg?}', [AppPageController::class, 'show'])->name('app.page')
        ->where(['page' => '[a-z]+', 'arg' => '[^/]+']);
});
