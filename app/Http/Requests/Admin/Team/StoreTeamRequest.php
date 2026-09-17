<?php

namespace App\Http\Requests\Admin\Team;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
