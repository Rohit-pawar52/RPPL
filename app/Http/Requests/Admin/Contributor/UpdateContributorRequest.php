<?php

namespace App\Http\Requests\Admin\Contributor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContributorRequest extends FormRequest
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
     * The uniqueness check on committee_member_id must ignore this
     * contributor's own current row, or a no-op re-save of an already
     * linked contributor would fail validation against itself.
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
                Rule::unique('contributors', 'committee_member_id')->ignore($this->route('contributor')),
            ],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
