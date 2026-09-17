<?php

namespace App\Http\Requests\Admin\GameMatch;

use App\Models\GameMatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordTossRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in MatchFlowController via
     * $this->authorize() (GameMatchPolicy::manageMatchFlow), so this
     * stays true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whether the match is currently in the 'toss' phase (and thus
     * whether toss data may be recorded at all) is a match-state gate,
     * not an input-shape rule — MatchFlowService::canRecordToss()
     * re-checks that, not this request. This only validates the shape
     * of the two submitted values themselves.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GameMatch $match */
        $match = $this->route('match');

        return [
            // Never trust an arbitrary EditionTeam id here — the toss
            // winner must be exactly one of this match's own two
            // participating teams.
            'toss_winner_team_id' => [
                'required',
                'integer',
                Rule::in([$match->edition_team_a_id, $match->edition_team_b_id]),
            ],
            'toss_decision' => ['required', 'string', Rule::in(GameMatch::TOSS_DECISIONS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'toss_winner_team_id.in' => 'The toss winner must be one of the two teams playing this match.',
            'toss_decision.in' => 'The toss decision must be either bat or bowl.',
        ];
    }
}
