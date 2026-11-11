<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A simple dropdown list kept in Admin > Masters (CRM changes, Sat 13/10/2026): Source, Category
 * (the Enquiry's Service type), Industry type and City. Each has its own table with a unique name.
 */
abstract class SimpleMaster extends Model
{
    protected $fillable = ['name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Active rows in list order for a dropdown, plus the row already chosen even if it was made inactive. */
    public function scopeForPicker(Builder $query, $keep = null): Builder
    {
        return $query->where(fn ($w) => $w->where('is_active', true)->when($keep, fn ($k) => $k->orWhere('id', $keep)))
            ->orderBy('sort_order')->orderBy('name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
