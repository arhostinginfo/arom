<?php

namespace App\Support;

/**
 * Numbers and rupee amounts in Indian grouping (1,83,000.30), the same as the quotation page, which uses
 * the browser's en-IN format. PHP's number_format() groups in thousands (183,000.30), and the intl
 * extension is not installed on every server, so the grouping is done here.
 */
class Money
{
    /** 1234567.5 → "12,34,567.50" with 2 decimals, "12,34,568" with none. */
    public static function indian(float|int|string|null $value, int $decimals = 0): string
    {
        $n = round((float) $value, $decimals);
        $sign = $n < 0 ? '-' : '';
        [$int, $frac] = array_pad(explode('.', number_format(abs($n), $decimals, '.', '')), 2, '');
        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest = substr($int, 0, -3);
            $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest).','.$last3;
        }

        return $sign.$int.($decimals > 0 ? '.'.$frac : '');
    }

    /** A quantity with Indian grouping and no trailing zeros: 1,200 or 12.5 (up to 3 decimals). */
    public static function qty(float|int|string|null $value): string
    {
        $s = self::indian($value, 3);

        return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
    }

    /**
     * Amount in words the Indian way, for tax invoices: 120050.5 → "One Lakh Twenty Thousand Fifty and Fifty Paise".
     * $unit and $sub name the currency ("Rupees", "Paise").
     */
    public static function words(float|int|string|null $value, string $unit = 'Rupees', string $sub = 'Paise'): string
    {
        $n = round(abs((float) $value), 2);
        $whole = (int) floor($n);
        $paise = (int) round(($n - $whole) * 100);
        $text = $whole === 0 ? 'Zero' : self::wordsOf($whole);
        $out = $unit.' '.$text;
        if ($paise > 0) {
            $out .= ' and '.self::wordsOf($paise).' '.$sub;
        }

        return $out.' Only';
    }

    private static function wordsOf(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen',
            'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $two = fn (int $x) => $x < 20 ? $ones[$x] : trim($tens[intdiv($x, 10)].' '.$ones[$x % 10]);
        $three = fn (int $x) => trim(($x >= 100 ? $ones[intdiv($x, 100)].' Hundred ' : '').$two($x % 100));
        $parts = [];
        foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$size, $name]) {
            if ($n >= $size) {
                $count = intdiv($n, $size);
                $parts[] = ($count >= 100 && $size === 10000000 ? self::wordsOf($count) : $three($count)).' '.$name;
                $n %= $size;
            }
        }
        if ($n > 0) {
            $parts[] = $three($n);
        }

        return implode(' ', $parts);
    }

    /** "₹12,34,567" (or with decimals). */
    public static function rupees(float|int|string|null $value, int $decimals = 0): string
    {
        return '₹'.self::indian($value, $decimals);
    }
}
