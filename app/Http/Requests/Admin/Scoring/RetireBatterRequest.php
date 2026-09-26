<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Frozen S02 rules 4/5 — shape validation only; eligibility (the player
 * must currently be at the crease) is re-verified inside
 * ScoringEventService::retireBatter().
 */
class RetireBatterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_player_id' => ['required', 'integer'],
            'type' => ['required', 'string', Rule::in(['hurt', 'out'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
