<?php

namespace App\Http\Requests\Admin\EditionContribution;

use App\Models\Contributor;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use App\Services\Contributor\ContributorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEditionContributionRequest extends FormRequest
{
    /**
     * The contribution itself needs EditionContributionPolicy::create. Adding somebody new on the spot
     * (the "not in the list" panel of the form) also needs permission to add contributors, and ticking
     * "add to this edition's committee" needs permission to manage the committee: recording money must
     * never be a side door to rights the role was not given. Asked before anything is validated, so a
     * refused request learns nothing from the validation messages. EditionContributionController checks again.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('create', EditionContribution::class)) {
            return false;
        }

        if ($this->isNewContributor()) {
            if (! $user->can('create', Contributor::class)) {
                return false;
            }

            if ($this->boolean('add_to_committee') && ! $user->can('create', EditionCommitteeMember::class)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Somebody not in the list yet is added by filling the "new contributor" fields of the same form; that is
     * the case as soon as any of them has something in it, so a half-filled panel is reported as such (the
     * name is missing) instead of as "choose a contributor".
     */
    public function isNewContributor(): bool
    {
        return $this->anyFilled(['new_name', 'new_village', 'new_phone', 'new_address']);
    }

    /**
     * Phase 3.48: ONE contribution form for every contributor — no more
     * source_type/source_id duality. created_by is deliberately absent
     * — never accepted from the request, only ever set server-side from
     * auth()->id(). contributor_id must reference a currently ACTIVE
     * contributor — a deactivated person may keep their historical
     * contributions, but may not receive a new one. There is no longer a
     * different minimum for a "committee" contribution: any genuinely
     * positive amount is accepted regardless of committee status —
     * installments toward the global committee dues target are the
     * whole point (see CommitteeDuesService/finance.committee_minimum_contribution).
     *
     * Either an existing contributor is chosen (contributor_id) or a new one is described (new_*), never both.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $new = $this->isNewContributor();

        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'contributor_id' => $new
                ? ['prohibited']
                : [
                    'required',
                    'integer',
                    function ($attribute, $value, $fail) {
                        if (! Contributor::where('id', $value)->where('is_active', true)->exists()) {
                            $fail('The selected contributor must be active.');
                        }
                    },
                ],
            'new_name' => [$new ? 'required' : 'nullable', 'string', 'max:255'],
            'new_village' => [$new ? 'required' : 'nullable', 'string', 'max:100'],
            'new_phone' => ['nullable', 'string', 'max:20'],
            'new_address' => ['nullable', 'string', 'max:255'],
            'add_to_committee' => ['nullable', 'boolean'],
            // Not saved: "yes, this really is another person" after the already-in-the-list warning.
            'confirm_duplicate' => ['nullable', 'boolean'],
            'amount' => ['required', 'numeric', 'max:99999999.99', 'min:'.EditionContribution::MINIMUM_AMOUNT],
            'contributed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contributor_id.prohibited' => 'Choose a contributor from the list, or add a new one below - not both.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'new_name' => 'name',
            'new_village' => 'village',
            'new_phone' => 'phone',
            'new_address' => 'address',
        ];
    }

    /**
     * Adding the same person twice would split their total in two (and so their place in the Top
     * Contributors list): a likely duplicate is stopped once, and the admin has to say it is somebody else.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->isNewContributor() || $validator->errors()->isNotEmpty() || $this->boolean('confirm_duplicate')) {
                return;
            }

            $matches = app(ContributorService::class)->possibleDuplicates(
                (string) $this->input('new_name'),
                (string) $this->input('new_village')
            );

            if ($matches->isNotEmpty()) {
                $validator->errors()->add('confirm_duplicate', ContributorService::duplicateMessage($matches));
            }
        });
    }

    /**
     * What EditionContributionService::createContribution() takes: the contribution's own fields plus,
     * for somebody new, a `new_contributor` block (and whether to put them on this edition's committee).
     *
     * @return array<string, mixed>
     */
    public function contributionData(): array
    {
        $data = $this->safe()->only(['edition_id', 'contributor_id', 'amount', 'contributed_at', 'notes']);

        if ($this->isNewContributor()) {
            unset($data['contributor_id']);

            $data['new_contributor'] = [
                'name' => $this->validated('new_name'),
                'village' => $this->validated('new_village'),
                'phone' => $this->validated('new_phone'),
                'address' => $this->validated('new_address'),
            ];
            $data['add_to_committee'] = $this->boolean('add_to_committee');
        }

        return $data;
    }
}
