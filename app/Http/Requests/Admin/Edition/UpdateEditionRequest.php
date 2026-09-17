<?php

namespace App\Http\Requests\Admin\Edition;

use App\Models\Edition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEditionRequest extends FormRequest
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
     * otherwise unchecking it would leave it silently unchanged rather
     * than actually turning it off (validated() would simply omit the
     * key entirely).
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
        /** @var Edition $edition */
        $edition = $this->route('edition');

        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => [
                'required',
                'integer',
                'digits:4',
                'min:2000',
                'max:2100',
                Rule::unique('editions', 'year')->ignore($edition->id),
            ],
            'status' => ['required', 'string', Rule::in(Edition::STATUSES)],
            'registration_open' => ['required', 'boolean'],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * Same V1 invariant as StoreEditionRequest: at most one Edition may
     * have registration_open = true at a time. Excludes this edition's
     * own current row so re-saving an already-open edition doesn't trip
     * the check against itself.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('registration_open')) {
                return;
            }

            /** @var Edition $edition */
            $edition = $this->route('edition');

            if (Edition::where('registration_open', true)->where('id', '!=', $edition->id)->exists()) {
                $validator->errors()->add(
                    'registration_open',
                    'Another edition already has public registration open. Close it first before opening a new one.'
                );
            }
        });
    }
}
