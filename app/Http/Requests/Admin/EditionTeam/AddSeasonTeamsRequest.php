<?php

namespace App\Http\Requests\Admin\EditionTeam;

use Illuminate\Foundation\Http\FormRequest;

class AddSeasonTeamsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in SeasonTeamController via the
     * EditionTeamPolicy, so this stays true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the shape is checked here; which of the teams can really be
     * added (active, not already in the season) is decided by
     * EditionTeamService::addTeams(), so a stale form never fails whole.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'team_ids' => ['required', 'array', 'min:1', 'max:200'],
            'team_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_ids.required' => 'Tick at least one team to add.',
            'team_ids.min' => 'Tick at least one team to add.',
        ];
    }
}
