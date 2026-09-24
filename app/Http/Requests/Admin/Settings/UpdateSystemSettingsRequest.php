<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in SettingsController via
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
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', 'string', 'max:10'],
            // max:10 characters (not bytes) — "₹" is a single multi-byte
            // character, so a plain max:10 comfortably fits it.
            'currency_symbol' => ['required', 'string', 'max:10'],
            // Laravel's own built-in `timezone` rule validates against
            // PHP's real timezone identifier list — this is a DISPLAY
            // preference only (see SettingsRegistry's own docblock) and
            // is never used to change config('app.timezone') or the
            // runtime timezone in this phase.
            'display_timezone' => ['required', 'timezone'],
            'committee_minimum_contribution' => ['required', 'numeric', 'min:0'],
        ];
    }
}
