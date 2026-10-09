<?php

namespace App\Http\Requests\Admin\Role;

use App\Models\Role;
use App\Services\Role\RoleService;
use App\Support\Permissions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    /**
     * Roles decide what everybody else may do, so this is checked here as
     * well as in RoleController: somebody who may not manage roles gets a
     * 403 before anything they sent is even validated.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules(null);
    }

    /**
     * Shared with UpdateRoleRequest - the only difference is which role (if
     * any) the name check ignores.
     *
     * Only delegable catalog keys are accepted: a submitted `roles.manage`,
     * `users.manage` or unknown key rejects the whole request, so it can
     * never be stored (Role::syncPermissions() also drops them, as a second
     * line of defence). The slug is not an input at all: it is generated
     * from the name, never chosen by the browser.
     *
     * @return array<string, mixed>
     */
    public static function sharedRules(?Role $ignore): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail) use ($ignore) {
                    // The slug is made from the name, so it needs something to make it from.
                    if (Str::slug($value) === '') {
                        $fail(__('The name must contain at least one English letter or number.'));

                        return;
                    }

                    if (app(RoleService::class)->nameIsTaken($value, $ignore)) {
                        $fail(__('A role with this name (or one that reads the same) already exists.'));
                    }
                },
            ],
            // At most one entry per permission there is, so the list can't be padded to waste work.
            'permissions' => ['nullable', 'array', 'max:'.count(Permissions::keys(delegableOnly: true))],
            'permissions.*' => ['string', Rule::in(Permissions::keys(delegableOnly: true))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::sharedMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function sharedMessages(): array
    {
        return [
            'permissions.*.in' => __('One of the selected permissions is not valid.'),
            'permissions.*.string' => __('One of the selected permissions is not valid.'),
        ];
    }
}
