<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Return & Replace (21/11/2026): a plant returned from a client's location, its quality and its replacement.
class AmcReturn extends Model
{
    protected $table = 'amc_returns';

    protected $fillable = ['amc_client_id', 'amc_client_line_id', 'location_1', 'location_2', 'request_date', 'replace_date', 'reported_by_id',
        'amc_type_id', 'amc_plant_id', 'plant_size', 'qty', 'rate', 'gardener_id', 'return_remark', 'quality'];

    protected function casts(): array
    {
        return ['request_date' => 'date', 'replace_date' => 'date', 'qty' => 'integer', 'rate' => 'decimal:2'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(AmcClient::class, 'amc_client_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(AmcClientLine::class, 'amc_client_line_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(AmcEmployee::class, 'reported_by_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AmcType::class, 'amc_type_id');
    }

    public function plant(): BelongsTo
    {
        return $this->belongsTo(AmcPlant::class, 'amc_plant_id');
    }

    /** (23/11/2026) The rows: one per entry of the Location (planter, plant, quantities and rate). */
    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AmcReturnItem::class, 'amc_return_id')->orderBy('line_no')->orderBy('id');
    }

    public function gardener(): BelongsTo
    {
        return $this->belongsTo(AmcEmployee::class, 'gardener_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** "RR-0001". */
    public function number(): string
    {
        return sprintf('RR-%04d', $this->id);
    }

    public function activityLabel(): string
    {
        return $this->number().' · '.($this->client?->name ?? '');
    }

    public function qualityBadge(): string
    {
        return match ($this->quality) {
            'Reusable' => 'b-ok',
            'Needs Treatment' => 'b-warn',
            'Repairable' => 'b-info',
            default => 'b-err',
        };
    }
}
