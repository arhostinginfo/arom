<?php

namespace App\Http\Requests\Admin;

/** Admin > Approval: the purchase order rule (D3, prototype "Purchase PO"). Same fields as the quotation rule; its errors show on its own card. */
class PurchaseOrderApprovalRuleRequest extends ApprovalRuleRequest
{
    protected $errorBag = 'purchase_order';
}
