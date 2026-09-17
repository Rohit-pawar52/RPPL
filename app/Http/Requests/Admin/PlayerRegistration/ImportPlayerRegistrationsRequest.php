<?php

namespace App\Http\Requests\Admin\PlayerRegistration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportPlayerRegistrationsRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in PlayerRegistrationController
     * via $this->authorize() (PlayerRegistrationPolicy), so this stays
     * true to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The edition follows the exact same eligibility rule as manual
     * registration creation (StorePlayerRegistrationRequest) — an
     * import must not be able to add registrations to a completed
     * edition when a manual registration could not. mimes:csv,txt is
     * deliberately permissive: browser MIME detection for CSV varies
     * (some send text/plain), but this is not an arbitrary-upload
     * bypass since the extension is still checked.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => [
                'required',
                'integer',
                Rule::exists('editions', 'id')->where(fn ($query) => $query->where('status', '!=', 'completed')),
            ],
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'edition_id.exists' => 'The selected edition is not currently accepting registrations.',
            'csv_file.mimes' => 'The file must be a CSV file.',
        ];
    }
}
