<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** dd/mm/yyyy on screens and files (OTHER POINTS A18), Y-m-d in the database. */
class Dates
{
    public static function toDb(?string $value): ?string
    {
        return $value ? Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d') : null;
    }

    /** "dd/mm/yyyy" plus an optional "HH:MM" into a database datetime. */
    public static function toDbDateTime(?string $date, ?string $time): ?string
    {
        return $date ? Carbon::createFromFormat('d/m/Y H:i', $date.' '.($time ?: '00:00'))->format('Y-m-d H:i:s') : null;
    }

    public static function show($value): ?string
    {
        return $value?->format(config('nursery.date_format'));
    }
}
