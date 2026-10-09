<?php

namespace App\Http\Requests\Admin\MatchPlayer;

use App\Models\GameMatch;
use App\Models\MatchPlayer;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk Playing XI selection — one submission replaces a team's entire
 * Playing XI (frozen S02 rule 1: always exactly 11), never 11
 * individual add requests. The active-player check lives here, not in
 * MatchPlayerService::syncPlayingXi() — see that method's docblock for
 * why: an already-selected player who later became inactive must still
 * be re-submittable as part of an otherwise-unchanged selection.
 */
class SyncMatchPlayersRequest extends FormRequest
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
            'edition_team_id' => [
                'required',
                'integer',
                Rule::in([$match->edition_team_a_id, $match->edition_team_b_id]),
            ],
            'team_player_ids' => ['required', 'array', 'size:11'],
            'team_player_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('team_players', 'id')->where('edition_team_id', $this->input('edition_team_id')),
                function ($attribute, $value, $fail) use ($match) {
                    $teamPlayer = TeamPlayer::with('playerRegistration.player')->find($value);

                    if (! $teamPlayer) {
                        return; // already caught by the 'exists' rule
                    }

                    $alreadySelected = MatchPlayer::query()
                        ->where('match_id', $match->id)
                        ->where('team_player_id', $teamPlayer->id)
                        ->exists();

                    // An already-selected player stays eligible even if
                    // they later became inactive (mirrors the existing
                    // "selection remains visible after player becomes
                    // inactive" rule) — only a NEWLY added player must
                    // currently be active.
                    if (! $alreadySelected && ! $teamPlayer->playerRegistration->player->is_active) {
                        $fail(__('One or more newly selected players are not available for match selection.'));
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
            'team_player_ids.size' => __('Exactly 11 players must be selected.'),
            'team_player_ids.*.exists' => __('One or more selected players do not belong to this team\'s squad.'),
            'team_player_ids.*.distinct' => __('The same player cannot be selected twice.'),
        ];
    }
}
