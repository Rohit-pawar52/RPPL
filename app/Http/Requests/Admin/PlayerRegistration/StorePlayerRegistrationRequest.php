<?php

namespace App\Http\Requests\Admin\PlayerRegistration;

use App\Models\PlayerRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerRegistrationRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => [
                'required',
                'integer',
                Rule::exists('editions', 'id')->where(fn ($query) => $query->where('status', '!=', 'completed')),
            ],
            'player_id' => [
                'required',
                'integer',
                Rule::exists('players', 'id')->where(fn ($query) => $query->where('is_active', true)),
                // Friendly pre-check for the same rule the database's
                // UNIQUE(edition_id, player_id) constraint enforces.
                Rule::unique('player_registrations', 'player_id')
                    ->where(fn ($query) => $query->where('edition_id', $this->input('edition_id'))),
            ],
            'payment_status' => ['required', 'string', Rule::in(PlayerRegistration::PAYMENT_STATUSES)],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'registered_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'edition_id.exists' => 'The selected edition is not currently accepting registrations.',
            'player_id.exists' => 'The selected player is not available for registration.',
            'player_id.unique' => 'This player is already registered for the selected edition.',
        ];
    }
}
