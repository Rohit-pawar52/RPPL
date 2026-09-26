<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 completion rule E (Mid-Over Bowler Change) — shape
 * validation only; eligibility (currently required, Playing XI
 * membership, different from the current bowler) is re-verified inside
 * ScoringEventService::changeBowlerMidOver(). Reason is mandatory — this
 * is a correction/exception (e.g. injury), unlike the normal-flow
 * Select Over Bowler action.
 */
class ChangeBowlerMidOverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bowler_match_player_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
