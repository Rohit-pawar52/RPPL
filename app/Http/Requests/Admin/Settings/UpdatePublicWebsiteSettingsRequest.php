<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePublicWebsiteSettingsRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'footer_text' => ['nullable', 'string', 'max:500'],
        ];
    }
}
