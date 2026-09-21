<?php

namespace App\Http\Requests\Admin\DataCleanup;

use Illuminate\Foundation\Http\FormRequest;

class DeleteNotificationsRequest extends FormRequest
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
     * before_date must be a real date no later than today — a future
     * date would silently mean "delete everything ever created", which
     * is never what a typo'd date picker value should do.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'before_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
