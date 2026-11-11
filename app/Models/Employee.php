<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Admin > Masters > Employee master (CRM changes). Leads are assigned to employees.
class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = ['code', 'name', 'designation', 'department', 'mobile', 'email', 'joining_date', 'address', 'user_id', 'status'];

    protected function casts(): array
    {
        return ['joining_date' => 'date'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
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
        return $this->name.' ('.$this->code.')';
    }
}
