<?php

namespace App\Http\Requests\Admin\DataCleanup;

use Illuminate\Foundation\Http\FormRequest;

class DeleteNotificationSendsRequest extends FormRequest
{
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
            'before_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
