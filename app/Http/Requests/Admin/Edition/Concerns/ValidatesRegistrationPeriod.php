<?php

namespace App\Http\Requests\Admin\Edition\Concerns;

use App\Models\Edition;
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

    /**
     * Only one edition (season) may be "active" at a time, like the
     * single-open-registration rule: moving a second one to active is
     * refused with a clear message, and never silently completes the other.
     * Only a CHANGE to active is checked, so re-saving an edition that is
     * already active (even in older data that has two) is never blocked.
     */
    protected function validateSingleActiveEdition(Validator $validator, ?Edition $current = null): void
    {
        if ($this->input('status') !== 'active' || $current?->status === 'active') {
            return;
        }

        $other = Edition::where('status', 'active')
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->first();

        if ($other) {
            $validator->errors()->add(
                'status',
                "\"{$other->name}\" is already the active edition. Mark it completed (or upcoming) first, then activate this one."
            );
        }
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
