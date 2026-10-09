<?php

namespace App\Http\Requests\Admin\Contributor;

use App\Models\Contributor;
use Illuminate\Foundation\Http\FormRequest;

class UpdateContributorRequest extends FormRequest
{
    /**
     * Asked before anything is validated, like StoreContributorRequest. ContributorController checks again.
     */
    public function authorize(): bool
    {
        $target = $this->route('contributor');

        return $target instanceof Contributor && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * committee_member_id is deliberately absent (Phase 3.48) —
     * committee membership is now edition-specific (see
     * EditionCommitteeMember/the Finance "Committee" tab), never a
     * field on the Contributor form.
     *
     * Village is optional here (unlike when adding): a contributor recorded before villages were kept has
     * none, and must stay editable until somebody knows it.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'village' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
