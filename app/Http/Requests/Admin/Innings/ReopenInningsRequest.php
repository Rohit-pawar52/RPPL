<?php

namespace App\Http\Requests\Admin\Innings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 16 — a mandatory reason, audited via ScoringEvent by
 * InningsService::reopenInnings().
 */
class ReopenInningsRequest extends FormRequest
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
