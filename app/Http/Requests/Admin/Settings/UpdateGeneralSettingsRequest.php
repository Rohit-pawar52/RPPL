<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
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
            'application_name' => ['required', 'string', 'max:150'],
            'short_name' => ['required', 'string', 'max:30'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'button_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // The `image` validation rule has no ico support at all
            // (verified against Laravel's own ValidatesAttributes —
            // its mimes list is jpg/jpeg/png/gif/bmp/webp only), so the
            // logo (never an icon file in practice) uses it, while the
            // favicon deliberately does NOT — it uses the extension/MIME
            // based `mimes` rule instead, which is honest about only
            // checking the file's extension/MIME, not decoding it as an
            // image the way `image` does.
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'favicon' => ['nullable', 'mimes:ico,png', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ];
    }
}
