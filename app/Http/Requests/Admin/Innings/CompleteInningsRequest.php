<?php

namespace App\Http\Requests\Admin\Innings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 rule 15 — a manual innings completion always needs a
 * reason, so a future reader of the audit trail (InningsService::
 * completeInnings() persists it as innings.completion_reason) can tell
 * why the innings was closed early/differently from the objective
 * automatic-completion conditions.
 */
class CompleteInningsRequest extends FormRequest
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
