<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Server-side paging and sorting for every list (rule in docs/ARCHITECTURE.md, decision P1).
 * Page size comes from ?per_page (25 by default, up to 100) and sort from ?sort and ?dir.
 * Only the columns a list allows can be sorted on, and each of them has an index.
 */
class ListQuery
{
    public const PER_PAGE = [25, 50, 100];

    public static function perPage(Request $request): int
    {
        $n = (int) $request->query('per_page');

        return in_array($n, self::PER_PAGE, true) ? $n : self::PER_PAGE[0];
    }

    /**
     * Apply ?sort / ?dir. $columns maps the URL name to the database column, e.g. ['name' => 'users.name'].
     * The first entry is the default sort. The primary key is always added last so paging is stable.
     *
     * @return array{0: string, 1: string} the sort name and direction in use, for the column headers
     */
    public static function sort(Builder $query, Request $request, array $columns, string $defaultDir = 'asc', string $key = 'id'): array
    {
        $sort = (string) $request->query('sort');
        if (! isset($columns[$sort])) {
            $sort = array_key_first($columns);
        }
        $dir = $request->query('dir');
        $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : $defaultDir;

        $query->orderBy($columns[$sort], $dir);
        if ($columns[$sort] !== $key) {
            $query->orderBy($key, $dir);
        }

        return [$sort, $dir];
    }
}
