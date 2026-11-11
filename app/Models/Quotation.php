<?php

namespace App\Models;

use App\Enums\FollowupStatus;
use App\Enums\QuotationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/*
| CRM > Quotation. One row per revision (decision D24). Made from an enquiry (CRM changes, Sat 13/10/2026):
| the client and enquiry number come from the enquiry. The old Followup status column is kept but no longer
| shown; Status replaces it.
*/
class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = ['enquiry_id', 'party_id', 'prospect_id', 'source_of_enquiry', 'reference', 'project_name', 'sales_person_id', 'enquiry_receipt_date',
        'currency_id', 'followup_status', 'quotation_date', 'valid_until', 'contact_name', 'site', 'payment_terms', 'delivery_terms', 'tax_terms', 'tax_structure_id', 'discount_amount', 'notes'];

    protected function casts(): array
    {
        return [
            'enquiry_receipt_date' => 'date',
            'quotation_date' => 'date',
            'valid_until' => 'date',
            'followup_status' => FollowupStatus::class,
            'status' => QuotationStatus::class,
            'is_latest' => 'boolean',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'sent_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuotationLine::class)->orderBy('line_no');
    }

    public function cost(): HasOne
    {
        return $this->hasOne(QuotationCost::class);
    }

    /** Every revision of this quotation, oldest first. */
    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'quotation_no', 'quotation_no')
            ->where('unit_id', $this->unit_id)->where('financial_year_id', $this->financial_year_id)
            ->orderBy('revision_no');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class)->withTrashed();
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_person_id')->withTrashed();
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by')->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** GST / tax of the quotation from the Tax master (23/10/2026); old quotations keep only the tax_terms text. */
    public function taxStructure(): BelongsTo
    {
        return $this->belongsTo(TaxStructure::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function customerPos(): HasMany
    {
        return $this->hasMany(CustomerPo::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function scopeLatestRevisions(Builder $query): Builder
    {
        return $query->where('is_latest', true);
    }

    /** Accepted by the customer but no PO / agreement entered yet: "Verbal wins / PO pending". */
    public function scopePoPending(Builder $query): Builder
    {
        return $query->latestRevisions()->where('status', QuotationStatus::Accepted->value)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('customer_pos')
                ->whereColumn('customer_pos.enquiry_id', 'quotations.enquiry_id')->whereNull('customer_pos.deleted_at'));
    }

    /** Customer or prospect name. */
    public function customerName(): string
    {
        return $this->party?->name ?? $this->prospect?->name ?? '';
    }

    public function activityLabel(): string
    {
        return $this->quotation_no.' Rev '.$this->revision_no;
    }
}
