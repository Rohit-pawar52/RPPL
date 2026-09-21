<?php

namespace App\Http\Requests\Admin\DataCleanup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteFcmTokensRequest extends FormRequest
{
    /**
     * The only retention sizes the admin UI offers — a bounded preset
     * list rather than a free-form number, so this can never be misused
     * to (for example) accidentally type "1" and wipe almost every real
     * subscriber.
     *
     * @var list<int>
     */
    public const KEEP_COUNT_OPTIONS = [10, 50, 100, 200, 500, 1000, 2000];

    /**
     * Authorization is handled explicitly in DataCleanupController via
     * $this->authorize('manage-tournament'), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keep_count' => ['required', 'integer', Rule::in(self::KEEP_COUNT_OPTIONS)],
        ];
    }
}
