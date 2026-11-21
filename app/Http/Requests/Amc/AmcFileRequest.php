<?php

namespace App\Http\Requests\Amc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Upload files to one box of an AMC contract: up to config('amc.max_files') files of the box's types, each at most
 * config('amc.max_file_kb') KB and together at most config('amc.max_total_kb') KB. Both the name and the content must
 * be of an allowed type (a text file renamed .pdf is refused).
 */
class AmcFileRequest extends FormRequest
{
    public static function totalLimitMessage(): string
    {
        return 'The files of one upload can be at most '.(config('amc.max_total_kb') / 1024).' MB in total. Upload them in smaller groups.';
    }
    public function rules(): array
    {
        $types = config('amc.file_categories.'.$this->input('category').'.types', ['pdf']);

        return [
            'category' => ['required', Rule::in(array_keys(config('amc.file_categories')))],
            'files' => ['required', 'array', 'min:1', 'max:'.config('amc.max_files')],
            'files.*' => ['file', 'mimes:'.implode(',', $types), 'extensions:'.implode(',', $types), 'max:'.config('amc.max_file_kb')],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            $files = array_filter((array) $this->file('files'), fn ($f) => $f instanceof UploadedFile);
            if (array_sum(array_map(fn (UploadedFile $f) => (int) $f->getSize(), $files)) > config('amc.max_total_kb') * 1024) {
                $v->errors()->add('files', self::totalLimitMessage());
            }
        }];
    }

    public function messages(): array
    {
        $types = strtoupper(implode(', ', array_diff(config('amc.file_categories.'.$this->input('category').'.types', ['pdf']), ['jpeg'])));

        return [
            'files.required' => 'Choose at least one file.',
            'files.max' => 'Choose at most '.config('amc.max_files').' files at a time.',
            'files.*.mimes' => 'Each file must be '.$types.'.',
            'files.*.extensions' => 'Each file must be '.$types.'.',
            'files.*.max' => 'Each file can be at most '.(config('amc.max_file_kb') / 1024).' MB.',
            'files.*.uploaded' => 'A file could not be uploaded; it may be larger than the server allows.',
        ];
    }
}
