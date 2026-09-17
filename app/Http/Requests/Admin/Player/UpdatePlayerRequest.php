<?php

namespace App\Http\Requests\Admin\Player;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in PlayerController via
     * $this->authorize() (PlayerPolicy), so this stays true to avoid
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
        /** @var Player $player */
        $player = $this->route('player');

        return [
            'name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('players', 'phone')->ignore($player->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('players', 'email')->ignore($player->id)],
            'batting_style' => ['nullable', 'string', Rule::in(Player::BATTING_STYLES)],
            'bowling_style' => ['nullable', 'string', Rule::in(Player::BOWLING_STYLES)],
            'primary_role' => ['nullable', 'string', Rule::in(Player::PRIMARY_ROLES)],
            'photo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            // Submitted via a <select> (values "1"/"0"), not a checkbox —
            // an unchecked checkbox simply omits the field from the
            // request, which would make it impossible to deactivate a
            // player whose browser did that. A select always submits a
            // definite value, so this can safely stay "required".
            'is_active' => ['required', 'boolean'],
        ];
    }
}
