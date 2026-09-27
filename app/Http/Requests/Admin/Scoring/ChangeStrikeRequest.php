<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 20 — shape validation only; eligibility (batting-team
 * membership, not dismissed/retired out, distinct players, an innings
 * that has actually started) is re-verified inside
 * ScoringEventService::changeStrike() itself.
 */
class ChangeStrikeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'striker_match_player_id' => ['required', 'integer'],
            'non_striker_match_player_id' => ['required', 'integer', 'different:striker_match_player_id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
