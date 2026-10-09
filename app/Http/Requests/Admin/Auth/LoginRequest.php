<?php

namespace App\Http\Requests\Admin\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Anyone may attempt to log in; authorization to specific admin
     * areas is handled separately by Gates once authenticated.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * Brute-force protection is handled by the "login" rate limiter on
     * the route itself, so this only needs to perform the attempt and
     * fail with a single generic message that does not reveal whether
     * the email, the password, or the account's active status was the
     * reason — an inactive account must not be distinguishable from a
     * wrong password at this point.
     *
     * Adding is_active to the credentials array makes the underlying
     * Eloquent user provider constrain its user lookup with
     * WHERE is_active = 1, so an inactive account simply fails to match
     * like any other invalid login — no separate check is needed here.
     */
    public function authenticate(): void
    {
        // The email is compared in lower case on both sides: MySQL ignores case in a comparison already,
        // but PostgreSQL (Render) and SQLite do not, and "Admin@Example.com" must not be a different
        // login from "admin@example.com". (The login rate limiter lower-cases the email for its key too.)
        $email = Str::lower(trim($this->string('email')->toString()));

        $credentials = [
            'email' => fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]),
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }
    }
}
