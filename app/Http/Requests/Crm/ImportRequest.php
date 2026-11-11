<?php

namespace App\Http\Requests\Crm;

use App\Support\UploadLimit;
use Illuminate\Foundation\Http\FormRequest;

/** CSV upload for Import on the Prospect and Quotation pages. Who may import is checked by the route. */
class ImportRequest extends FormRequest
{
    /** Limit of the imports not yet on the Admin > Config upload size. */
    public const MAX_KB = 2048;

    /** Imports that use the upload size of Admin > Config (22/10/2026); add a route name to switch another one over. */
    public const USES_UPLOAD_LIMIT = ['crm.prospects.import'];

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', $this->configured() ? UploadLimit::rule() : 'max:'.self::MAX_KB],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to import.',
            'file.mimes' => 'The file must be a CSV file (.csv).',
            'file.max' => $this->configured() ? UploadLimit::message() : 'The file can be at most 2 MB.',
        ];
    }

    private function configured(): bool
    {
        return in_array($this->route()?->getName(), self::USES_UPLOAD_LIMIT, true);
    }
}
