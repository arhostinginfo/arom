<?php

namespace App\Models\Amc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One location line of an AMC client: Location 1, Location 2, planter and plant with their quantities.
class AmcClientLine extends Model
{
    protected $table = 'amc_client_lines';

    protected $fillable = ['line_no', 'location_1', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_qty'];

    protected function casts(): array
    {
        return ['planter_qty' => 'integer', 'plant_qty' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AmcClient::class, 'amc_client_id');
    }

    public function planter(): BelongsTo
    {
        return $this->belongsTo(AmcPlanter::class, 'amc_planter_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(AmcPlant::class, 'amc_plant_id');
    }

    /** "Tower A · Floor 5 - Lobby", for the Return & Replace location picker. */
    public function locationLabel(): string
    {
        return implode(' · ', array_filter([$this->location_1, $this->location_2], fn ($v) => (string) $v !== '')) ?: 'Line '.$this->line_no.' (no location)';
    }
}
