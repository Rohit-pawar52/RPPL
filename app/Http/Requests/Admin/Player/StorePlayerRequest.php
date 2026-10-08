<?php

namespace App\Http\Requests\Admin\Player;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerRequest extends FormRequest
{
    /**
     * Asks PlayerPolicy before anything is validated: a validation message such as "already taken"
     * must not tell somebody who may not manage this what already exists. PlayerController checks again.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Player::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:players,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:players,email'],
            'batting_style' => ['nullable', 'string', Rule::in(Player::BATTING_STYLES)],
            'bowling_style' => ['nullable', 'string', Rule::in(Player::BOWLING_STYLES)],
            'primary_role' => ['nullable', 'string', Rule::in(Player::PRIMARY_ROLES)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }
}
