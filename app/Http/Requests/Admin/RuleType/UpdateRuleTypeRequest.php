<?php

namespace App\Http\Requests\Admin\RuleType;

use App\Models\RuleType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleTypeRequest extends FormRequest
{
    /**
     * Asks RuleTypePolicy before anything is validated: a validation message such as "already taken"
     * must not tell somebody who may not manage this what already exists. RuleTypeController checks again.
     */
    public function authorize(): bool
    {
        $target = $this->route('rule_type');

        return $target instanceof RuleType && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * Same as StoreRuleTypeRequest except the slug uniqueness check
     * ignores the rule type being edited.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var RuleType|null $ruleType */
        $ruleType = $this->route('rule_type');

        return StoreRuleTypeRequest::sharedRules($ruleType?->id);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoreRuleTypeRequest::sharedMessages();
    }
}
