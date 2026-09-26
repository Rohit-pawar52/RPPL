<?php

namespace App\Http\Requests\Admin\DataCleanup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteStaleFcmTokensRequest extends FormRequest
{
    /**
     * A bounded preset list rather than a free-form number of days —
     * same reasoning as the old KEEP_COUNT_OPTIONS this replaces (see
     * NotificationDataCleanupService's docblock): never let a typo'd
     * tiny number wipe almost every real subscriber.
     *
     * @var list<int>
     */
    public const DAYS_OPTIONS = [7, 14, 30, 60, 90, 180, 365];

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
            'days' => ['required', 'integer', Rule::in(self::DAYS_OPTIONS)],
        ];
    }
}
