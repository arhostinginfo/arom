<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of a purchase order: quantity, UOM, rate, discount, GST at the tax structure's rates, delivery date, and the
 * quantity already received on GRNs (received_quantity, changed only by PurchaseOrderService::receive()).
 */
class PurchaseOrderLine extends Model
{
    protected $fillable = ['line_no', 'item_id', 'description', 'hsn_code', 'quantity', 'uom_id', 'rate', 'discount_percent', 'taxable_value', 'tax_structure_id',
        'cgst_rate', 'sgst_rate', 'igst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total', 'delivery_date'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'rate' => 'decimal:4', 'discount_percent' => 'decimal:2', 'taxable_value' => 'decimal:2',
            'cgst_rate' => 'decimal:2', 'sgst_rate' => 'decimal:2', 'igst_rate' => 'decimal:2',
            'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'total' => 'decimal:2',
            'delivery_date' => 'date', 'received_quantity' => 'decimal:3',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function taxStructure(): BelongsTo
    {
        return $this->belongsTo(TaxStructure::class);
    }

    /** Quantity still to receive on GRNs (the pending quantity). */
    public function pending(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->received_quantity, 3));
    }
}
