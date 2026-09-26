<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 6 — shape validation only; the awarded team must be
 * one of the match's own two teams, re-verified inside
 * ScoringEventService::awardPenaltyRuns().
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
            'runs' => ['required', 'integer', 'min:1', 'max:20'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
