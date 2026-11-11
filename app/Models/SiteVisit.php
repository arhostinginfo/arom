<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// CRM > Site visit / requirement gathering (CRM changes). Belongs to an enquiry; photos are documents.
class SiteVisit extends Model
{
    use SoftDeletes;

    public const STATUSES = ['Planned', 'Done'];

    /** Fields typed on the page, in the order of the Word file. */
    public const FIELDS = ['enquiry_id', 'visit_at', 'poc', 'technical_employee_id', 'site_address', 'area_measurements', 'floors',
        'indoor_outdoor_area', 'existing_plants', 'existing_planters', 'lighting_condition', 'water_availability',
        'access_restrictions', 'customer_requirement', 'remarks', 'next_action', 'status'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['visit_at' => 'datetime'];
    }

    /** Planned visits whose date has come: the "Site visit due" alert. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'Planned')->where('visit_at', '<=', now()->endOfDay());
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    public function technicalPerson(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technical_employee_id')->withTrashed();
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
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
        return 'Site visit '.$this->visit_at?->format(config('nursery.datetime_format')).' · '.($this->enquiry?->enquiry_no ?? '');
    }
}
