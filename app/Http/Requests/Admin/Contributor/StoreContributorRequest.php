<?php

namespace App\Http\Requests\Admin\Contributor;

use Illuminate\Foundation\Http\FormRequest;

class StoreContributorRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in ContributorController via
     * $this->authorize() (ContributorPolicy), so this stays true to
     * avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * is_active is deliberately absent — a newly created contributor
     * simply takes the column's DB default (active). committee_member_id
     * is deliberately absent too (Phase 3.48) — a Contributor is added
     * to a specific edition's committee from the Finance "Committee"
     * tab, never linked via this form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
