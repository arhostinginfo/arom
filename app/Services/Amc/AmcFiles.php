<?php

namespace App\Services\Amc;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/** The private disk of AMC files and photos (config amc.files_disk): "local" on the development PC, a private bucket in production. */
class AmcFiles
{
    public static function diskName(): string
    {
        return (string) config('amc.files_disk', 'local');
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::diskName());
    }

    /** Write bytes, or fail: the disk does not throw on a failed write ('throw' => false), so no row may point at a missing file. */
    public static function put(string $path, string $bytes): string
    {
        if (! self::disk()->put($path, $bytes)) {
            throw new \RuntimeException('Could not store '.$path);
        }

        return $path;
    }
}
