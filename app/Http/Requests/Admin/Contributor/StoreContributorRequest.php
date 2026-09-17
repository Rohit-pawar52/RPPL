<?php

namespace App\Http\Requests\Admin\Contributor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * simply takes the column's DB default (active), matching
     * CommitteeMember's own convention. committee_member_id is optional
     * explicit identity linkage: if provided, it must reference a real
     * CommitteeMember (active or not — historical/inactive committee
     * identity can still represent the same person) and must not already
     * be linked to a different Contributor.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'committee_member_id' => [
                'nullable',
                'integer',
                'exists:committee_members,id',
                Rule::unique('contributors', 'committee_member_id'),
            ],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
