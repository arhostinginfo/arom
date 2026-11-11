<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
| Quotation line (CRM changes): Item no, Item description, Qty, Unit, Rate, Discount %, Tax %, Total.
| Annual volume, sample and production target dates and technical details are older fields, kept
| for lines saved before the change and no longer on the form.
*/
class QuotationLine extends Model
{
    protected $fillable = ['line_no', 'item_no', 'description', 'qty', 'uom', 'rate', 'discount_percent', 'tax_structure_id', 'tax_percent', 'amount',
        'annual_volume', 'sample_target_date', 'production_target_date', 'technical_details'];

    protected function casts(): array
    {
        return [
            'annual_volume' => 'decimal:3',
            'qty' => 'decimal:3',
            'rate' => 'decimal:4',
            'discount_percent' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'amount' => 'decimal:2',
            'sample_target_date' => 'date',
            'production_target_date' => 'date',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** Tax master row of the item (23/10/2026); tax_percent keeps the rate applied when saved. */
    public function taxStructure(): BelongsTo
    {
        return $this->belongsTo(TaxStructure::class);
    }

    /** Qty × Rate, less the discount %, plus the tax %, to 2 decimals. */
    public static function total(float $qty, float $rate, float $discount, float $tax): float
    {
        return round($qty * $rate * (1 - $discount / 100) * (1 + $tax / 100), 2);
    }
}
