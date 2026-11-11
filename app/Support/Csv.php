<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV download and import. Files are UTF-8 with a BOM so Excel opens them with the right characters. */
class Csv
{
    public const MAX_ROWS = 2000;

    /** Text that a spreadsheet would run as a formula: =, @, tab, CR, or + / - followed by something other than a digit. */
    private const FORMULA = '/^(?:[=@\t\r]|[+-](?![0-9]))/';

    /** $rows may be any iterable (a lazy collection or cursor), so large downloads are streamed. */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'safe'], $headers));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], array_values((array) $row)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cell value for a download. Text that Excel would treat as a formula gets a leading
     * apostrophe (CSV formula injection, review M2). Numbers and +91 phone numbers are kept as they are.
     */
    public static function safe($value): string
    {
        $value = (string) ($value ?? '');

        return preg_match(self::FORMULA, $value) ? "'".$value : $value;
    }

    /** Undo safe() on import, so a downloaded file can be imported again unchanged. */
    public static function unsafe(string $value): string
    {
        return str_starts_with($value, "'") && preg_match(self::FORMULA, substr($value, 1)) ? substr($value, 1) : $value;
    }

    /**
     * Read an uploaded CSV into rows keyed by the header names (lower case, trimmed).
     * Each row also gets "_row", its line number in the file, for error messages.
     * Files saved by Excel as ANSI (Windows-1252) are converted to UTF-8 (review M3).
     */
    public static function read(UploadedFile $file, array $requiredHeaders): array
    {
        $content = (string) file_get_contents($file->getRealPath());
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), fgetcsv($handle) ?: []);
        $missing = array_diff(array_map('mb_strtolower', $requiredHeaders), $headers);
        if ($missing) {
            fclose($handle);
            throw ValidationException::withMessages(['file' => 'The file is missing these columns: '.implode(', ', $missing).'. Download the template to see the layout.']);
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $line++;
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $row = ['_row' => $line];
            foreach ($headers as $i => $h) {
                if ($h !== '') {
                    $row[$h] = self::unsafe(trim((string) ($cells[$i] ?? '')));
                }
            }
            $rows[] = $row;
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);
                throw ValidationException::withMessages(['file' => 'A file can have at most '.self::MAX_ROWS.' rows.']);
            }
        }
        fclose($handle);

        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'The file has no rows to import.']);
        }

        return $rows;
    }

    /** dd/mm/yyyy for CSV and PDF. */
    public static function date($date): string
    {
        return $date ? $date->format(config('nursery.date_format')) : '';
    }
}
