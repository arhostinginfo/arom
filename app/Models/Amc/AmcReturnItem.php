<?php

namespace App\Models\Amc;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** (23/11/2026) One row of a Return & Replace entry: an entry of the customer's Location with what is returned. */
class AmcReturnItem extends Model
{
    protected $table = 'amc_return_items';

    protected $fillable = ['line_no', 'amc_client_line_id', 'location_2', 'amc_planter_id', 'planter_qty', 'amc_plant_id', 'plant_size', 'plant_qty', 'rate', 'quality'];

    protected function casts(): array
    {
        return ['planter_qty' => 'integer', 'plant_qty' => 'integer', 'rate' => 'decimal:2'];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(AmcReturn::class, 'amc_return_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(AmcClientLine::class, 'amc_client_line_id');
    }

    public function planter(): BelongsTo
    {
        return $this->belongsTo(AmcPlanter::class, 'amc_planter_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(AmcPlant::class, 'amc_plant_id');
    }

    /** The quantity returned: the plant qty, else the planter qty. */
    public function qty(): int
    {
        return (int) ($this->plant_qty ?? $this->planter_qty ?? 0);
    }

    /** (23/11/2026) Rate × plant qty; null without a rate. */
    public function amount(): ?float
    {
        return $this->rate !== null && $this->plant_qty !== null ? round((float) $this->rate * $this->plant_qty, 2) : null;
    }

    public function qualityBadge(): string
    {
        return match ($this->quality) {
            'Reusable' => 'b-ok',
            'Needs Treatment' => 'b-warn',
            'Repairable' => 'b-info',
            null => 'b-neu',
            default => 'b-err',
        };
    }

    /** "Floor 5: Ceramic ×4, Rose (5 ft) ×5 @ ₹120.00 · Reusable" for the list, the history and the CSV. */
    public function summary(): string
    {
        $parts = array_filter([
            $this->amc_planter_id ? ($this->planter?->name ?? 'Planter').($this->planter_qty !== null ? ' ×'.$this->planter_qty : '') : null,
            $this->amc_plant_id ? ($this->plant?->name ?? 'Plant').($this->plant_size ? ' ('.$this->plant_size.')' : '').($this->plant_qty !== null ? ' ×'.$this->plant_qty : '') : null,
        ]);

        return ($this->location_2 !== null && $this->location_2 !== '' ? $this->location_2.': ' : '').implode(', ', $parts)
            .($this->rate !== null ? ' @ ₹'.\App\Support\Money::indian($this->rate, 2) : '')
            .($this->quality ? ' · '.$this->quality : '');
    }
}
