<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUpiSettingsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in SettingsController (the
     * settings.manage permission), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A UPI ID (virtual payment address) is name@bank — letters, digits,
     * dots, dashes and underscores either side of the @. It ends up inside
     * the public form's "pay with a UPI app" link, so it must never carry
     * anything else.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'upi_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-]{2,64}@[A-Za-z][A-Za-z0-9.\-]{1,63}$/'],
            'upi_qr' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_upi_qr' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'upi_id.regex' => 'Enter a valid UPI ID, like name@bank.',
        ];
    }
}
