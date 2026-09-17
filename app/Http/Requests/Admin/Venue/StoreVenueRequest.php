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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
        ];
    }
}
