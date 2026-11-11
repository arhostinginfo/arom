<?php

namespace App\Http\Requests\Admin;

/** Admin > Approval: the stock transfer rule (required or not, one level). A transfer has no value, so there is no amount. */
class StockTransferApprovalRuleRequest extends ApprovalRuleRequest
{
    protected $errorBag = 'stock_transfer';

    public function rules(): array
    {
        return ['is_required' => ['required', 'boolean']];
    }
}
