<?php

namespace App\Http\Requests\Admin\Team;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    /**
     * Asks TeamPolicy before anything is validated: a validation message such as "already taken"
     * must not tell somebody who may not manage this what already exists. TeamController checks again.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Team::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
            'short_name' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
