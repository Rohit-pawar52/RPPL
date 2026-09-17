<?php

namespace App\Http\Requests\Admin\GameMatch;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGameMatchRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in GameMatchController via
     * $this->authorize() (GameMatchPolicy), so this stays true to avoid
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
            'edition_id' => [
                'required',
                'integer',
                Rule::exists('editions', 'id')->where(fn ($query) => $query->where('status', '!=', 'completed')),
            ],
            'edition_team_a_id' => [
                'required',
                'integer',
                'exists:edition_teams,id',
                'different:edition_team_b_id',
                function ($attribute, $value, $fail) {
                    $this->validateEditionTeamEligibility($value, $fail);
                },
            ],
            'edition_team_b_id' => [
                'required',
                'integer',
                'exists:edition_teams,id',
                function ($attribute, $value, $fail) {
                    $this->validateEditionTeamEligibility($value, $fail);
                },
            ],
            'venue_id' => [
                'nullable',
                'integer',
                Rule::exists('venues', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'match_stage' => ['nullable', 'string', Rule::in(GameMatch::STAGES)],
            'overs_per_innings' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scheduled_at' => ['required', 'date'],
            'match_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('matches', 'match_number')
                    ->where(fn ($query) => $query->where('edition_id', $this->input('edition_id'))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'edition_id.exists' => 'The selected edition is not currently accepting new fixtures.',
            'edition_team_a_id.different' => 'Team A and Team B must be different teams.',
            'venue_id.exists' => 'The selected venue is not available for scheduling.',
            'match_number.unique' => 'This match number is already used in the selected edition.',
        ];
    }

    /**
     * Checks that the given edition_team belongs to the request's
     * edition_id and that its underlying team is active. Shared by
     * both edition_team_a_id and edition_team_b_id.
     */
    protected function validateEditionTeamEligibility(mixed $value, \Closure $fail): void
    {
        $editionTeam = EditionTeam::with('team')->find($value);

        if (! $editionTeam) {
            return; // already caught by the 'exists' rule
        }

        if ((int) $editionTeam->edition_id !== (int) $this->input('edition_id')) {
            $fail('The selected team does not belong to the chosen edition.');

            return;
        }

        if (! $editionTeam->team->is_active) {
            $fail('The selected team is not available for scheduling.');
        }
    }
}
