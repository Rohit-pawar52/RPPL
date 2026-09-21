<?php

namespace App\Http\Requests\Admin\Announcement;

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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'The end date/time must be after the start date/time.',
        ];
    }
}
