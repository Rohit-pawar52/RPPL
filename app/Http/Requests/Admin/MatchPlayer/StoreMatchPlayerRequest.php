<?php

namespace App\Http\Requests\Admin\MatchPlayer;

use App\Models\GameMatch;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatchPlayerRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in MatchPlayerController via
     * $this->authorize() (MatchPlayerPolicy), so this stays true to
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
        /** @var GameMatch $match */
        $match = $this->route('match');

        return [
            'team_player_id' => [
                'required',
                'integer',
                'exists:team_players,id',
                // A team_player can be selected at most once per match —
                // do not rely on UI filtering alone for this.
                Rule::unique('match_players', 'team_player_id')
                    ->where(fn ($query) => $query->where('match_id', $match->id)),
                function ($attribute, $value, $fail) use ($match) {
                    $teamPlayer = TeamPlayer::with('playerRegistration.player')->find($value);

                    if (! $teamPlayer) {
                        return; // already caught by the 'exists' rule
                    }

                    // THE core rule of this phase: the team_player must
                    // belong to one of the two edition_teams actually
                    // participating in this match.
                    if (! in_array($teamPlayer->edition_team_id, [$match->edition_team_a_id, $match->edition_team_b_id], true)) {
                        $fail('The selected player does not belong to either team in this match.');

                        return;
                    }

                    if (! $teamPlayer->playerRegistration->player->is_active) {
                        $fail('The selected player is not available for match selection.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_player_id.exists' => 'The selected squad player does not exist.',
            'team_player_id.unique' => 'This player is already selected for this match.',
        ];
    }
}
