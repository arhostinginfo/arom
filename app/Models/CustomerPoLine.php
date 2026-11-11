<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// An item of a customer PO / agreement (29/10/2026), picked from the Item master (Part master).
class CustomerPoLine extends Model
{
    protected $fillable = ['line_no', 'item_id', 'description', 'uom_id', 'quantity', 'rate', 'amount'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    public function customerPo(): BelongsTo
    {
        return $this->belongsTo(CustomerPo::class)->withTrashed();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }
}
