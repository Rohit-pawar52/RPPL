<?php

namespace App\Http\Requests\Public;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Validates only the SHAPE of a status-lookup submission — registration
 * number format and phone format. Deliberately has no Rule::exists() or
 * any other existence check: whether the pair actually matches a
 * registration is PlayerRegistrationStatusLookupService's job, so a
 * malformed value and a well-formed-but-nonexistent value can never be
 * told apart by different validation-error behavior (see Phase 3.39E's
 * enumeration-avoidance requirement).
 */
class StatusLookupPlayerRegistrationRequest extends FormRequest
{
    /**
     * This is the public site — no auth/policy exists for a guest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Registration numbers are compared case-insensitively for
     * usability (trim + uppercase), and phone reuses the EXACT same
     * Player::normalizePhone() helper Phase 3.39C already established —
     * never a second phone-equivalence algorithm.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'registration_number' => Str::upper(trim((string) $this->input('registration_number'))),
            'phone' => Player::normalizePhone($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:30', 'regex:/^RPPL-\d{4}-\d{6}$/'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.regex' => 'Enter your Registration Number exactly as given, e.g. RPPL-2026-000125.',
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ];
    }
}
