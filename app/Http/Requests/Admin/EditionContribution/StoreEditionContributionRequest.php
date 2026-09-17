<?php

namespace App\Http\Requests\Admin\EditionContribution;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\EditionContribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * The admin form posts one combined "source" field (e.g.
     * "committee:5" or "contributor:12") from a single grouped select,
     * so the choice of person is a single, unambiguous field the
     * browser can never split across two stale selects. Split into the
     * source_type/source_id shape the rest of this request/the service
     * actually validates and consumes. Only the two fixed literal
     * prefixes are ever recognized — never an arbitrary model/class
     * name from the request.
     */
    protected function prepareForValidation(): void
    {
        $source = (string) $this->input('source', '');

        if (str_contains($source, ':')) {
            [$type, $id] = explode(':', $source, 2);

            $this->merge(['source_type' => $type, 'source_id' => $id]);
        }
    }

    /**
     * created_by is deliberately absent — never accepted from the
     * request, only ever set server-side from auth()->id().
     * source_type/source_id (Phase 3.38B2) replace a single
     * committee_member_id field so ONE form can record a contribution
     * from either identity without exposing an internal model/class
     * name. source_id must reference a currently ACTIVE record of the
     * chosen type — a deactivated person may keep their historical
     * contributions, but may not receive a new one. The minimum amount
     * differs by source: committee keeps its existing ₹1000 floor,
     * general contributions only need to be genuinely positive.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'source_type' => ['required', Rule::in(['committee', 'contributor'])],
            'source_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    $isActive = $this->input('source_type') === 'committee'
                        ? CommitteeMember::where('id', $value)->where('is_active', true)->exists()
                        : Contributor::where('id', $value)->where('is_active', true)->exists();

                    if (! $isActive) {
                        $fail($this->input('source_type') === 'committee'
                            ? 'The selected committee member must be active.'
                            : 'The selected contributor must be active.');
                    }
                },
            ],
            'amount' => [
                'required',
                'numeric',
                'max:99999999.99',
                function ($attribute, $value, $fail) {
                    if ($this->input('source_type') === 'committee') {
                        if ((float) $value < EditionContribution::MINIMUM_AMOUNT) {
                            $fail('Each committee contribution must be at least ₹'.number_format(EditionContribution::MINIMUM_AMOUNT).'.');
                        }
                    } elseif ((float) $value < EditionContribution::MINIMUM_GENERAL_AMOUNT) {
                        $fail('The contribution amount must be greater than zero.');
                    }
                },
            ],
            'contributed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
