<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Company master (D21, S6): one row, printed on tax invoices. Its GSTIN state decides CGST + SGST or IGST.
class Company extends Model
{
    protected $fillable = ['name', 'gstin', 'pan', 'address', 'phone', 'email'];

    /** The one company row (an unsaved blank one until Admin fills it in). */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? new self(['name' => config('app.name')]);
    }

    /** GSTIN state code, e.g. "27" for Maharashtra; null without a GSTIN. */
    public function stateCode(): ?string
    {
        return $this->gstin ? substr($this->gstin, 0, 2) : null;
    }

    /**
     * File types the logo may be (23/10/2026). The PDF library draws JPG by itself but needs PHP's GD extension for
     * PNG and WebP, so those are offered only where GD is on. SVG is not offered: dompdf draws it unreliably.
     */
    public static function logoTypes(): array
    {
        return array_merge(['jpg', 'jpeg'], function_exists('imagecreatefrompng') ? ['png'] : [], function_exists('imagecreatefromwebp') ? ['webp'] : []);
    }

    /** "JPG, PNG or WebP" (or just "JPG" without GD). */
    public static function logoTypesLabel(): string
    {
        $names = array_values(array_unique(array_map(fn ($t) => ['jpeg' => 'JPG', 'webp' => 'WebP'][$t] ?? strtoupper($t), self::logoTypes())));
        $last = array_pop($names);

        return $names ? implode(', ', $names).' or '.$last : $last;
    }

    /**
     * The logo as a data: URI (23/10/2026), so the quotation PDF embeds it without a public URL. Null without a logo,
     * when the file is missing, or (for the PDF) when this server cannot draw its type: the PDF then prints as before.
     */
    public function logoDataUri(bool $forPdf = true): ?string
    {
        if (! $this->logo_path) {
            return null;
        }
        $ext = strtolower(pathinfo($this->logo_path, PATHINFO_EXTENSION));
        if ($forPdf && ! in_array($ext, self::logoTypes(), true)) {
            return null;
        }
        try {
            $bytes = \App\Services\DocumentService::disk()->get($this->logo_path);
        } catch (\Throwable) {
            return null;
        }

        return $bytes ? 'data:'.(['png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'image/jpeg').';base64,'.base64_encode($bytes) : null;
    }

    /**
     * The logo for the PDF header (23/10/2026): src plus a width and height that fit 60 x 200 px and keep its shape,
     * worked out here because dompdf does not apply max-height to images reliably. Null when there is nothing to print.
     */
    public function pdfLogo(): ?array
    {
        if (! $src = $this->logoDataUri()) {
            return null;
        }
        $size = @getimagesizefromstring(base64_decode(substr($src, strpos($src, ',') + 1)));
        if (! $size || ! $size[0] || ! $size[1]) {
            return null;
        }
        $scale = min(1, 60 / $size[1], 200 / $size[0]);

        return ['src' => $src, 'width' => round($size[0] * $scale, 1), 'height' => round($size[1] * $scale, 1)];
    }

    public function activityLabel(): string
    {
        return 'company details';
    }
}
