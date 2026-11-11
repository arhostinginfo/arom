<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * GRN entry (Purchase, prototype "grn"): a supplier invoice received, with its item lines against PO lines.
 * Pending match (no number) → Matched (GRN no GRN/26-27/0001 and GRN date, PO received quantities raised) →
 * Quality Approved (accepted quantity into stock through StockLedger, source_type 'grn') or Rejected (no stock).
 * Saved → Cancelled reverses the PO quantities and the stock. See docs/purchase/grn-report.md.
 */
class Grn extends Model
{
    use SoftDeletes;

    public const DRAFT = 'Draft';      // reserved: every save is Saved today

    public const SAVED = 'Saved';

    public const CANCELLED = 'Cancelled';

    public const STATUSES = [self::DRAFT, self::SAVED, self::CANCELLED];

    public const PENDING_MATCH = 'Pending match';

    public const MATCHED = 'Matched';

    public const QA_PENDING = 'Pending';

    public const QA_APPROVED = 'Approved';

    public const QA_REJECTED = 'Rejected';

    public const QUALITY = [self::QA_PENDING, self::QA_APPROVED, self::QA_REJECTED];

    /** Invoice type (prototype segmented control). Only Purchase receives against POs and brings stock in. */
    public const TYPES = ['Purchase', 'Credit', 'Debit'];

    public const TITLES = ['Purchase' => 'Purchase invoice', 'Credit' => 'Supplier credit note', 'Debit' => 'Supplier debit note'];

    /** StockLedger source_type of the movements this document writes. */
    public const LEDGER_SOURCE = 'grn';

    /** Header fields typed on the page. */
    public const FIELDS = ['invoice_type', 'party_id', 'invoice_no', 'invoice_date', 'invoice_amount', 'transporter_id', 'vehicle_no', 'eway_bill_no', 'eway_bill_date',
        'customer_id', 'sales_order_id', 'remarks'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return [
            'grn_date' => 'date', 'invoice_date' => 'date', 'eway_bill_date' => 'date',
            'matched_at' => 'datetime', 'quality_at' => 'datetime', 'cancelled_at' => 'datetime', 'stock_posted' => 'boolean',
            'invoice_amount' => 'decimal:2', 'taxable_value' => 'decimal:2', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2', 'grand_total' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GrnLine::class)->orderBy('line_no');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id')->withTrashed();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class)->withTrashed();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function qualityUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quality_by')->withTrashed();
    }

    /** GRNs of a unit and financial year (the top bar). */
    public function scopeForWork(Builder $q, ?int $unitId, int $yearId): Builder
    {
        return $q->where('unit_id', $unitId)->where('financial_year_id', $yearId);
    }

    /** Saved GRNs whose lines do not yet match the supplier invoice amount (dashboard tile "GRNs pending match"). */
    public function scopePendingMatch(Builder $q): Builder
    {
        return $q->where('status', self::SAVED)->where('match_status', self::PENDING_MATCH);
    }

    /** Matched GRNs waiting for the quality decision (dashboard tile "GRNs pending quality approval"). */
    public function scopePendingQuality(Builder $q): Builder
    {
        return $q->where('status', self::SAVED)->where('match_status', self::MATCHED)->where('quality_status', self::QA_PENDING);
    }

    public function scopeQualityApproved(Builder $q): Builder
    {
        return $q->where('status', self::SAVED)->where('quality_status', self::QA_APPROVED);
    }

    public function isMatched(): bool
    {
        return $this->match_status === self::MATCHED;
    }

    public function isPurchase(): bool
    {
        return $this->invoice_type === 'Purchase';
    }

    /** Saved and before the quality decision: lines and amounts can still change (a matched GRN must stay matched). */
    public function isEditable(): bool
    {
        return $this->status === self::SAVED && $this->quality_status === self::QA_PENDING;
    }

    public function currencyCode(): string
    {
        return $this->currency?->code ?? 'INR';
    }

    /** What the user sees as the GRN's state: Cancelled, Pending match, Pending quality, Approved or Rejected. */
    public function stateLabel(): string
    {
        return match (true) {
            $this->status === self::CANCELLED => 'Cancelled',
            ! $this->isMatched() => 'Pending match',
            $this->quality_status === self::QA_PENDING => 'Pending quality',
            default => 'Quality '.strtolower($this->quality_status),
        };
    }

    public function badgeClass(): string
    {
        return match (true) {
            $this->status === self::CANCELLED, $this->quality_status === self::QA_REJECTED => 'b-err',
            ! $this->isMatched(), $this->quality_status === self::QA_PENDING => 'b-warn',
            default => 'b-ok',
        };
    }

    public function label(): string
    {
        return $this->grn_no ?? 'pending match ('.$this->invoice_no.')';
    }

    public function activityLabel(): string
    {
        return 'GRN '.($this->grn_no ?? 'for invoice '.$this->invoice_no);
    }
}
