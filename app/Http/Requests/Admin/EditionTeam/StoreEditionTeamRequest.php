<?php

namespace App\Http\Requests\Admin\EditionTeam;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEditionTeamRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in EditionTeamController via
     * $this->authorize() (EditionTeamPolicy), so this stays true to
     * avoid duplicating that check.
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
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(fn ($query) => $query->where('is_active', true)),
                // Friendly pre-check for the same rule the database's
                // UNIQUE(edition_id, team_id) constraint enforces.
                Rule::unique('edition_teams', 'team_id')
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
            'edition_id.exists' => 'The selected edition is not currently accepting new team participation.',
            'team_id.exists' => 'The selected team is not available for participation.',
            'team_id.unique' => 'This team is already participating in the selected edition.',
        ];
    }
}
