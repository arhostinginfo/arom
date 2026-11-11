<?php

namespace App\Http\Requests\Admin;

/** Admin > Approval: the sales order rule (D3). Same fields as the quotation rule; its errors show on its own card. */
class SalesOrderApprovalRuleRequest extends ApprovalRuleRequest
{
    protected $errorBag = 'sales_order';
}
