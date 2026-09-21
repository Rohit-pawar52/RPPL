<?php

namespace App\Http\Requests\Admin\ContentPage;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContentPageRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in ContentPageController via
     * $this->authorize('manage-tournament'), so this stays true to
     * avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deliberately no `type` rule at all — it is never an accepted
     * input. Even if a request sends one, ContentPageController never
     * reads it from validated()/all(); the record to update is chosen
     * entirely by the {content_page} route-model-binding, and the
     * update payload is built explicitly from title/content/is_active
     * only.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            // Markdown source — rendered safely for public display via
            // App\Support\MarkdownRenderer. A generous but bounded
            // limit; these are long-form policy/FAQ pages, not short
            // fields.
            'content' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
