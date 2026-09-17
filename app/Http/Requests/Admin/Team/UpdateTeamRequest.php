<?php

namespace App\Http\Requests\Admin\Team;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in TeamController via
     * $this->authorize() (TeamPolicy), so this stays true to avoid
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
        /** @var Team $team */
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($team->id)],
            'short_name' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            // Submitted via a <select> (values "1"/"0"), not a checkbox —
            // an unchecked checkbox simply omits the field from the
            // request, which would make it impossible to deactivate a
            // team whose browser did that. A select always submits a
            // definite value, so this can safely stay "required".
            'is_active' => ['required', 'boolean'],
        ];
    }
}
