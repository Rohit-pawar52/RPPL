<?php

namespace App\Http\Requests\Admin\Announcement;

use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
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
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            // Only actually applied by the controller when this
            // announcement's notification hasn't been dispatched yet
            // (see AnnouncementController::update()) — validated
            // unconditionally here regardless, the same way every other
            // field is.
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
