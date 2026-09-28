<?php

namespace App\Http\Requests\Admin\RuleType;

use App\Models\RuleType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleTypeRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in RuleTypeController via
     * $this->authorize() (RuleTypePolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
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
