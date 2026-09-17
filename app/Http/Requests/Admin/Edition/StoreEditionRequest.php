<?php

namespace App\Http\Requests\Admin\Edition;

use App\Models\Edition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEditionRequest extends FormRequest
{
    /**
     * Authorization is handled by EditionController's authorizeResource()
     * (EditionPolicy), so this stays true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A plain HTML checkbox sends nothing at all when unchecked, so
     * registration_open is normalized to an explicit true/false here —
     * otherwise an unchecked box would simply be absent from
     * validated()/the update array rather than actually turning it off.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['registration_open' => $this->boolean('registration_open')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100', 'unique:editions,year'],
            'status' => ['required', 'string', Rule::in(Edition::STATUSES)],
            'registration_open' => ['required', 'boolean'],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * V1 invariant (Phase 3.39C): at most one Edition may have
     * registration_open = true at a time, so the public guest
     * registration form always has an unambiguous single target. Opening
     * a second Edition while another is already open is rejected with a
     * clear message — the admin must close the first one first; this
     * never silently closes it for them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('registration_open')) {
                return;
            }

            if (Edition::where('registration_open', true)->exists()) {
                $validator->errors()->add(
                    'registration_open',
                    'Another edition already has public registration open. Close it first before opening a new one.'
                );
            }
        });
    }
}
