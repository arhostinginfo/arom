<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of a GRN: the PO line it is received against (Purchase), the invoice quantity, the rate (the PO line's net
 * rate unless typed), GST at the tax structure's rates, and the accepted / rejected quantities of the quality decision.
 */
class GrnLine extends Model
{
    protected $fillable = ['line_no', 'purchase_order_id', 'purchase_order_line_id', 'item_id', 'description', 'hsn_code', 'quantity', 'accepted_quantity', 'rejected_quantity',
        'uom_id', 'rate', 'taxable_value', 'tax_structure_id', 'cgst_rate', 'sgst_rate', 'igst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount', 'total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'accepted_quantity' => 'decimal:3', 'rejected_quantity' => 'decimal:3', 'rate' => 'decimal:4', 'taxable_value' => 'decimal:2',
            'cgst_rate' => 'decimal:2', 'sgst_rate' => 'decimal:2', 'igst_rate' => 'decimal:2',
            'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'total' => 'decimal:2',
        ];
    }

    public function grn(): BelongsTo
    {
        return $this->belongsTo(Grn::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id')->withTrashed();
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
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
}
