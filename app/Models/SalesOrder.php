<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sales order (SALES tab A11:G14): the customer's PO with its validity, revision, uploaded PO file and the parts
 * with quantity, rate and delivery date. Number SO/26-27/0001 per unit and year (D16). Approval as set in
 * Admin › Approval (D3): Draft → Pending approval → Approved / Rejected.
 */
class SalesOrder extends Model
{
    use SoftDeletes;

    public const DRAFT = 'Draft';

    public const PENDING = 'Pending approval';

    public const APPROVED = 'Approved';

    public const REJECTED = 'Rejected';

    public const STATUSES = [self::DRAFT, self::PENDING, self::APPROVED, self::REJECTED];

    /** Header fields typed on the page. */
    public const FIELDS = ['party_id', 'po_number', 'start_date', 'end_date', 'revision_no', 'revision_date'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'revision_date' => 'date', 'total_value' => 'decimal:2'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('line_no');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SalesInvoice::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /** The customer PO this order was made from (Create Sales Order, 02/11/2026); a deleted PO still shows. */
    public function customerPo(): BelongsTo
    {
        return $this->belongsTo(CustomerPo::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /** Draft and Rejected orders can be changed; Approved and Pending ones only by Admin (D11). */
    public function isEditableBy(User $u): bool
    {
        return in_array($this->status, [self::DRAFT, self::REJECTED], true) || $u->isAdmin();
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::APPROVED => 'b-ok',
            self::PENDING => 'b-warn',
            self::REJECTED => 'b-err',
            default => 'b-neu',
        };
    }

    public function activityLabel(): string
    {
        return 'sales order '.$this->so_no;
    }
}
