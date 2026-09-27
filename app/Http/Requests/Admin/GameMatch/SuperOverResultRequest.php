<?php

namespace App\Http\Requests\Admin\GameMatch;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 7 — the winning team plus a mandatory reason/note
 * (which may optionally describe the Super Over's own score as free
 * text). The winner must be one of the match's own two teams, re-
 * verified inside MatchResultService::recordSuperOverResult().
 */
class SuperOverResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'winner_team_id' => ['required', 'integer', 'exists:edition_teams,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
