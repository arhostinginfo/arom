<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock transfer (Purchase, prototype D9): items moved from one unit (plant) to another. Number ST/26-27/0001 per
 * source unit and year. Draft → Pending approval → Approved (stock moves through StockLedger) / Rejected; any of them
 * can be Cancelled, and cancelling an Approved transfer takes its stock movements back off the ledger.
 */
class StockTransfer extends Model
{
    use SoftDeletes;

    public const DRAFT = 'Draft';

    public const PENDING = 'Pending approval';

    public const APPROVED = 'Approved';

    public const REJECTED = 'Rejected';

    public const CANCELLED = 'Cancelled';

    public const STATUSES = [self::DRAFT, self::PENDING, self::APPROVED, self::REJECTED, self::CANCELLED];

    /** Source type: the kind of inward document the stock came from (prototype). */
    public const SOURCE_TYPES = ['Purchase', 'Credit', 'Debit'];

    /** StockLedger source_type of the movements this document writes. */
    public const LEDGER_SOURCE = 'stock_transfer';

    /** Header fields typed on the page. */
    public const FIELDS = ['transfer_date', 'from_unit_id', 'to_unit_id', 'source_type', 'remarks'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('line_no');
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    /** Draft and Rejected transfers can be changed; once sent for approval the stock check depends on what was sent. */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::REJECTED], true);
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::APPROVED => 'b-ok',
            self::PENDING => 'b-warn',
            self::REJECTED, self::CANCELLED => 'b-err',
            default => 'b-neu',
        };
    }

    public function activityLabel(): string
    {
        return 'stock transfer '.$this->transfer_no;
    }
}
