<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Purchase order (PURCHASE tab, prototype "Purchase order" D5): a supplier PO with its items, GST and approval.
 * Number PO/26-27/0001 per unit and year (D16). Approval as set in Admin › Approval (D3):
 * Draft → Pending approval → Approved / Rejected; GRN entry (later) moves it to Partly received / Received through
 * PurchaseOrderService::receive(); Cancelled keeps its number; Closed is kept for a short-close later.
 */
class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const DRAFT = 'Draft';

    public const PENDING = 'Pending approval';

    public const APPROVED = 'Approved';

    public const REJECTED = 'Rejected';

    public const PARTLY_RECEIVED = 'Partly received';

    public const RECEIVED = 'Received';

    public const CLOSED = 'Closed';

    public const CANCELLED = 'Cancelled';

    public const STATUSES = [self::DRAFT, self::PENDING, self::APPROVED, self::REJECTED, self::PARTLY_RECEIVED, self::RECEIVED, self::CLOSED, self::CANCELLED];

    /** Statuses a GRN can receive against (GRN contract). */
    public const RECEIVABLE = [self::APPROVED, self::PARTLY_RECEIVED];

    /** A supplier billing in another currency: no GST on the PO (customs IGST is paid on the bill of entry). */
    public const IMPORT = 'Import';

    /** Header fields typed on the page. */
    public const FIELDS = ['party_id', 'po_date', 'currency_id', 'delivery_date', 'payment_terms', 'transporter_id', 'billing_address', 'shipping_address', 'remarks'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return [
            'po_date' => 'date', 'delivery_date' => 'date', 'approved_at' => 'datetime',
            'taxable_value' => 'decimal:2', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'grand_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_no');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /** POs of a unit and financial year (the top bar). */
    public function scopeForWork(Builder $q, ?int $unitId, int $yearId): Builder
    {
        return $q->where('unit_id', $unitId)->where('financial_year_id', $yearId);
    }

    public function scopePendingApproval(Builder $q): Builder
    {
        return $q->where('status', self::PENDING);
    }

    /**
     * Open POs, pending GRN: Approved or Partly received with at least one line not fully received. For the GRN page's
     * PO picker and the later "Open purchase orders" tile and PO summary report.
     */
    public function scopePendingReceipt(Builder $q): Builder
    {
        return $q->whereIn('status', self::RECEIVABLE)
            ->whereHas('lines', fn ($l) => $l->whereColumn('received_quantity', '<', 'quantity'));
    }

    /** Draft and Rejected POs can be changed; Pending and Approved ones only by an Admin (as sales orders, D11). */
    public function isEditableBy(User $u): bool
    {
        return in_array($this->status, [self::DRAFT, self::REJECTED], true)
            || ($u->isAdmin() && in_array($this->status, [self::PENDING, self::APPROVED, self::PARTLY_RECEIVED], true));
    }

    /** Something received on a GRN: party and received lines are locked, and the PO cannot be deleted or cancelled. */
    public function hasReceipts(): bool
    {
        return $this->lines()->where('received_quantity', '>', 0)->exists();
    }

    public function isImport(): bool
    {
        return $this->supply_type === self::IMPORT;
    }

    public function currencyCode(): string
    {
        return $this->currency?->code ?? 'INR';
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::APPROVED, self::RECEIVED => 'b-ok',
            self::PENDING, self::PARTLY_RECEIVED => 'b-warn',
            self::REJECTED, self::CANCELLED => 'b-err',
            default => 'b-neu',
        };
    }

    public function activityLabel(): string
    {
        return 'purchase order '.$this->po_no;
    }
}
