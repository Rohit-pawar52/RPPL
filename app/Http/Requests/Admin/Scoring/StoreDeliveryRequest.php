<?php

namespace App\Http\Requests\Admin\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeliveryRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in ScoringController via
     * $this->authorize() (GameMatchPolicy::score), so this stays true
     * to avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Whether the innings/match may currently be scored at all
     * (live status, overs limit) is a match-state gate, not an
     * input-shape rule — ScoringController checks
     * DeliveryService::canRecordDelivery() for that, not this request.
     * This only validates the shape and relational eligibility of the
     * submitted delivery itself.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GameMatch $match */
        $match = $this->route('match');
        /** @var Innings $innings */
        $innings = $this->route('innings');

        $deliveries = app(DeliveryService::class);

        return [
            'striker_match_player_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if (! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->batting_team_id)) {
                        $fail('The striker must be a selected player from the batting team.');
                    }
                },
            ],
            'non_striker_match_player_id' => [
                'required',
                'integer',
                'different:striker_match_player_id',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if (! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->batting_team_id)) {
                        $fail('The non-striker must be a selected player from the batting team.');
                    }
                },
            ],
            'bowler_match_player_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if (! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->bowling_team_id)) {
                        $fail('The bowler must be a selected player from the bowling team.');
                    }
                },
            ],
            // Not a real column — a UI/request-shape convenience mapped
            // onto wide_runs/no_ball_runs/bye_runs/leg_bye_runs by
            // DeliveryService. See its docblock.
            'extra_type' => ['nullable', 'string', Rule::in(Delivery::EXTRA_TYPES)],
            'extra_amount' => ['nullable', 'required_with:extra_type', 'integer', 'min:1', 'max:7'],
            'runs_off_bat' => ['nullable', 'integer', 'min:0', 'max:6'],
            'is_wicket' => ['nullable', 'boolean'],
            'wicket_type' => [
                'nullable',
                'required_if:is_wicket,1',
                'string',
                Rule::in(Delivery::WICKET_TYPES),
                function ($attribute, $value, $fail) use ($deliveries) {
                    if ($value && ! in_array($value, $deliveries->validWicketTypesForExtraType($this->input('extra_type')), true)) {
                        $fail('This dismissal type is not valid for this kind of delivery.');
                    }
                },
            ],
            'dismissed_match_player_id' => [
                'nullable',
                'required_if:is_wicket,1',
                'integer',
                function ($attribute, $value, $fail) {
                    if ($value && ! in_array((int) $value, [(int) $this->input('striker_match_player_id'), (int) $this->input('non_striker_match_player_id')], true)) {
                        $fail('The dismissed player must be the striker or non-striker on this delivery.');
                    }
                },
            ],
            'fielder_match_player_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    $wicketType = $this->input('wicket_type');

                    if (! $this->boolean('is_wicket') || ! $wicketType) {
                        return;
                    }

                    if ($deliveries->dismissalRequiresFielder($wicketType) && ! $value) {
                        $fail('A fielder is required for this dismissal type.');

                        return;
                    }

                    if ($deliveries->dismissalForbidsFielder($wicketType) && $value) {
                        $fail('A fielder must not be recorded for this dismissal type.');

                        return;
                    }

                    if ($value && ! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->bowling_team_id)) {
                        $fail('The fielder must be a selected player from the bowling team.');
                    }
                },
            ],
            'commentary' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
