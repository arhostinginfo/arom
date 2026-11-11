<?php

namespace App\Http\Requests\Admin;

use App\Support\Roles;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Add / edit user. Required fields (decision U1): Name, Login email, Mobile,
 * Password (new users), Role, Units allowed, Status. Extra permissions are optional.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only an Admin can change an Admin user, including their password (decision U8); a non-Admin
        // changes only users whose rights they hold themselves (decision U9).
        if ($this->editing() && ! Access::canManage($this->user(), $this->editing())) {
            return false;
        }

        return $this->user()->can($this->editing() ? 'users.edit' : 'users.add');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'mobile' => self::normaliseMobile((string) $this->input('mobile')),
            'permissions' => array_values((array) $this->input('permissions', [])),
        ]);
    }

    public function rules(): array
    {
        $id = $this->editing()?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($id)],
            'mobile' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'mobile')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'units' => ['required', 'array', 'min:1'],
            'units.*' => ['integer', Rule::exists('units', 'id')],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Access::all())],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => 'Enter a 10 digit Indian mobile number, for example 98220 00001.',
            'mobile.unique' => 'Another user, current or deleted, already has this mobile number.',
            'email.unique' => 'Another user, current or deleted, already uses this login email.',
            'units.required' => 'Choose at least one unit.',
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'login email', 'units' => 'units allowed'];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $actor = $this->user();
            $user = $this->editing();

            // Decision U8: only an Admin gives the Admin role; others give only permissions they have
            // and cannot change their own role.
            if (! $actor->isAdmin()) {
                if ($this->input('role') === Roles::ADMIN) {
                    $v->errors()->add('role', 'Only an Admin can give the Admin role.');
                }
                if ($user && $user->is($actor) && $this->input('role') !== $user->roleName()) {
                    $v->errors()->add('role', 'You cannot change your own role.');
                }
                // Decision U9: the role's rights count too, not only the extra permissions.
                if ($this->input('role') !== Roles::ADMIN && ($notHeld = Access::notGrantable($actor, Access::ofRole($this->input('role'))))) {
                    $v->errors()->add('role', 'You can only give a role whose rights you have yourself. '.$this->input('role').' also gives: '.implode(', ', Access::labels(array_slice($notHeld, 0, 8))).(count($notHeld) > 8 ? ' and '.(count($notHeld) - 8).' more' : '').'.');
                }
                if ($notHeld = Access::notGrantable($actor, Access::withViews($this->input('permissions', [])))) {
                    $v->errors()->add('permissions', 'You can only give permissions you have yourself. Not allowed: '.implode(', ', Access::labels($notHeld)).'.');
                }
            }

            if (! $user) {
                return;
            }
            if ($user->is($actor) && $this->input('status') !== UserStatus::Active->value) {
                $v->errors()->add('status', 'You cannot make your own user inactive.');
            }
            // The last-active-Admin guard only matters when this user is an active Admin now (B3).
            $leavesAdmin = $this->input('role') !== Roles::ADMIN || $this->input('status') !== UserStatus::Active->value;
            if ($leavesAdmin && $user->hasRole(Roles::ADMIN) && $user->isActive() && User::activeAdminCount() <= 1) {
                $v->errors()->add('role', 'This is the only active Admin. Make another user an active Admin first.');
            }
        }];
    }

    private function editing(): ?User
    {
        return $this->route('user');
    }

    public static function normaliseMobile(string $mobile): string
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return substr($digits, 2);
        }
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
