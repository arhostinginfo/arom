<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Party master (customers and suppliers, ADMIN tab A19:J20). Its screen was built with the Sales module (15/10/2026).
class Party extends Model
{
    use SoftDeletes;

    public const TYPES = ['Customer', 'Supplier', 'Both'];

    /** Fields typed on the Party master page, in the order of the workbook. */
    public const FIELDS = ['name', 'gstin', 'type', 'billing_address', 'shipping_address', 'payment_terms', 'opening_balance', 'opening_balance_type', 'currency_id', 'status'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }

    /** Active parties a quotation, sales order or invoice can be made for. */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereIn('type', ['Customer', 'Both'])->where('status', 'Active')->orderBy('name');
    }

    /** Active parties a purchase order (later a GRN) can be made for: Supplier or Both (Purchase, 27/10/2026). */
    public function scopeSuppliers(Builder $query): Builder
    {
        return $query->whereIn('type', ['Supplier', 'Both'])->where('status', 'Active')->orderBy('name');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** GSTIN state code ("27" = Maharashtra); null without a GSTIN. */
    public function stateCode(): ?string
    {
        return $this->gstin ? substr($this->gstin, 0, 2) : null;
    }

    /** An export customer: billed in a currency other than INR (decision D15). */
    public function isForeign(): bool
    {
        return $this->currency_id !== null && $this->currency?->code !== null && $this->currency->code !== 'INR';
    }

    public function activityLabel(): string
    {
        return 'party '.$this->name;
    }
}
