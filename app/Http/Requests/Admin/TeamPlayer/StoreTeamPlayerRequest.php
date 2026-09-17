<?php

namespace App\Http\Requests\Admin\TeamPlayer;

use App\Models\EditionTeam;
use App\Models\PlayerRegistration;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeamPlayerRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in TeamPlayerController via
     * $this->authorize() (TeamPlayerPolicy), so this stays true to
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
            'edition_team_id' => [
                'required',
                'integer',
                'exists:edition_teams,id',
                function ($attribute, $value, $fail) {
                    $editionTeam = EditionTeam::with(['edition', 'team'])->find($value);

                    if (! $editionTeam) {
                        return;
                    }

                    if ($editionTeam->edition->status === 'completed') {
                        $fail('The selected edition is not currently accepting new squad assignments.');
                    }

                    if (! $editionTeam->team->is_active) {
                        $fail('The selected team is not available for squad assignment.');
                    }
                },
            ],
            'player_registration_id' => [
                'required',
                'integer',
                'exists:player_registrations,id',
                // team_players.player_registration_id is UNIQUE on its
                // own (not scoped) — a registration can join at most one
                // squad, ever. This mirrors that exactly.
                Rule::unique('team_players', 'player_registration_id'),
                function ($attribute, $value, $fail) {
                    $registration = PlayerRegistration::with('player')->find($value);

                    if (! $registration) {
                        return;
                    }

                    if (! $registration->player->is_active) {
                        $fail('The selected player is not available for squad assignment.');
                    }

                    // THE core rule of this phase: the registration's
                    // edition must match the selected team's edition.
                    $editionTeam = EditionTeam::find($this->input('edition_team_id'));

                    if ($editionTeam && $registration->edition_id !== $editionTeam->edition_id) {
                        $fail('The selected player is not registered for the same edition as the selected team.');
                    }
                },
            ],
            'jersey_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('team_players', 'jersey_number')
                    ->where(fn ($query) => $query->where('edition_team_id', $this->input('edition_team_id'))),
            ],
            'role' => ['nullable', 'string', Rule::in(TeamPlayer::ROLES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'edition_team_id.exists' => 'The selected team participation record does not exist.',
            'player_registration_id.exists' => 'The selected player registration does not exist.',
            'player_registration_id.unique' => 'This player is already assigned to a squad.',
            'jersey_number.unique' => 'This jersey number is already taken on the selected team.',
        ];
    }
}
