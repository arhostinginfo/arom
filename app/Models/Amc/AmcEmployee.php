<?php

namespace App\Models\Amc;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// AMC Employee master (21/11/2026): operation managers and gardeners of AMC contracts. Separate from the CRM Employee master.
class AmcEmployee extends Model
{
    public const OPERATION_MANAGER = 'Operation Manager';

    public const GARDENER = 'Gardener';

    protected $table = 'amc_employees';

    protected $fillable = ['code', 'name', 'role', 'mobile', 'status'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    /** Active employees of a role for a dropdown, plus the one already chosen even if inactive or of another role now. */
    public function scopeForPicker(Builder $query, ?string $role, $keep = null): Builder
    {
        return $query->where(fn ($w) => $w->where(fn ($a) => $a->where('status', 'Active')->when($role, fn ($r) => $r->where('role', $role)))
            ->when($keep, fn ($k) => $k->orWhere('id', $keep)))->orderBy('name')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function label(): string
    {
        return $this->name.' ('.$this->code.')';
    }

    public function activityLabel(): string
    {
        return $this->label();
    }
}
