<?php

namespace App\Http\Requests\Admin\Contributor;

use Illuminate\Foundation\Http\FormRequest;

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
     * committee_member_id is deliberately absent (Phase 3.48) —
     * committee membership is now edition-specific (see
     * EditionCommitteeMember/the Finance "Committee" tab), never a
     * field on the Contributor form.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
