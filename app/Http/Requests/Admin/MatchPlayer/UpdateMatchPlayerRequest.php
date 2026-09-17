<?php

namespace App\Http\Requests\Admin\MatchPlayer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The only thing ever updated on a MatchPlayer is which single
 * designation — captain or wicket-keeper — is being assigned. There is
 * no free-form "edit this row" form, so the only validation of real
 * value here is restricting which designation a client may request;
 * everything else (which team the new designation is unset for) is
 * MatchPlayerService's job, not this request's.
 */
class UpdateMatchPlayerRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in MatchPlayerController via
     * $this->authorize() (MatchPlayerPolicy), so this stays true to
     * avoid duplicating that check.
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
            'designation' => ['required', 'string', Rule::in(['captain', 'wicket_keeper'])],
        ];
    }
}
