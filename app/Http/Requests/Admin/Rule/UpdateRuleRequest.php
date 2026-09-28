<?php

namespace App\Http\Requests\Admin\Rule;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in RuleController via
     * $this->authorize() (RulePolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same as StoreRuleRequest plus remove_image — a checkbox (only shown
     * when the rule currently has an image) that clears the existing
     * image without uploading a replacement.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...StoreRuleRequest::sharedRules(),
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreRuleRequest::sharedMessages();
    }
}
