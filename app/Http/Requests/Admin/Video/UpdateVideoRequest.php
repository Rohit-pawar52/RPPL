<?php

namespace App\Http\Requests\Admin\Video;

use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVideoRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in VideoController via
     * $this->authorize() (VideoPolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same as StoreVideoRequest except `video` is nullable: when no new
     * file is uploaded, the existing stored video is kept.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Video::STATUSES)],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.StoreVideoRequest::maxUploadKilobytes()],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreVideoRequest::videoMessages();
    }
}
