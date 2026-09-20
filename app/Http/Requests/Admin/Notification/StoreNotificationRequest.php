<?php

namespace App\Http\Requests\Admin\Notification;

use App\Models\Notification;
use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
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
     * created_by is deliberately absent — it is always the authenticated
     * admin (set in the controller), never accepted from request input.
     */
    protected function prepareForValidation(): void
    {
        $actionUrl = trim((string) $this->input('action_url', ''));

        $this->merge([
            'title' => trim((string) $this->input('title', '')),
            'message' => trim((string) $this->input('message', '')),
            // A blank submission normalizes to null (valid, "no link") —
            // an invalid, non-blank value must still fail validation
            // below rather than being silently rewritten.
            'action_url' => $actionUrl === '' ? null : $actionUrl,
        ]);
    }

    /**
     * title's max:150 matches the notifications.title column exactly.
     * message's max:500 is a deliberate, generous-but-bounded admin-
     * authoring limit for push-notification content — not the DB
     * column's TEXT capacity, which is a storage detail, not a content
     * guideline. action_url's max:255 matches the column's default
     * string length; its shape (internal path only) is enforced by
     * Notification::isValidActionUrl(), never a generic URL validator,
     * since external URLs are intentionally never allowed.
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
