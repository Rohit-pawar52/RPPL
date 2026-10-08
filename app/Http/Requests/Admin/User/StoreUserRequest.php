<?php

namespace App\Http\Requests\Admin\User;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Asks UserPolicy (users.manage - never delegable, so administrators
     * only) before anything is validated. Validation errors such as "this
     * email is already taken" would otherwise tell any signed-in panel user
     * which addresses have an account. UserController checks again.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
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
