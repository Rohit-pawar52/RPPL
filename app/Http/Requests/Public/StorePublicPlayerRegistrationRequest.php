<?php

namespace App\Http\Requests\Public;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates only the SHAPE/format of a guest submission — never
 * eligibility (open edition, identity conflicts, duplicate
 * registration), which is GuestPlayerRegistrationService's domain-layer
 * job. Deliberately has no fields for payment_status, registration_fee,
 * payment_reference, registration_number, or edition_id: those are
 * entirely server-controlled and never read from this request, even if
 * a malicious client includes them in the POST body.
 */
class StorePublicPlayerRegistrationRequest extends FormRequest
{
    /**
     * This is the public site — no auth/policy exists for a guest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Phone is normalized here (before the format rule runs) so the
     * validated regex applies to the same normalized form the service
     * layer will use for identity resolution/storage — matching common
     * Indian input variants (spaces, hyphens, a leading +91/91) to one
     * consistent 10-digit value. Email is trimmed/lowercased the same
     * way. See Player::normalizePhone()/normalizeEmail().
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Player::normalizePhone($this->input('phone')),
            'email' => Player::normalizeEmail($this->input('email')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'primary_role' => ['required', 'string', Rule::in(Player::PRIMARY_ROLES)],
            'aadhaar_document' => ['required', 'file', 'mimes:jpeg,jpg,png,pdf', 'max:4096'],
            'payment_proof' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 10-digit Indian mobile number.',
        ];
    }
}
