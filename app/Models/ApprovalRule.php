<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Admin > Approval: whether a document type needs approval, and above which amount.
class ApprovalRule extends Model
{
    protected $fillable = ['document_type', 'is_required', 'approve_above_amount'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'approve_above_amount' => 'decimal:2'];
    }

    /** The rule is seeded by the migration; reading it never writes (a missing row means "every document"). */
    public static function for(string $type): self
    {
        return self::firstOrNew(['document_type' => $type], ['is_required' => true]);
    }

    public function activityLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->document_type)).' approval rule';
    }

    /**
     * True when a document of this amount must be approved by an approver. Blank amount = every document.
     * The amount is in rupees; a document in another currency always needs approval (no exchange rate).
     */
    public function needsApproval(float $amount, bool $inRupees = true): bool
    {
        return $this->is_required && ($this->approve_above_amount === null || ! $inRupees || $amount > (float) $this->approve_above_amount);
    }
}
