<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// CRM > PO / Agreement received: the customer's PO or agreement against a quotation (CRM changes 13/10/2026).
// Separate PO menu removed (02/11/2026): there are no direct POs any more, so "source" is always crm (the column stays).
class CustomerPo extends Model
{
    use SoftDeletes;

    protected $table = 'customer_pos';

    public const CRM = 'crm';

    // quotation_id, enquiry_id and party_id are set by the service after their checks, not filled from the form (01/11/2026).
    public const FIELDS = ['po_no', 'po_date', 'site', 'po_value', 'po_quantity', 'valid_from', 'valid_to',
        'payment_terms', 'tax_terms', 'contact_name', 'received_by', 'received_date', 'remarks'];

    protected $fillable = self::FIELDS;

    protected $attributes = ['source' => self::CRM];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'received_date' => 'date',
            'po_value' => 'decimal:2',
            'po_quantity' => 'decimal:3',
        ];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class)->withTrashed();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class)->withTrashed();
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class)->withTrashed();
    }

    /** The unit, for the sales order made from the PO (02/11/2026). */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'received_by')->withTrashed();
    }

    /** Items of the PO, in the order entered (29/10/2026). */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerPoLine::class)->orderBy('line_no')->orderBy('id');
    }

    /** The sales order made from this PO (Create Sales Order, 02/11/2026): one active (not deleted) order at most. */
    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class)->latest('id');
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

    /**
     * The PO page a user can open this PO on (02/11/2026, for "From PO" on a sales order), or null: CRM › 6 PO / Agreement
     * while CRM is on, for a user with PO / Agreement · View in the PO's unit.
     */
    public function pageUrlFor(User $user): ?string
    {
        if ($this->trashed() || ! $user->can('customer-po.view') || ! in_array($this->unit_id, \App\Support\WorkContext::unitIds($user), true)) {
            return null;
        }

        return \App\Support\Modules::on('crm') ? route('crm.customer-pos.show', $this) : null;
    }

    public function clientName(): string
    {
        return $this->party?->name ?? $this->prospect?->name ?? '';
    }

    /** "PO KP/PO/1 · ENQ/0001"; a PO with no enquiry (old data) shows its client instead. */
    public function activityLabel(): string
    {
        return 'PO '.$this->po_no.' · '.($this->enquiry_id ? ($this->enquiry?->enquiry_no ?? '') : $this->clientName());
    }
}
