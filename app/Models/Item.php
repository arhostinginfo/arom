<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Item master (ADMIN tab A15:P16): a part of a party, with its HSN/SAC, UOM, tax structure and price (D14).
class Item extends Model
{
    use SoftDeletes;

    public const TYPES = ['Sales', 'Purchase', 'Both'];

    /** Fields typed on the Item master page, in the order of the workbook. */
    public const FIELDS = ['party_id', 'item_category_id', 'description', 'item_no', 'internal_item_no', 'type', 'hsn_sac_code_id', 'uom_id',
        'tax_structure_id', 'price', 'currency_id', 'min_stock', 'max_stock', 'weight_per_piece', 'packaging_qty', 'status'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['price' => 'decimal:4', 'min_stock' => 'decimal:3', 'max_stock' => 'decimal:3', 'weight_per_piece' => 'decimal:3', 'packaging_qty' => 'decimal:3'];
    }

    /** Active items a customer can be sold: Sales or Both. With a party, that party's items only (SALES A22). */
    public function scopeForSale(Builder $query, ?int $partyId = null): Builder
    {
        return $query->where('status', 'Active')->whereIn('type', ['Sales', 'Both'])
            ->when($partyId, fn ($q) => $q->where('party_id', $partyId));
    }

    /** Active items a supplier can be ordered from: Purchase or Both (S-4; Purchase order, 27/10/2026). */
    public function scopeForPurchase(Builder $query): Builder
    {
        return $query->where('status', 'Active')->whereIn('type', ['Purchase', 'Both']);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function hsn(): BelongsTo
    {
        return $this->belongsTo(HsnSacCode::class, 'hsn_sac_code_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function taxStructure(): BelongsTo
    {
        return $this->belongsTo(TaxStructure::class);
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

    /** "BRK-2210 · Brake bracket · IPN 10-4471" for pickers (SALES A13 "Item no / Description / IPN"). */
    public function pickerLabel(): string
    {
        return $this->item_no.' · '.$this->description.($this->internal_item_no ? ' · IPN '.$this->internal_item_no : '');
    }

    public function activityLabel(): string
    {
        return 'item '.$this->item_no;
    }
}
