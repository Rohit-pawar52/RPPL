<?php

namespace App\Http\Requests\Admin\Notification;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in NotificationController via
     * $this->authorize() (NotificationPolicy), so this stays true to
     * avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * created_by is deliberately absent — it is set only once, at
     * creation, and is never part of an update payload (see
     * NotificationController::update()).
     */
    protected function prepareForValidation(): void
    {
        $actionUrl = trim((string) $this->input('action_url', ''));

        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'message' => trim((string) $this->input('message', '')),
            'action_url' => $actionUrl === '' ? null : $actionUrl,
        ]);
    }

    /**
     * Identical shape to StoreNotificationRequest — a Notification's
     * content fields are validated the same way whether being created
     * or edited; see that class's docblock for the reasoning behind each
     * bound.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:500'],
            'action_url' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, $value, \Closure $fail) {
                    if (! Notification::isValidActionUrl($value)) {
                        $fail('The action URL must be an internal RPPL path starting with a single "/" (e.g. /matches/12) — external links are not allowed.');
                    }
                },
            ],
        ];
    }
}
