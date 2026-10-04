<?php

namespace App\Http\Requests\Admin\Auction;

/**
 * Settings plus, optionally, a purse of its own for each team (blank = the
 * default purse).
 */
class UpdateAuctionRequest extends SaveAuctionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'team_purses' => ['nullable', 'array'],
            'team_purses.*' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return array<int, int|null> edition team id => own purse
     */
    public function teamPurses(): array
    {
        return collect($this->validated('team_purses') ?? [])
            ->mapWithKeys(fn ($purse, $teamId) => [(int) $teamId => filled($purse) ? (int) $purse : null])
            ->all();
    }
}
