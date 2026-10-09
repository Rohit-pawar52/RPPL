<?php

namespace App\Http\Requests\Admin\TeamPlayer;

use App\Models\TeamPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Saves the whole squad table of one team: players[teamPlayerId][...] with
 * the optional jersey number, role and sold amount of each row.
 */
class UpdateSquadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank cell means "none", never an empty string in the database.
     */
    protected function prepareForValidation(): void
    {
        $players = collect($this->input('players', []))
            ->map(fn ($row) => is_array($row) ? array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $row) : $row)
            ->all();

        $this->merge(['players' => $players]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'players' => ['required', 'array', 'max:200'],
            'players.*.jersey_number' => ['nullable', 'integer', 'min:1', 'max:999'],
            'players.*.role' => ['nullable', 'string', Rule::in(TeamPlayer::ROLES)],
            'players.*.sold_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * Two players of a team cannot wear the same number.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $numbers = collect($this->input('players', []))->pluck('jersey_number')->filter()->map(fn ($n) => (int) $n);
            $duplicates = $numbers->duplicates()->unique();

            if ($duplicates->isNotEmpty()) {
                $validator->errors()->add('players', __('Jersey number :numbers is used by more than one player of this team.', ['numbers' => $duplicates->implode(', ')]));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'players.*.jersey_number.integer' => __('A jersey number must be a whole number.'),
            'players.*.sold_amount.numeric' => __('A sold amount must be a number.'),
        ];
    }
}
