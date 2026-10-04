<?php

namespace App\Http\Requests\Admin\Auction;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The auction's rule settings, shared by creating an auction and editing it.
 * Authorization is done in AuctionController through AuctionPolicy.
 */
abstract class SaveAuctionRequest extends FormRequest
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
            'team_purse' => ['required', 'integer', 'min:1', 'max:99999999'],
            'min_bid' => ['required', 'integer', 'min:1', 'max:1000000'],
            'bid_step' => ['required', 'integer', 'min:1', 'max:1000000'],
            'min_squad' => ['required', 'integer', 'min:1', 'max:50'],
            'max_squad' => ['required', 'integer', 'min:1', 'max:50', 'gte:min_squad'],
            'show_live_bids' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_squad.gte' => 'The maximum squad cannot be smaller than the minimum squad.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return [
            ...$this->safe()->only(['team_purse', 'min_bid', 'bid_step', 'min_squad', 'max_squad']),
            'show_live_bids' => $this->boolean('show_live_bids'),
        ];
    }
}
