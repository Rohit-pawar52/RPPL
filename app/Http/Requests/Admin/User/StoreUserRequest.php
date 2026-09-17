<?php

namespace App\Http\Requests\Admin\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in UserController via
     * $this->authorize() (UserPolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * is_active is deliberately absent — a newly created account simply
     * takes the users.is_active column's DB default (active), matching
     * every other master-entity CREATE form in this project.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
