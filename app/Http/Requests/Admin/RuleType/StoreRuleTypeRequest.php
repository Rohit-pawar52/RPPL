<?php

namespace App\Http\Requests\Admin\RuleType;

use App\Models\RuleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuleTypeRequest extends FormRequest
{
    /**
     * Asks RuleTypePolicy before anything is validated: a validation message such as "already taken"
     * must not tell somebody who may not manage this what already exists. RuleTypeController checks again.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', RuleType::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules(null);
    }

    /**
     * Shared with UpdateRuleTypeRequest — the only difference is which
     * row (if any) the slug uniqueness check ignores.
     *
     * is_active is rendered as an Active/Inactive <select>, which always
     * submits a definite value, so it can safely be "required" (unlike a
     * checkbox, which is simply absent when unticked).
     *
     * @return array<string, mixed>
     */
    public static function sharedRules(?int $ignoreId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('rule_types', 'slug')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::sharedMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function sharedMessages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers and single hyphens (e.g. cricket-rules).',
        ];
    }
}
