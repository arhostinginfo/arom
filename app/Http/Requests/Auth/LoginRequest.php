<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'login email'];
    }

    /** Five tries per minute for one email from one address (decision U2). */
    public function authenticate(): void
    {
        $key = Str::lower($this->input('email')).'|'.$this->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout($this));
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        // The password is checked first, so a wrong password never reveals whether an account is inactive.
        $user = User::where('email', Str::lower($this->input('email')))->first();
        if ($user && ! $user->isActive() && Hash::check((string) $this->input('password'), $user->password)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'This account is inactive. Contact your administrator.']);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'The login email or password is wrong.']);
        }

        RateLimiter::clear($key);
    }
}
