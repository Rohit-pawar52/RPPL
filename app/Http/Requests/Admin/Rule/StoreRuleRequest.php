<?php

namespace App\Http\Requests\Admin\Rule;

use App\Models\Rule as RuleModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRuleRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::sharedRules();
    }

    /**
     * Shared with UpdateRuleRequest (which adds remove_image on top).
     * The image is optional on both — on update, the existing image is
     * kept unless replaced or explicitly removed.
     *
     * is_important is a checkbox: unticked means it is absent from the
     * request entirely, so it must stay nullable, never required (the
     * controller always writes it explicitly via $request->boolean()).
     *
     * @return array<string, mixed>
     */
    public static function sharedRules(): array
    {
        return [
            'rule_type_id' => ['required', 'integer', Rule::exists('rule_types', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:1', 'max:9999'],
            'status' => ['required', Rule::in(RuleModel::STATUSES)],
            'is_important' => ['nullable', 'boolean'],
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
            'rule_type_id.required' => __('Please choose a rule type.'),
            'rule_type_id.exists' => __('The selected rule type does not exist.'),
            'image.max' => __('The image must not be larger than 2 MB.'),
        ];
    }
}
