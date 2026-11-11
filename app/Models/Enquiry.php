<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\LeadRating;
use App\Enums\ProspectType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// CRM > Enquiry: the lead (CRM changes, Sat 13/10/2026). Its number is ENQ/yy-yy/0001 per unit and year.
class Enquiry extends Model
{
    use SoftDeletes;

    /** Fields typed on the Enquiry page, in the order of the Word file. */
    public const FIELDS = ['enquiry_date', 'lead_source_id', 'sub_source', 'competitor_details', 'prospect_type', 'party_id', 'prospect_id',
        'industry', 'industry_type_id', 'city_id', 'address', 'website', 'contact_name', 'contact_no', 'service_category_id',
        'approx_requirement', 'estimated_value', 'expected_closing_date', 'lead_rating', 'assigned_to', 'next_action', 'next_action_date', 'remarks'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return [
            'enquiry_date' => 'date',
            'expected_closing_date' => 'date',
            'next_action_date' => 'date',
            'estimated_value' => 'decimal:2',
            'prospect_type' => ProspectType::class,
            'lead_rating' => LeadRating::class,
            'status' => EnquiryStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Digits-only copy of the contact no for the picker search (full test B3).
        static::saving(fn (Enquiry $e) => $e->contact_no_digits = self::phoneDigits($e->contact_no));
    }

    /** "+91 98220 00005", "098220 00005" and "9822000005" all become "9822000005". */
    public static function phoneDigits(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) > 10 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        }
        $d = ltrim($d, '0');

        return $d === '' ? null : substr($d, 0, 20);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', EnquiryStatus::Open->value);
    }

    /** Open leads whose next action is due today or overdue. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('next_action_date')->where('next_action_date', '<=', today()->toDateString().' 23:59:59');
    }

    public function isDue(): bool
    {
        return $this->status === EnquiryStatus::Open && $this->next_action_date && $this->next_action_date->lte(today());
    }

    /** "Due today" or "3 day(s) late" when the next action is due, so "due" is never shown by colour alone (UX review M5). */
    public function dueLabel(): ?string
    {
        if (! $this->isDue()) {
            return null;
        }
        $late = (int) $this->next_action_date->diffInDays(today());

        return $late ? $late.' day(s) late' : 'Due today';
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function industryType(): BelongsTo
    {
        return $this->belongsTo(IndustryType::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function serviceCategory(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_to')->withTrashed();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class)->withTrashed();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function customerPos(): HasMany
    {
        return $this->hasMany(CustomerPo::class);
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

    /** The client's phone, from its Prospect (the one place it is kept), or as typed on this enquiry. */
    public function clientPhone(): ?string
    {
        return $this->prospect?->phone_1 ?: ($this->contact_no ?: null);
    }

    public function clientName(): string
    {
        return $this->party?->name ?? $this->prospect?->name ?? '';
    }

    public function activityLabel(): string
    {
        return $this->enquiry_no.' · '.$this->clientName();
    }
}
