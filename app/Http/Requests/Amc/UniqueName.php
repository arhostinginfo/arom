<?php

namespace App\Http\Requests\Amc;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * "This name cannot repeat", ignoring upper / lower case on every database (MySQL compares names without case already,
 * SQLite in the tests does not), so "smart work" is refused when "Smart Work" is in the list.
 */
final class UniqueName
{
    public static function rule(string $table, ?int $ignore, string $message, string $column = 'name'): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($table, $ignore, $message, $column) {
            $taken = DB::table($table)->whereRaw('LOWER('.$column.') = ?', [mb_strtolower((string) $value)])
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists();
            if ($taken) {
                $fail($message);
            }
        };
    }
}
