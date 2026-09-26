<?php

namespace App\Http\Requests\Admin\DataCleanup;

use App\Models\Edition;
use App\Services\DataCleanup\RegistrationDocumentCleanupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteRegistrationDocumentsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in DataCleanupController via
     * $this->authorize('manage-tournament'), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The edition must no longer be open for new public registration —
     * deleting sensitive identity documents for an edition still
     * actively collecting them would delete evidence a currently-open
     * verification workflow might still need. registration_open (not
     * the broader `status`) is the exact gate the public guest form
     * itself checks (see Edition::isAcceptingPublicRegistration()), so
     * this uses the same real signal rather than inventing a new one.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => [
                'required',
                'integer',
                'exists:editions,id',
                function ($attribute, $value, $fail) {
                    $edition = Edition::find($value);

                    if ($edition && $edition->registration_open) {
                        $fail('Registration documents cannot be cleaned up for an edition that is still open for public registration.');
                    }
                },
            ],
            'document_type' => ['required', Rule::in(RegistrationDocumentCleanupService::TYPES)],
        ];
    }
}
