<?php

namespace App\Http\Requests\Admin\TeamPlayer;

use App\Models\TeamPlayer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamPlayerRequest extends FormRequest
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
     * Deliberately does NOT validate edition_team_id/player_registration_id:
     * a squad assignment's identity is immutable once created (see the
     * Phase 3.8 report). The edit form never submits those fields, and
     * since $request->validated() only returns keys defined here, any
     * edition_team_id/player_registration_id a client tampered in is
     * silently ignored rather than trusted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var TeamPlayer $teamPlayer */
        $teamPlayer = $this->route('team_player');

        return [
            'jersey_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('team_players', 'jersey_number')
                    ->where(fn ($query) => $query->where('edition_team_id', $teamPlayer->edition_team_id))
                    ->ignore($teamPlayer->id),
            ],
            'role' => ['nullable', 'string', Rule::in(TeamPlayer::ROLES)],
        ];
    }
}
