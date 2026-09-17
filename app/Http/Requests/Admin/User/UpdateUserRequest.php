<?php

namespace App\Http\Requests\Admin\User;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
     * password is deliberately optional here — blank means "keep the
     * current password" (enforced in the controller, which only
     * includes it in the update payload when filled).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * The self-lockout and last-active-admin rules from this phase's
     * spec are cross-field/data-dependent business rules, not simple
     * per-field validation — an `after()` hook is the natural place for
     * them, exactly like every other FormRequest in this project that
     * needs to compare submitted data against existing database state.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var User $target */
            $target = $this->route('user');
            $actingUser = $this->user();

            $wantsActive = $this->boolean('is_active');
            $newRole = Role::find($this->input('role_id'));
            $isDeactivating = $target->is_active && ! $wantsActive;
            $isDemotingFromAdmin = $target->role?->slug === 'admin' && $newRole?->slug !== 'admin';

            if ($actingUser->id === $target->id) {
                if ($isDeactivating) {
                    $validator->errors()->add('is_active', 'You cannot deactivate your own account.');
                }

                if ($isDemotingFromAdmin) {
                    $validator->errors()->add('role_id', 'You cannot remove your own administrator role.');
                }

                return;
            }

            if ($target->role?->slug === 'admin' && ($isDeactivating || $isDemotingFromAdmin) && $this->isLastActiveAdmin($target)) {
                $field = $isDeactivating ? 'is_active' : 'role_id';
                $validator->errors()->add($field, 'At least one active administrator account must remain.');
            }
        });
    }

    private function isLastActiveAdmin(User $target): bool
    {
        return User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->where('is_active', true)
            ->where('id', '!=', $target->id)
            ->doesntExist();
    }
}
