<?php

namespace App\Http\Requests\Admin\News;

use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in NewsController via
     * $this->authorize() (NewsPolicy).
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
        return self::sharedRules();
    }

    /**
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', Rule::in(News::STATUSES)],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            // Naive datetime-local string in system.display_timezone;
            // NewsService converts it to UTC.
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'images' => ['nullable', 'array', 'max:'.News::MAX_IMAGES],
            // `image` rejects SVG by default; mimes pins the exact set.
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function imageMessages(): array
    {
        return [
            'images.max' => __('You can attach at most :max images to a news item.', ['max' => News::MAX_IMAGES]),
            'images.*.image' => __('Each image must be a JPG, PNG or WebP file.'),
            'images.*.mimes' => __('Each image must be a JPG, PNG or WebP file.'),
            'images.*.max' => __('Each image must not be larger than 5 MB.'),
            'images.*.uploaded' => __('One of the images could not be uploaded. It may be larger than the server allows.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::imageMessages();
    }
}
