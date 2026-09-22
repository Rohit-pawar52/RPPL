<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingsRequest extends FormRequest
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
            'razorpay_enabled' => ['nullable', 'boolean'],
            'razorpay_mode' => ['required', Rule::in(['test', 'live'])],
            'razorpay_key_id' => ['nullable', 'string', 'max:255'],
            // A blank secret means "keep the existing value" — that's a
            // controller-level decision (only include the key in
            // SettingsService::setMany() when it's non-blank), never
            // encoded as a validation rule here.
            'razorpay_key_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
