<?php

namespace App\Http\Requests\Admin\News;

use App\Models\News;
use App\Models\NewsImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNewsRequest extends FormRequest
{
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
            ...StoreNewsRequest::sharedRules(),
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
        ];
    }

    /**
     * The 10-image cap applies to the total after this edit: what is
     * already attached, minus what is being removed, plus what is new.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('images') || $validator->errors()->has('remove_images')) {
                return;
            }

            /** @var News $news */
            $news = $this->route('news');

            $existing = NewsImage::where('news_id', $news->id)->count();
            $removed = NewsImage::where('news_id', $news->id)
                ->whereIn('id', (array) $this->input('remove_images', []))
                ->count();
            $added = count((array) $this->file('images', []));

            if ($existing - $removed + $added > News::MAX_IMAGES) {
                $validator->errors()->add('images', 'You can attach at most '.News::MAX_IMAGES.' images to a news item.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreNewsRequest::imageMessages();
    }
}
