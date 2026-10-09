<?php

namespace App\Http\Requests\Admin\TeamPlayer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Add players" on a team's squad page: the ticked registrations (add[ID])
 * and an optional sold amount for each (amount[ID]). Which of them can
 * really be added (same season, active player, not already in a squad) is
 * decided by TeamPlayerService::addPlayers().
 */
class AddSquadPlayersRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in SeasonSquadController via the
     * policies, so this stays true to avoid duplicating that check.
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
            'add' => ['required', 'array', 'min:1', 'max:200'],
            'add.*' => ['in:1'],
            'amount' => ['nullable', 'array'],
            'amount.*' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'add.required' => __('Tick at least one player to add.'),
            'add.min' => __('Tick at least one player to add.'),
            'amount.*.numeric' => __('A sold amount must be a number.'),
            'amount.*.min' => __('A sold amount cannot be negative.'),
        ];
    }

    /**
     * registration id => sold amount (blank = none), for the ticked players only.
     *
     * @return array<int, ?string>
     */
    public function amounts(): array
    {
        $amounts = [];

        foreach (array_keys($this->validated('add')) as $registrationId) {
            $amount = $this->validated('amount')[$registrationId] ?? null;
            $amounts[(int) $registrationId] = filled($amount) ? (string) $amount : null;
        }

        return $amounts;
    }
}
