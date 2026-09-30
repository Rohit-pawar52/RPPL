<?php

namespace App\Http\Requests\Admin\Photo;

use App\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhotoRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in PhotoController via
     * $this->authorize() (PhotoPolicy).
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Photo::STATUSES)],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            // `image` rejects SVG by default; mimes pins the exact set.
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function photoMessages(): array
    {
        return [
            'photo.max' => 'The photo must not be larger than 5 MB.',
            'photo.image' => 'The photo must be a JPG, PNG or WebP image.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP image.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::photoMessages();
    }
}
