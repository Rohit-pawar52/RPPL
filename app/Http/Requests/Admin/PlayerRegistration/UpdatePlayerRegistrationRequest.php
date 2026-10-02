<?php

namespace App\Http\Requests\Admin\PlayerRegistration;

use App\Models\PlayerRegistration;
use App\Support\DriveLink;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRegistrationRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in PlayerRegistrationController
     * via $this->authorize() (PlayerRegistrationPolicy), so this stays
     * true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank payment_reference input must clear the column (the
     * business explicitly wants it optional and correctable), so an
     * empty submitted value is normalized to null here rather than
     * relying on how "nullable" happens to treat an empty string —
     * that keeps validated()['payment_reference'] deterministic
     * (always present, either a trimmed string or null) instead of
     * depending on whether the field was present in the raw request.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('payment_reference')) {
            $this->merge([
                'payment_reference' => $this->filled('payment_reference')
                    ? trim($this->string('payment_reference'))
                    : null,
            ]);
        }
    }

    /**
     * Deliberately does NOT validate edition_id/player_id/
     * registration_number/document paths: a registration's identity
     * and its guest-submitted documents are immutable/read-only once
     * created (see the Phase 3.4 and Phase 3.39D reports). The edit
     * form never submits those fields, and since $request->validated()
     * only returns keys defined here, any of them a client tampered in
     * is silently ignored rather than trusted.
     *
     * The form-answer fields (age, village, ..., the Drive links) are
     * what a CSV import fills in from a Google Form sheet; they are
     * editable here so an imported registration can be corrected or
     * completed later. The two links are shown as clickable anchors, so
     * they must be a plain https Google link (see DriveLink).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $driveLink = function (string $attribute, mixed $value, Closure $fail) {
            if (! DriveLink::isValid((string) $value)) {
                $fail('The :attribute must be a secure Google Drive link (starting with https://).');
            }
        };

        return [
            'payment_status' => ['required', 'string', Rule::in(PlayerRegistration::PAYMENT_STATUSES)],
            'registration_fee' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'registered_at' => ['nullable', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'age' => ['nullable', 'integer', 'between:'.PlayerRegistration::AGE_MIN.','.PlayerRegistration::AGE_MAX],
            'village' => ['nullable', 'string', 'max:100'],
            'tehsil' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'submitted_utr' => ['nullable', 'string', 'max:100'],
            'photo_url' => ['nullable', 'string', 'max:'.DriveLink::MAX_LENGTH, $driveLink],
            'payment_proof_url' => ['nullable', 'string', 'max:'.DriveLink::MAX_LENGTH, $driveLink],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'submitted_utr' => 'UTR',
            'photo_url' => 'photo link',
            'payment_proof_url' => 'payment screenshot link',
        ];
    }
}
