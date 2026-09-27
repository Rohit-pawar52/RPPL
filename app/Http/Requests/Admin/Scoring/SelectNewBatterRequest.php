<?php

namespace App\Http\Requests\Admin\Scoring;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 completion rule C (New Batter workflow) — shape validation
 * only; eligibility (currently-required, Playing XI membership, not
 * dismissed/retired out, not the surviving batter) is re-verified
 * inside ScoringEventService::selectNewBatter(). No reason required —
 * a normal continuation of play, not a correction.
 */
class SelectNewBatterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_player_id' => ['required', 'integer'],
        ];
    }
}
