<?php

namespace App\Http\Requests\Admin\Innings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Frozen S02 completion rule A (explicit Start Innings workflow) —
 * shape validation only; Playing XI membership and distinctness are
 * re-verified inside InningsService::setUpOpeningState().
 */
class SetUpOpeningStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'striker_match_player_id' => ['required', 'integer'],
            'non_striker_match_player_id' => ['required', 'integer', 'different:striker_match_player_id'],
            'bowler_match_player_id' => ['required', 'integer'],
        ];
    }
}
