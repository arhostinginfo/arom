<?php

namespace App\Http\Requests\Amc;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit an AMC employee: code (blank = next number), name, role, mobile, photo (JPG or PNG) and status. */
class AmcEmployeeRequest extends FormRequest
{
    public const PHOTO_KB = 5120;

    public function authorize(): bool
    {
        return $this->user()->can($this->route('employee') ? 'amc-employee.edit' : 'amc-employee.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => ($c = strtoupper(preg_replace('/\s+/', '', (string) $this->input('code')))) === '' ? null : $c,
            'name' => ($n = preg_replace('/\s+/', ' ', trim((string) $this->input('name')))) === '' ? null : $n,
            'mobile' => ($m = preg_replace('/\D/', '', (string) $this->input('mobile'))) === '' ? null : $m,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return [
            'code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9\/-]*$/', Rule::unique('amc_employees', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100', UniqueName::rule('amc_employees', $id, 'An employee with this name is already in the list.')],
            'role' => ['required', Rule::in(config('amc.employee_roles'))],
            'mobile' => ['nullable', 'digits:10'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'photo' => self::photoRules(false),
        ];
    }

    /** A JPG or PNG photo, checked by its content too. */
    public static function photoRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:'.self::PHOTO_KB];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'This code is already used by another employee.',
            'code.regex' => 'Use capital letters, numbers, "/" and "-" only.',
            'name.unique' => 'An employee with this name is already in the list.',
            'mobile.digits' => 'Enter a 10 digit mobile number.',
            'photo.image' => 'The photo must be a JPG or PNG image.',
            'photo.mimes' => 'The photo must be a JPG or PNG image.',
            'photo.extensions' => 'The photo must be a JPG or PNG image.',
            'photo.max' => 'The photo can be at most 5 MB.',
        ];
    }
}
