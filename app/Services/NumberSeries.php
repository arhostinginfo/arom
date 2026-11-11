<?php

namespace App\Services;

use App\Models\FinancialYear;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;

/**
 * Automatic document numbers per document type, unit and financial year (decision D16),
 * e.g. QT/26-27/0001. Call inside the transaction that saves the document: the series row
 * is locked until it commits, so two people saving at once never get the same number.
 */
class NumberSeries
{
    public const PREFIXES = [
        'enquiry' => 'ENQ',
        'quotation' => 'QT',
        // Sales module (D16): sales orders, and tax invoices, credit notes and debit notes in their own series.
        'sales_order' => 'SO',
        'tax_invoice' => 'INV',
        'credit_note' => 'CN',
        'debit_note' => 'DN',
        // Purchase › Stock transfer (27/10/2026): per source unit and year, ST/26-27/0001.
        'stock_transfer' => 'ST',
        // Purchase order (27/10/2026): PO/26-27/0001 per unit and financial year.
        'purchase_order' => 'PO',
        // GRN entry (27/10/2026): GRN/26-27/0001 per unit and year, taken only when the amount matches.
        'grn' => 'GRN',
    ];

    public function next(string $type, Unit $unit, FinancialYear $year): string
    {
        return $this->reserve($type, $unit, $year, 1)[0];
    }

    /**
     * Take $count numbers in one step (an import reserves its whole block at once, decision C12).
     * The series row is locked first and only inserted when it is missing: inserting first made
     * parallel saves deadlock (database review DB-M1).
     */
    public function reserve(string $type, Unit $unit, FinancialYear $year, int $count): array
    {
        $key = ['document_type' => $type, 'unit_id' => $unit->id, 'financial_year_id' => $year->id];

        $row = DB::table('number_series')->where($key)->lockForUpdate()->first();
        if (! $row) {
            DB::table('number_series')->insertOrIgnore($key + [
                'prefix' => self::PREFIXES[$type],
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('number_series')->where($key)->lockForUpdate()->first();
        }

        DB::table('number_series')->where('id', $row->id)->update([
            'next_number' => $row->next_number + $count,
            'updated_at' => now(),
        ]);

        return array_map(
            fn ($n) => sprintf('%s/%s/%04d', $row->prefix, $year->code, $n),
            range($row->next_number, $row->next_number + $count - 1),
        );
    }
}
