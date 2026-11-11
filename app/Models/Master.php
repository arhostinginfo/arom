<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A master of Admin › Masters kept through the shared master screen (Sales module, 15/10/2026): Tax master,
 * HSN/SAC, UOM, Category and Transporter. The fields of each are listed in config('nursery.sales_masters').
 */
abstract class Master extends Model
{
    protected $guarded = ['id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Active rows for a dropdown, plus the row already chosen even if it was made inactive. */
    public function scopeForPicker(Builder $query, $keep = null): Builder
    {
        return $query->where(fn ($w) => $w->where('is_active', true)->when($keep, fn ($k) => $k->orWhere('id', $keep)));
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
    abstract public function label(): string;
}
