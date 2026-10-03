<?php

namespace App\Http\Requests\Admin\EditionTeam;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A brand-new team created from inside a season. The same rules as the
 * Teams page (StoreTeamRequest).
 */
class CreateSeasonTeamRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * A failed "new team" form must not be mistaken for the "add existing
     * teams" form on the same page: its errors live in their own bag.
     */
    protected $errorBag = 'newTeam';
}
