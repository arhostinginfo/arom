<?php

namespace App\Models;

use App\Support\Gst;

// Tax master (D21): GST rates of one tax structure.
class TaxStructure extends Master
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'cgst_rate' => 'decimal:2', 'sgst_rate' => 'decimal:2', 'igst_rate' => 'decimal:2'];
    }

    public function label(): string
    {
        return $this->name;
    }

    /**
     * Total GST % for a supply type (quotation lines, 23/10/2026): CGST + SGST in the company's state, IGST in another
     * state, none for export, as on the tax invoice. A structure with only an IGST rate gives that rate in the state too.
     */
    public function rateFor(string $supply): float
    {
        $intra = (float) $this->cgst_rate + (float) $this->sgst_rate;

        return match ($supply) {
            Gst::INTER => (float) $this->igst_rate,
            Gst::EXPORT => 0.0,
            default => $intra ?: (float) $this->igst_rate,
        };
    }
}
