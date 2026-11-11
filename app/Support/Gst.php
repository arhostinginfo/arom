<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Party;

/**
 * GST on a tax invoice (D14): CGST + SGST when the customer's GSTIN state is the company's state, IGST when it is
 * another state, and no GST for an export customer (billed in a foreign currency, D15), who is invoiced under LUT.
 * A customer without a GSTIN (unregistered) is taken as in the company's state.
 */
final class Gst
{
    public const INTRA = 'Intra-state';

    public const INTER = 'Inter-state';

    public const EXPORT = 'Export';

    /** GSTIN format: 2-digit state, PAN, entity no, Z, check character. */
    public const GSTIN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

    /** GST state codes (the first two digits of a GSTIN), for "State & code" and "Place of supply" on invoices (02/11/2026). */
    public const STATES = [
        '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab', '04' => 'Chandigarh', '05' => 'Uttarakhand',
        '06' => 'Haryana', '07' => 'Delhi', '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram', '16' => 'Tripura', '17' => 'Meghalaya',
        '18' => 'Assam', '19' => 'West Bengal', '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
        '24' => 'Gujarat', '25' => 'Daman and Diu', '26' => 'Dadra and Nagar Haveli and Daman and Diu', '27' => 'Maharashtra',
        '29' => 'Karnataka', '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu', '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana', '37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory',
    ];

    /** "Maharashtra (27)" for a state code; null without one (02/11/2026). */
    public static function stateLabel(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        return (self::STATES[$code] ?? 'State').' ('.$code.')';
    }

    public static function supplyType(Party $party, Company $company): string
    {
        if ($party->isForeign()) {
            return self::EXPORT;
        }
        $theirs = $party->stateCode();
        $ours = $company->stateCode();

        return $theirs && $ours && $theirs !== $ours ? self::INTER : self::INTRA;
    }

    /** Supply type of a quotation (23/10/2026): by the customer as above; a prospect (no customer yet) is in the company's state. */
    public static function forQuotation(?Party $party): string
    {
        return $party ? self::supplyType($party, Company::current()) : self::INTRA;
    }

    /**
     * Tax of one taxable amount at a tax structure's rates for the supply type, each rounded to paise:
     * ['cgst_rate', 'sgst_rate', 'igst_rate', 'cgst_amount', 'sgst_amount', 'igst_amount'].
     */
    public static function split(float $taxable, string $supply, float $cgst, float $sgst, float $igst): array
    {
        $rates = match ($supply) {
            self::INTRA => [$cgst, $sgst, 0.0],
            self::INTER => [0.0, 0.0, $igst],
            default => [0.0, 0.0, 0.0],
        };

        return [
            'cgst_rate' => $rates[0], 'sgst_rate' => $rates[1], 'igst_rate' => $rates[2],
            'cgst_amount' => round($taxable * $rates[0] / 100, 2),
            'sgst_amount' => round($taxable * $rates[1] / 100, 2),
            'igst_amount' => round($taxable * $rates[2] / 100, 2),
        ];
    }
}
