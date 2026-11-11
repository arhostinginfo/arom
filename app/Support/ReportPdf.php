<?php

namespace App\Support;

/**
 * Row cap of report PDFs (decision C9, revised 27/10/2026). dompdf keeps every table cell in memory, so the cap
 * depends on the number of cells, not only rows: measured with the rows in tables of 50 (pdf/report.blade.php),
 * about 6,000 cells peak at 160–190 MB (857 × 7 = 156 MB, 352 × 17 = 182 MB, 240 × 25 = 188 MB), well under the
 * 512 MB memory limit. Narrow reports keep up to 2,000 rows; the widest still give at least 200.
 */
class ReportPdf
{
    public const MAX_ROWS = 2000;

    public const MIN_ROWS = 200;

    public const MAX_CELLS = 6000;

    /** Rows of a report with $columns columns that fit in one PDF. */
    public static function maxRows(int $columns): int
    {
        return max(self::MIN_ROWS, min(self::MAX_ROWS, intdiv(self::MAX_CELLS, max(1, $columns))));
    }

    /** The message when a PDF would be larger: names the cap of this report. */
    public static function tooMany(int $cap): string
    {
        return 'Too many rows for PDF (more than '.number_format($cap).' for this report). Use CSV or narrow the dates.';
    }
}
