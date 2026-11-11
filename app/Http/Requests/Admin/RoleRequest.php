<?php

namespace App\Http\Requests\Admin;

use App\Support\Roles;
use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

// Create / edit a role on Access rights (decisions D19, U4, U5).
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('role') ? 'access-rights.edit' : 'access-rights.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'permissions' => array_values((array) $this->input('permissions', [])),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\pN][\pL\pN &\/-]*$/u', Rule::unique('roles', 'name')->ignore($this->route('role')?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Access::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Use letters, numbers, spaces, & / or - only.',
            'name.unique' => 'A role with this name already exists.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'role name'];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // Decision U10: Admin and Sales keep their names.
            $current = $this->route('role')?->name;
            if (Roles::isFixed($current) && $this->input('name') !== $current) {
                $v->errors()->add('name', $current === Roles::SALES
                    ? 'The Sales role cannot be renamed, because quotations pick their sales person from it.'
                    : 'The Admin role cannot be renamed.');
            }
            // Decision U8: a non-Admin can add to a role only permissions they have themselves.
            $actor = $this->user();
            if (! $actor->isAdmin()) {
                $already = $this->route('role')?->permissions->pluck('name')->all() ?? [];
                $added = array_diff(Access::withViews($this->input('permissions', [])), $already);
                if ($notHeld = array_diff($added, Access::grantable($actor))) {
                    $v->errors()->add('permissions', 'You can only give permissions you have yourself. Not allowed: '.implode(', ', Access::labels(array_values($notHeld))).'.');
                }
            }
        }];
    }
}
