<?php

namespace App\Http\Requests\Admin\Role;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Same reasoning as StoreRoleRequest: refuse before validating. RolePolicy
     * also refuses the admin role, which always holds every permission.
     */
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * Same as StoreRoleRequest except the name check ignores the role being
     * edited, so a role may keep (or re-case) its own name.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return StoreRoleRequest::sharedRules($role instanceof Role ? $role : null);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreRoleRequest::sharedMessages();
    }
}
