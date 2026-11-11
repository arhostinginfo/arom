<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tax invoice, credit note or debit note (SALES tab A17:I19, "Invoice type Sales / Credit / Debit"). Numbers
 * INV / CN / DN per unit and year in an unbroken series (D16, C12), so an invoice is cancelled, never deleted.
 */
class SalesInvoice extends Model
{
    public const TYPES = ['Sales', 'Credit', 'Debit'];

    /** Number series and title of each invoice type. */
    public const SERIES = ['Sales' => 'tax_invoice', 'Credit' => 'credit_note', 'Debit' => 'debit_note'];

    public const TITLES = ['Sales' => 'Tax invoice', 'Credit' => 'Credit note', 'Debit' => 'Debit note'];

    public const MODES = ['Road', 'Rail', 'Air', 'Ship'];

    /** Header fields typed on the page. */
    public const FIELDS = ['invoice_type', 'invoice_date', 'party_id', 'sales_order_id', 'shipping_address', 'mode_of_transport', 'transporter_id',
        'vehicle_no', 'distance_km', 'pf_charges'];

    protected $fillable = self::FIELDS;

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'pf_charges' => 'decimal:2', 'taxable_value' => 'decimal:2', 'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'grand_total' => 'decimal:2', 'distance_km' => 'decimal:1'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class)->orderBy('line_no');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class)->withTrashed();
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function isCancelled(): bool
    {
        return $this->status === 'Cancelled';
    }

    public function title(): string
    {
        return self::TITLES[$this->invoice_type] ?? 'Tax invoice';
    }

    public function activityLabel(): string
    {
        return strtolower($this->title()).' '.$this->invoice_no;
    }
}
