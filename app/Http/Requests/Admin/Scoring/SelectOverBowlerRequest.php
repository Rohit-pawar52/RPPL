<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 completion rule D (New Over Bowler workflow) — shape
 * validation only; eligibility (currently required, Playing XI
 * membership, not the previous over's bowler) is re-verified inside
 * ScoringEventService::selectOverBowler(). No reason required — a
 * normal continuation of play, not a correction.
 */
class SelectOverBowlerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bowler_match_player_id' => ['required', 'integer'],
        ];
    }
}
