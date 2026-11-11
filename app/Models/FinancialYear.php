<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

// Financial year 1 April to 31 March (decision D20). Code is "26-27".
class FinancialYear extends Model
{
    protected $fillable = ['code', 'starts_on', 'ends_on', 'is_current'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    public static function startYearFor(CarbonInterface $date): int
    {
        return $date->month >= 4 ? $date->year : $date->year - 1;
    }

    /**
     * The financial year a date falls in, created on first use. Remembered for the request and
     * created race-safe with createOrFirst, so the first users on 1 April never get a duplicate-key
     * error (architecture review A6). The nursery:financial-years command also creates next year ahead.
     */
    public static function forDate(?CarbonInterface $date = null): self
    {
        $date ??= now();
        $start = self::startYearFor($date);
        $code = self::codeFor($start);
        $memo = app('nursery.financial-years');

        return $memo[$code] ??= static::where('code', $code)->first() ?? static::createOrFirst(['code' => $code], [
            'starts_on' => Carbon::create($start, 4, 1),
            'ends_on' => Carbon::create($start + 1, 3, 31),
            'is_current' => $start === self::startYearFor(now()),
        ]);
    }

    /** "26-27" for the year that starts on 1 April 2026. */
    public static function codeFor(int $startYear): string
    {
        return substr((string) $startYear, 2).'-'.substr((string) ($startYear + 1), 2);
    }

    /** "2026-27", as shown on the FY chip. */
    public function label(): string
    {
        return $this->starts_on->year.'-'.substr((string) $this->ends_on->year, 2);
    }
}
