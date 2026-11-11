<?php

namespace App\Models;

// Transporters picked on the tax invoice (SALES tab "Transporter").
class Transporter extends Master
{
    public function label(): string
    {
        return $this->name;
    }
}
