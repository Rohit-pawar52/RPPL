<?php

namespace App\Http\Requests\Admin\EditionContribution;

use App\Models\Contributor;
use App\Models\EditionContribution;
use Illuminate\Foundation\Http\FormRequest;

class StoreEditionContributionRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in
     * EditionContributionController via $this->authorize()
     * (EditionContributionPolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'contributor_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (! Contributor::where('id', $value)->where('is_active', true)->exists()) {
                        $fail('The selected contributor must be active.');
                    }
                },
            ],
            'amount' => ['required', 'numeric', 'max:99999999.99', 'min:'.EditionContribution::MINIMUM_AMOUNT],
            'contributed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
