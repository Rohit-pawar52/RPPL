<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactSettingsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in SettingsController via
     * $this->authorize('manage-tournament'), so this stays true to avoid
     * duplicating that check.
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
            'email' => ['nullable', 'email', 'max:255'],
            // Deliberately plain string validation, NOT
            // Player::normalizePhone() — these are free-form public
            // contact display values, not a player's registered mobile
            // number, so that domain-specific normalization doesn't
            // apply here.
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
