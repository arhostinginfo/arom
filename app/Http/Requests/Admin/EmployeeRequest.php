<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Crm\ProspectRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit an employee (Employee master). Always mandatory: Employee code, Name, Status; the rest as set in Admin > Page setup. */
class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('employee') ? 'masters.edit' : 'masters.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))) ?: null,
            'user_id' => $this->input('user_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;

        return app(\App\Services\PageSetupService::class)->rules('employee', [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\/-]+$/', Rule::unique('employees', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'regex:'.ProspectRequest::PHONE],
            'email' => ['nullable', 'email', 'max:191'],
            'joining_date' => ['nullable', 'date_format:d/m/Y'],
            'address' => ['nullable', 'string', 'max:1000'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('employees', 'user_id')->ignore($id)],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, / and - only.',
            'code.unique' => 'Another employee has this code.',
            'mobile.regex' => 'Enter a phone number of 6 to 20 digits, for example +91 98220 00001.',
            'joining_date.date_format' => 'Enter the date of joining as dd/mm/yyyy.',
            'user_id.unique' => 'This login user is already linked to another employee.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'employee code', 'user_id' => 'login user', 'joining_date' => 'date of joining'];
    }

    /** The date as Y-m-d for the database. */
    public function validatedForSave(): array
    {
        $data = $this->validated();
        // Date of joining hidden in Admin > Page setup: not sent, so the saved date stays as it was.
        if (array_key_exists('joining_date', $data)) {
            $data['joining_date'] = \App\Support\Dates::toDb($data['joining_date']);
        }

        return $data;
    }
}
