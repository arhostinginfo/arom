<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// CRM > Contacts: a contact / POC of a client, a prospect or a customer (CRM changes Word file, built 14/10/2026).
class Contact extends Model
{
    use SoftDeletes;

    /** Fields typed on the Contact page, in the order of the Word file. */
    public const FIELDS = ['party_id', 'prospect_id', 'name', 'designation', 'department', 'mobile', 'whatsapp', 'email', 'alternate_no',
        'preferred_communication', 'is_decision_maker', 'is_influencer', 'is_purchase_contact', 'is_facility_contact', 'is_billing_contact', 'notes'];

    /** The contact roles of the Word file, as tick boxes. */
    public const ROLES = [
        'is_decision_maker' => 'Decision maker',
        'is_influencer' => 'Influencer',
        'is_purchase_contact' => 'Purchase contact',
        'is_facility_contact' => 'Facility contact',
        'is_billing_contact' => 'Accounts / billing contact',
    ];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return array_fill_keys(array_keys(self::ROLES), 'boolean');
    }

    protected static function booted(): void
    {
        static::saving(fn (Contact $c) => $c->mobile_digits = Enquiry::phoneDigits($c->mobile));
    }

    /** Contacts of the client of an enquiry, quotation or PO. */
    public function scopeOfClient(Builder $query, ?int $partyId, ?int $prospectId): Builder
    {
        return $partyId ? $query->where('party_id', $partyId) : $query->where('prospect_id', (int) $prospectId);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function clientName(): string
    {
        return $this->party?->name ?? $this->prospect?->name ?? '';
    }

    /** "Decision maker, Purchase contact". */
    public function roleNames(): string
    {
        return implode(', ', array_values(array_filter(self::ROLES, fn ($label, $f) => $this->{$f}, ARRAY_FILTER_USE_BOTH)));
    }

    public function activityLabel(): string
    {
        return 'contact '.$this->contact_no.' '.$this->name;
    }
}
