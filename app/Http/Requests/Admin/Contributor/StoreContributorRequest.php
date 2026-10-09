<?php

namespace App\Http\Requests\Admin\Contributor;

use App\Models\Contributor;
use App\Services\Contributor\ContributorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreContributorRequest extends FormRequest
{
    /**
     * Asked before anything is validated: the "already in the list" check below tells the reader who is
     * on the contributor list, so somebody who may not manage contributors must not get that far.
     * ContributorController checks again.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Contributor::class) ?? false;
    }

    /**
     * is_active is deliberately absent — a newly created contributor
     * simply takes the column's DB default (active). committee_member_id
     * is deliberately absent too (Phase 3.48) — a Contributor is added
     * to a specific edition's committee from the Finance "Committee"
     * tab, never linked via this form.
     *
     * The village is required for a new contributor: it is what tells two people with the same name apart
     * in every list, on the receipt and in the exports. (Contributors recorded before villages existed
     * stay as they are; see UpdateContributorRequest.)
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'village' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            // Not saved: the admin's "yes, this really is another person" after the duplicate warning.
            'confirm_duplicate' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Adding the same person twice would split their total in two (and so their place in the Top
     * Contributors list), so a likely duplicate is stopped once and the admin has to say it is somebody else.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->boolean('confirm_duplicate')) {
                return;
            }

            $matches = app(ContributorService::class)->possibleDuplicates(
                (string) $this->input('name'),
                (string) $this->input('village')
            );

            if ($matches->isNotEmpty()) {
                $validator->errors()->add('confirm_duplicate', ContributorService::duplicateMessage($matches));
            }
        });
    }
}
