<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovalRuleRequest;
use App\Http\Requests\Admin\SalesOrderApprovalRuleRequest;
use App\Models\ApprovalRule;
use App\Models\User;
use App\Services\ApprovalSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

/** Admin > Approval (CRM changes, Sat 13/10/2026; Sales 15/10/2026): the quotation and sales order approval rules (D3). */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalSettingsService $settings)
    {
    }

    public function edit(): View
    {
        // Who can approve: roles holding Quotation (or Sales order) approval · Edit; Admin always can.
        $roles = fn (string $name) => ($perm = Permission::where('name', $name)->first()) ? $perm->roles()->pluck('name')->prepend('Admin')->unique()->values() : collect(['Admin']);

        return view('admin.approval.edit', [
            'rule' => ApprovalRule::for('quotation'),
            'approverRoles' => $roles('quotation-approval.edit'),
            'soRule' => ApprovalRule::for('sales_order'),
            'soApproverRoles' => $roles('sales-order-approval.edit'),
            'stRule' => ApprovalRule::for('stock_transfer'),
            'stApproverRoles' => $roles('stock-transfer-approval.edit'),
            'poRule' => ApprovalRule::for('purchase_order'),
            'poApproverRoles' => $roles('purchase-order-approval.edit'),
        ]);
    }

    public function update(ApprovalRuleRequest $request): RedirectResponse
    {
        $this->settings->save('quotation', $request->validated(), $request->user());

        return redirect()->route('admin.approval.edit')->with('toast', 'Quotation approval rule saved');
    }

    public function updateSalesOrder(SalesOrderApprovalRuleRequest $request): RedirectResponse
    {
        $this->settings->save('sales_order', $request->validated(), $request->user());

        return redirect()->route('admin.approval.edit')->with('toast', 'Sales order approval rule saved');
    }

    /** Stock transfer (27/10/2026): approval required or not; one level, no amount. */
    public function updateStockTransfer(\App\Http\Requests\Admin\StockTransferApprovalRuleRequest $request): RedirectResponse
    {
        $this->settings->save('stock_transfer', $request->validated(), $request->user());

        return redirect()->route('admin.approval.edit')->with('toast', 'Stock transfer approval rule saved');
    }

    /** Purchase order (27/10/2026, prototype "Purchase PO"): approval required, and above which taxable value. */
    public function updatePurchaseOrder(\App\Http\Requests\Admin\PurchaseOrderApprovalRuleRequest $request): RedirectResponse
    {
        $this->settings->save('purchase_order', $request->validated(), $request->user());

        return redirect()->route('admin.approval.edit')->with('toast', 'Purchase order approval rule saved');
    }
}
