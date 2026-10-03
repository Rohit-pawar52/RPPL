<?php

namespace App\Http\Requests\Admin\TeamPlayer;

use App\Models\Player;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A player who registered offline and was bought in the auction: name and
 * mobile number (plus an optional sold amount) create the player, their
 * registration for the season and the squad entry in one step.
 */
class CreateOfflineSquadPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same phone normalisation as the public form, so +91 / spaces match.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => Player::normalizePhone($this->input('phone'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'sold_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_status' => ['nullable', Rule::in(['paid', 'pending'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.'];
    }

    /**
     * Its errors live in their own bag so they are not mistaken for the
     * "add existing players" form on the same page.
     */
    protected $errorBag = 'offlinePlayer';
}
