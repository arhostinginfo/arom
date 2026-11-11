<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One part of a sales order: quantity, rate, delivery date, and the quantity already billed (qty lock, D6).
class SalesOrderLine extends Model
{
    protected $fillable = ['line_no', 'item_id', 'quantity', 'rate', 'amount', 'delivery_date'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2', 'delivery_date' => 'date', 'invoiced_quantity' => 'decimal:3', 'credited_quantity' => 'decimal:3'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** Quantity still to bill on Sales invoices (the PO balance). */
    public function balance(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->invoiced_quantity, 3));
    }

    /** Quantity a credit note may still give back: invoiced minus earlier credit notes (code review M1). */
    public function creditRoom(): float
    {
        return max(0, round((float) $this->invoiced_quantity - (float) $this->credited_quantity, 3));
    }
}
