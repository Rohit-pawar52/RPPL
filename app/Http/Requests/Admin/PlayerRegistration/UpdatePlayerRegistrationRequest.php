<?php

namespace App\Http\Requests\Admin\PlayerRegistration;

use App\Models\PlayerRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRegistrationRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in PlayerRegistrationController
     * via $this->authorize() (PlayerRegistrationPolicy), so this stays
     * true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank payment_reference input must clear the column (the
     * business explicitly wants it optional and correctable), so an
     * empty submitted value is normalized to null here rather than
     * relying on how "nullable" happens to treat an empty string —
     * that keeps validated()['payment_reference'] deterministic
     * (always present, either a trimmed string or null) instead of
     * depending on whether the field was present in the raw request.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payment_reference')) {
            $this->merge([
                'payment_reference' => $this->filled('payment_reference')
                    ? trim($this->string('payment_reference'))
                    : null,
            ]);
        }
    }

    /**
     * Deliberately does NOT validate edition_id/player_id/
     * registration_number/document paths: a registration's identity
     * and its guest-submitted documents are immutable/read-only once
     * created (see the Phase 3.4 and Phase 3.39D reports). The edit
     * form never submits those fields, and since $request->validated()
     * only returns keys defined here, any of them a client tampered in
     * is silently ignored rather than trusted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', 'string', Rule::in(PlayerRegistration::PAYMENT_STATUSES)],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'registered_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
