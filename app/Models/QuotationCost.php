<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Internal cost details of one quotation revision. Never printed on the quotation.
class QuotationCost extends Model
{
    /** Cost fields in the order of the Word file, with their labels. */
    public const COSTS = [
        'material_cost' => 'Purchase / material cost',
        'plant_production_cost' => 'Plant production cost',
        'planter_cost' => 'Planter cost',
        'labour_cost' => 'Labour cost',
        'transportation_cost' => 'Transportation cost',
        'installation_cost' => 'Installation cost',
        'maintenance_cost' => 'Maintenance cost',
        'other_cost' => 'Other cost',
    ];

    protected $fillable = ['material_cost', 'plant_production_cost', 'planter_cost', 'labour_cost', 'transportation_cost', 'installation_cost', 'maintenance_cost', 'other_cost', 'total_cost', 'margin_percent', 'margin_amount', 'proposed_selling_price'];

    protected function casts(): array
    {
        return array_fill_keys($this->fillable, 'decimal:2');
    }
}
