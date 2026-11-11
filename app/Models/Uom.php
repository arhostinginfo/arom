<?php

namespace App\Models;

// UOM master (D21): units of measure (decision D10 keeps units of measure apart from company units).
class Uom extends Master
{
    public function label(): string
    {
        return $this->code;
    }
}
