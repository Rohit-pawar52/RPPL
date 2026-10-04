<?php

namespace App\Http\Requests\Admin\Advertisement;

use App\Models\Advertisement;
use App\Services\Advertisement\AdvertisementService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and editing a sponsor ad. Whether the media
 * file is required is the only difference (see the two subclasses).
 */
abstract class SaveAdvertisementRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in AdvertisementController via
     * $this->authorize() (AdvertisementPolicy).
     */
    public function authorize(): bool
    {
        return true;
    }

    abstract protected function mediaIsRequired(): bool;

    /**
     * The ad being edited, or null when creating.
     */
    protected function current(): ?Advertisement
    {
        $route = $this->route('advertisement');

        return $route instanceof Advertisement ? $route : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isVideo = $this->input('media_type') === Advertisement::MEDIA_VIDEO;

        // Laravel's file max: rule is in kilobytes; config is in megabytes.
        $mediaRules = $isVideo
            ? ['file', 'mimetypes:video/mp4,video/webm', 'max:'.((int) config('ads.max_video_mb') * 1024)]
            : ['image', 'mimes:jpeg,jpg,png,webp', 'max:'.((int) config('ads.max_image_mb') * 1024)];

        return [
            'title' => ['required', 'string', 'max:255'],
            'tier' => ['required', Rule::in(array_keys(Advertisement::TIERS))],
            // Only a Normal sponsor chooses its spot; for the others it is ignored.
            'format' => ['nullable', Rule::in(array_keys(Advertisement::FORMATS))],
            'media_type' => [
                'required',
                Rule::in(Advertisement::MEDIA_TYPES),
                // Mini sponsors are small logos in a strip — images only.
                Rule::prohibitedIf(fn () => $this->input('tier') === Advertisement::TIER_MINI && $this->input('media_type') === Advertisement::MEDIA_VIDEO),
            ],
            'media' => [$this->mediaIsRequired() ? 'required' : 'nullable', ...$mediaRules],
            'poster' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(Advertisement::STATUSES)],
            'weight' => ['required', 'integer', 'min:1', 'max:'.Advertisement::MAX_WEIGHT],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media_type.prohibited' => 'Mini sponsors are small logos shown together at the bottom of the page, so they must be an image.',
            'media.max' => 'The file must not be larger than '.($this->input('media_type') === Advertisement::MEDIA_VIDEO ? (int) config('ads.max_video_mb') : (int) config('ads.max_image_mb')).' MB.',
            'media.mimetypes' => 'The video must be an MP4 or WebM file.',
            'poster.max' => 'The preview image must not be larger than 2 MB.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                // Switching an existing ad between image and video needs a
                // new file; the old one is the wrong kind.
                $current = $this->current();
                if ($current && ! $this->hasFile('media') && $current->media_type !== $this->input('media_type')) {
                    $validator->errors()->add('media', 'Upload a new '.$this->input('media_type').' file — the current file is a '.$current->media_type.'.');

                    return;
                }

                $clash = app(AdvertisementService::class)->mainSponsorClash(
                    $this->input('tier'),
                    $this->input('status'),
                    $this->input('starts_on') ?: null,
                    $this->input('ends_on') ?: null,
                    $current?->id,
                );

                if ($clash) {
                    $validator->errors()->add('tier', app(AdvertisementService::class)->clashMessage($clash));
                }
            },
        ];
    }
}
