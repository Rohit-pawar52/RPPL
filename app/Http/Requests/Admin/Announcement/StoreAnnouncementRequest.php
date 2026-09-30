<?php

namespace App\Http\Requests\Admin\Announcement;

use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in AnnouncementController via
     * $this->authorize() (AnnouncementPolicy), so this stays true to
     * avoid duplicating that check.
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
            'message' => ['required', 'string', 'max:500'],
            // Both submitted as raw datetime-local strings
            // (Y-m-d\TH:i) — the controller converts them from
            // system.display_timezone to UTC via
            // DisplayTimezoneFormatter::parseFromDisplayTimezone()
            // before storing. Comparing them here as plain strings in
            // the SAME implicit timezone is enough to validate their
            // relative order; no conversion is needed for that.
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Entirely separate from starts_at/ends_at (ticker
            // visibility) — this controls whether/when a Firebase push
            // fires for this announcement.
            'notification_choice' => ['nullable', 'in:none,now,later'],
            'notification_scheduled_at' => [
                'nullable',
                'required_if:notification_choice,later',
                'date_format:Y-m-d\TH:i',
                function ($attribute, $value, $fail) {
                    if ($this->input('notification_choice') !== 'later' || blank($value)) {
                        return;
                    }

                    $parsed = app(DisplayTimezoneFormatter::class)->parseFromDisplayTimezone($value);

                    if ($parsed !== null && $parsed->isPast()) {
                        $fail('The scheduled date/time must be in the future.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'The end date/time must be after the start date/time.',
            'notification_scheduled_at.required_if' => 'Choose a date and time for the scheduled push notification.',
        ];
    }
}
