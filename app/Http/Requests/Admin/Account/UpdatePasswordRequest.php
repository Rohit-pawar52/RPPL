<?php

namespace App\Http\Requests\Admin\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePasswordRequest extends FormRequest
{
    /**
     * Every signed-in, active user may change their own password: the route's auth and active
     * middleware already guarantee that, and the user can only ever change their own.
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
            'current_password' => ['required', 'string', 'current_password'],
            // max: bcrypt only ever looks at the first 72 bytes; this just bounds the payload.
            'password' => ['required', 'string', 'min:'.config('admin.password_min_length'), 'max:255', 'confirmed', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.different' => 'The new password must be different from your current password.',
        ];
    }
}
