<?php

namespace App\Http\Requests\Admin\Edition\Concerns;

use Illuminate\Validation\Validator;

/**
 * Shared by StoreEditionRequest/UpdateEditionRequest. Both datetimes are
 * naive datetime-local strings in system.display_timezone; the
 * controller converts them to UTC after validation.
 */
trait ValidatesRegistrationPeriod
{
    /**
     * @return array<string, mixed>
     */
    protected function registrationPeriodRules(): array
    {
        return [
            'registration_opens_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'registration_closes_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'registration_reminder_enabled' => ['nullable', 'boolean'],
            'registration_reminder_minutes_before' => ['nullable', 'required_if:registration_reminder_enabled,1', 'integer', 'min:1', 'max:10080'],
        ];
    }

    protected function validateRegistrationPeriod(Validator $validator): void
    {
        $opensAt = $this->input('registration_opens_at');
        $closesAt = $this->input('registration_closes_at');

        // Same-format, same-timezone strings compare correctly as text.
        if (filled($opensAt) && filled($closesAt)
            && ! $validator->errors()->hasAny(['registration_opens_at', 'registration_closes_at'])
            && $closesAt <= $opensAt) {
            $validator->errors()->add('registration_closes_at', 'Registration must close after it opens.');
        }

        if ($this->boolean('registration_reminder_enabled') && blank($closesAt)) {
            $validator->errors()->add('registration_closes_at', 'Set a registration closing time to use the closing reminder.');
        }
    }
}
