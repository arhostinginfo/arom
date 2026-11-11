<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Admin > Page setup: whether an optional part of the CRM flow (site visit, BOQ, costing) is switched on.
class ModuleSetting extends Model
{
    protected $fillable = ['module', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
