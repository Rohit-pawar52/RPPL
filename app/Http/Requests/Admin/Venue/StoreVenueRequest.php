<?php

namespace App\Http\Requests\Admin\Venue;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in VenueController via
     * $this->authorize() (VenuePolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A plain checkbox sends nothing when unchecked, so is_default is made an
     * explicit true/false (otherwise unticking it would change nothing).
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_default' => $this->boolean('is_default')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // city/country are intentionally not accepted any more (kept in
            // the database for old rows) so an update never touches them.
            'village' => ['nullable', 'string', 'max:100'],
            'tehsil' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            // The map pin: both coordinates or neither.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'is_default' => ['required', 'boolean'],
        ];
    }
}
