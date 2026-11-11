<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use App\Services\PageSetupService;
use App\Support\Gst;
use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;

/** Company master (D21): Name always; GSTIN, PAN, Address, Phone, Email as set in Admin › Page setup; an optional logo (23/10/2026). */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('masters.edit');
    }

    protected function prepareForValidation(): void
    {
        $up = fn ($f) => strtoupper(preg_replace('/\s+/', '', (string) $this->input($f))) ?: null;
        $this->merge(['gstin' => $up('gstin'), 'pan' => $up('pan'), 'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null]);
    }

    public function rules(): array
    {
        return app(PageSetupService::class)->rules('company', [
            'name' => ['required', 'string', 'max:150'],
            'gstin' => ['nullable', 'regex:'.Gst::GSTIN],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:191'],
        ]) + [
            // Company logo for the quotation PDF (23/10/2026): an image of a type the PDF can draw, up to the
            // Admin > Config upload size. Not a Page setup field, so it is always optional.
            // A logo is capped at 2 MB and 4000 px even if the general upload limit is higher, so PDFs stay light (23/10/2026).
            'logo' => ['nullable', 'file', 'max:'.self::logoKb(), 'dimensions:max_width=4000,max_height=4000', 'extensions:'.implode(',', Company::logoTypes()), 'mimes:'.implode(',', Company::logoTypes())],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        $type = 'Upload the logo as a '.Company::logoTypesLabel().' image.';

        return ['gstin.regex' => 'Enter a 15 character GSTIN, for example 27AAACK1234F1Z5.', 'pan.regex' => 'Enter a 10 character PAN, for example AAACK1234F.',
            'logo.max' => 'The file must not be larger than '.UploadLimit::label(self::logoKb()).'.', 'logo.dimensions' => 'The logo must be at most 4000 × 4000 pixels.', 'logo.extensions' => $type, 'logo.mimes' => $type, 'logo.uploaded' => UploadLimit::message()];
    }

    public function attributes(): array
    {
        return ['gstin' => 'GSTIN', 'pan' => 'PAN', 'logo' => 'company logo'];
    }

    /** The logo size limit in KB: the general upload limit, but never more than 2 MB. */
    public static function logoKb(): int
    {
        return min(UploadLimit::kb(), 2048);
    }
}
