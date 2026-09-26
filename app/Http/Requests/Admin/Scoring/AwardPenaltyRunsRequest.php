<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 6, corrected per the penalty-run-accounting follow-up
 * — shape validation only; the awarded team must be one of the match's
 * own two teams, re-verified inside ScoringEventService::
 * awardPenaltyRuns(). No "runs" field: this action always awards the
 * standard international-law 5-run penalty
 * (ScoringEventService::STANDARD_PENALTY_RUNS) — RPPL has no
 * established need for an arbitrary custom amount, so none is offered.
 */
class AwardPenaltyRunsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'awarded_team_id' => ['required', 'integer', 'exists:edition_teams,id'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
