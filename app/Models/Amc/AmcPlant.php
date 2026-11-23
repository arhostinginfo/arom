<?php

namespace App\Models\Amc;

// Plant master: a plant and its size as free text ("5 ft"); Return & Replace shows the size from here.
class AmcPlant extends AmcMaster
{
    protected $table = 'amc_plants';

    protected function casts(): array
    {
        return parent::casts() + ['rate' => 'decimal:2'];
    }

    public function label(): string
    {
        return $this->size ? $this->name.' ('.$this->size.')' : $this->name;
    }
}
