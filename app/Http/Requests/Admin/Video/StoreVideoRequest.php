<?php

namespace App\Http\Requests\Admin\Video;

use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(Video::STATUSES)],
            'priority' => ['required', 'integer', 'min:1', 'max:9999'],
            // Laravel's file max: rule is in kilobytes; the configured
            // limit (config/videos.php) is in megabytes.
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm', 'max:'.self::maxUploadKilobytes()],
            'thumbnail' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::videoMessages();
    }

    public static function maxUploadKilobytes(): int
    {
        return (int) config('videos.max_upload_mb') * 1024;
    }

    /**
     * Shared with UpdateVideoRequest so both forms word the size/type
     * errors in MB rather than the raw kilobyte value the rule uses.
     *
     * @return array<string, string>
     */
    public static function videoMessages(): array
    {
        return [
            'video.max' => __('The video must not be larger than :max MB.', ['max' => (int) config('videos.max_upload_mb')]),
            'video.mimetypes' => __('The video must be an MP4 or WebM file.'),
            'thumbnail.max' => __('The thumbnail must not be larger than 2 MB.'),
        ];
    }
}
