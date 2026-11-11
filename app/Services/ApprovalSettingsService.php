<?php

namespace App\Services;

use App\Models\ApprovalRule;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\DB;

/** Admin > Approval (CRM changes; Sales): the quotation and sales order approval rules (amount criteria). */
class ApprovalSettingsService
{
    public function save(string $type, array $data, User $actor): ApprovalRule
    {
        return DB::transaction(function () use ($type, $data, $actor) {
            $rule = ApprovalRule::where('document_type', $type)->lockForUpdate()->first() ?? new ApprovalRule(['document_type' => $type]);
            $before = $this->snapshot($rule);
            $rule->is_required = (bool) $data['is_required'];
            $rule->approve_above_amount = ($data['approve_above_amount'] ?? '') !== '' ? $data['approve_above_amount'] : null;
            $rule->updated_by = $actor->id;
            $rule->save();
            if ($changes = ActivityLogger::diff($before, $this->snapshot($rule))) {
                ActivityLogger::log('approval.updated', $rule, 'Changed the '.$type.' approval rule', $changes);
            }

            return $rule;
        });
    }

    private function snapshot(ApprovalRule $r): array
    {
        return [
            'Approval required' => $r->exists ? ($r->is_required ? 'Yes' : 'No') : null,
            'Approve above amount' => $r->approve_above_amount !== null ? number_format((float) $r->approve_above_amount, 2, '.', '') : match ($r->document_type) { 'sales_order' => 'Every sales order', 'stock_transfer' => 'Every stock transfer', 'purchase_order' => 'Every purchase order', default => 'Every quotation' },
        ];
    }
}
