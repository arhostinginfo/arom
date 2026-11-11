<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

// HSN/SAC master (D21): goods (HSN) and services (SAC) codes with their default tax structure.
class HsnSacCode extends Master
{
    public function taxStructure(): BelongsTo
    {
        return $this->belongsTo(TaxStructure::class);
    }

    public function label(): string
    {
        return $this->code.($this->description ? ' · '.$this->description : '');
    }
}
