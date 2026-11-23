<?php

namespace App\Services\Amc;

use Illuminate\Http\UploadedFile;

/**
 * Image work for AMC uploads (21/11/2026), with PHP's GD extension: several images chosen together become one collage
 * JPEG (a grid of config('amc.collage.columns') columns, each tile scaled to at most tile_width pixels wide), a single
 * image is saved again as a JPEG of at most config('amc.single_max') pixels. Without GD nothing is changed: callers
 * check available() and keep the files as they came. Nothing here throws on a bad image; it returns null instead.
 */
class AmcImages
{
    public static function available(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagejpeg');
    }

    /** JPEG bytes of one collage of $files (in the order chosen), or null when GD is missing or no image could be read. */
    public static function collage(array $files): ?string
    {
        if (! self::available()) {
            return null;
        }
        $cfg = config('amc.collage');
        $cols = max(1, (int) $cfg['columns']);
        $tileW = (int) $cfg['tile_width'];

        // Tile sizes first (from the image headers), so the canvas is made once and each image is read only when drawn.
        $tiles = [];
        foreach ($files as $file) {
            $info = @getimagesize(self::path($file));
            if (! $info || $info[0] < 1 || $info[1] < 1) {
                continue;
            }
            // A phone photo stored sideways (orientation tag 6 or 8) is drawn turned upright, so its width and height swap
            // (re-test 21/11/2026: the tile was sized from the sideways header and the photo came out squashed).
            [$iw, $ih] = abs(self::angle(self::path($file))) === 90 ? [$info[1], $info[0]] : [$info[0], $info[1]];
            $w = min($tileW, $iw);
            $tiles[] = ['file' => $file, 'w' => $w, 'h' => (int) max(1, round($ih * $w / $iw))];
        }
        if (! $tiles) {
            return null;
        }
        $cols = min($cols, count($tiles));
        $rows = array_chunk($tiles, $cols);
        $gap = 8;
        $width = $cols * $tileW + ($cols + 1) * $gap;
        $height = $gap;
        foreach ($rows as $row) {
            $height += max(array_column($row, 'h')) + $gap;
        }

        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        $y = $gap;
        foreach ($rows as $row) {
            $x = $gap;
            foreach ($row as $t) {
                $img = self::open(self::path($t['file']));
                if ($img) {
                    // Centred in its column.
                    imagecopyresampled($canvas, $img, $x + intdiv($tileW - $t['w'], 2), $y, 0, 0, $t['w'], $t['h'], imagesx($img), imagesy($img));
                    imagedestroy($img);
                }
                $x += $tileW + $gap;
            }
            $y += max(array_column($row, 'h')) + $gap;
        }

        return self::jpeg($canvas);
    }

    /** JPEG bytes of one image at most $max pixels on its longer side, or null when GD is missing or it cannot be read. */
    public static function resized($file, ?int $max = null): ?string
    {
        if (! self::available()) {
            return null;
        }
        $max ??= (int) config('amc.single_max');
        $img = self::open(self::path($file));
        if (! $img) {
            return null;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $max / max($w, $h));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        // A transparent PNG gets a white background, as JPEG has no transparency.
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return self::jpeg($out);
    }

    /** True when the file is a JPEG or PNG image by its content (not only its name). */
    public static function isImage($file): bool
    {
        $info = @getimagesize(self::path($file));

        return $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
    }

    private static function open(string $path)
    {
        $info = @getimagesize($path);
        if (! $info) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            default => false,
        };
        if ($img && $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $img = self::upright($img, $path);
        }

        return $img ?: null;
    }

    /** Phone photos are often stored sideways with an orientation tag; turn them upright before they are scaled. */
    private static function upright($img, string $path)
    {
        $angle = self::angle($path);
        if ($angle) {
            $turned = imagerotate($img, $angle, 0);
            if ($turned) {
                imagedestroy($img);

                return $turned;
            }
        }

        return $img;
    }

    /** Degrees (for imagerotate) that turn a JPEG with an orientation tag upright: 180, -90, 90, or 0 when none is needed. */
    private static function angle(string $path): int
    {
        $info = @getimagesize($path);
        if (! $info || $info[2] !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return 0;
        }
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    }

    private static function jpeg($img): string
    {
        ob_start();
        imagejpeg($img, null, (int) config('amc.collage.quality'));
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private static function path($file): string
    {
        return $file instanceof UploadedFile ? (string) $file->getRealPath() : (string) $file;
    }
}
