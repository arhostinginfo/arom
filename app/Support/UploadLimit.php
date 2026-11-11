<?php

namespace App\Support;

use App\Services\AppSettings;

/**
 * Maximum size of a file upload on a form (22/10/2026), set by an Admin in Admin > Config (default 1 MB).
 * A form adopts it by using rule() and message() in its Form Request and hint() / kb() (data-max-kb) on its
 * file input. Used now by the Prospect CSV import and the enquiry documents; other uploads keep their own limit
 * until they are switched over.
 */
final class UploadLimit
{
    public const KEY = 'upload_max_kb';

    /** Allowed range in Admin > Config, in MB, with at most one decimal (0.5, 1, 2.5 ... 50). */
    public const MIN_MB = 0.5;

    public const MAX_MB = 50;

    public static function kb(): int
    {
        return max(1, (int) app(AppSettings::class)->get(self::KEY));
    }

    /** Always in MB with at most one decimal: "1 MB", "0.5 MB", "1.5 MB". */
    public static function label(?int $kb = null): string
    {
        return self::mb($kb ?? self::kb()).' MB';
    }

    /** 1024 => "1", 1536 => "1.5". */
    public static function mb(int $kb): string
    {
        return rtrim(rtrim(number_format($kb / 1024, 1, '.', ''), '0'), '.');
    }

    /** Laravel rule, e.g. "max:1024" (kilobytes). */
    public static function rule(): string
    {
        return 'max:'.self::kb();
    }

    public static function message(): string
    {
        return 'The file must not be larger than '.self::label().'.';
    }

    /** Hint under a file input, e.g. "Max 1 MB". */
    public static function hint(): string
    {
        return 'Max '.self::label();
    }

    /** PHP's own limits in KB (upload_max_filesize, post_max_size); a larger setting cannot work until they are raised. */
    public static function phpLimits(): array
    {
        return ['upload_max_filesize' => self::iniKb('upload_max_filesize'), 'post_max_size' => self::iniKb('post_max_size')];
    }

    private static function iniKb(string $name): ?int
    {
        $v = trim((string) ini_get($name));
        if ($v === '' || $v === '0' || $v === '-1') {
            return null; // no limit
        }
        $n = (float) $v;

        return (int) match (strtoupper(substr($v, -1))) {
            'G' => $n * 1024 * 1024,
            'M' => $n * 1024,
            'K' => $n,
            default => $n / 1024,
        };
    }
}
