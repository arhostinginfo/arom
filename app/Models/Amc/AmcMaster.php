<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A simple AMC master (21/11/2026): Business segment, Planter, Plant, AMC type. Fields of each are listed in
 * config('amc.masters'). Kept apart from the Sales masters (App\Models\Master) so the two never share rules.
 */
abstract class AmcMaster extends Model
{
    protected $guarded = ['id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Active rows for a dropdown, plus the row already chosen even if it was made inactive. */
    public function scopeForPicker(Builder $query, $keep = null): Builder
    {
        return $query->where(fn ($w) => $w->where('is_active', true)->when($keep, fn ($k) => $k->orWhere('id', $keep)))->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    /** The text that names the row in lists, pickers and the activity log. */
    public function label(): string
    {
        return $this->name;
    }

    public function activityLabel(): string
    {
        return $this->label();
    }
}
