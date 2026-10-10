<?php

namespace App\Http\Requests\Admin\GameMatch;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 17 — needs `matches.reopen` (admin, and the scorer by default;
 * enforced by GameMatchPolicy::reopenResult() in the controller, not here)
 * with a mandatory reason,
 * audited via ScoringEvent by MatchResultService::reopenMatch().
 */
class ReopenMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
