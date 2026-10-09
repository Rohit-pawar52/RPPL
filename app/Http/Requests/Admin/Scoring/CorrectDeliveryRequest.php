<?php

namespace App\Http\Requests\Admin\Scoring;

use App\Models\Delivery;
use App\Models\GameMatch;
use App\Models\Innings;
use App\Services\Scoring\DeliveryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Frozen S02 rules 43/44/46 — the quick correction window. Every field
 * is optional: an omitted field simply keeps the delivery's current
 * value (see DeliveryService::correctDelivery()). Deliberately has no
 * striker_match_player_id/non_striker_match_player_id/
 * bowler_match_player_id fields at all — correction never changes who
 * was batting/bowling on a ball, only its recorded outcome facts; that
 * is what Change Strike/Change Bowler Mid-Over are for.
 */
class CorrectDeliveryRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in ScoringController via
     * $this->authorize() (GameMatchPolicy::score), so this stays true to
     * avoid duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('extra_type')) {
            return;
        }

        $extraType = $this->input('extra_type');
        $extraAmount = (int) $this->input('extra_amount', 0);

        $this->merge(match ($extraType) {
            'wide' => ['is_wide' => true, 'wide_running_runs' => max(0, $extraAmount - 1)],
            'no_ball' => ['is_no_ball' => true],
            'bye' => ['bye_runs' => $extraAmount],
            'leg_bye' => ['leg_bye_runs' => $extraAmount],
            default => [],
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GameMatch $match */
        $match = $this->route('match');
        /** @var Innings $innings */
        $innings = $this->route('innings');
        /** @var Delivery $delivery */
        $delivery = $this->route('delivery');

        $deliveries = app(DeliveryService::class);
        $isFreeHit = (bool) $delivery->is_free_hit;

        return [
            'is_wide' => ['sometimes', 'boolean'],
            'is_no_ball' => ['sometimes', 'boolean'],
            'no_ball_reason' => ['nullable', 'string', 'max:255'],
            'wide_running_runs' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'bye_runs' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'leg_bye_runs' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'runs_off_bat' => ['sometimes', 'integer', 'min:0', 'max:11'],
            'runs_physically_run' => ['nullable', 'integer', 'min:0', 'max:11'],
            'is_short_run' => ['sometimes', 'boolean'],

            'is_wicket' => ['sometimes', 'boolean'],
            'wicket_type' => [
                'nullable',
                'required_if:is_wicket,1',
                'string',
                Rule::in(Delivery::WICKET_TYPES),
                function ($attribute, $value, $fail) use ($deliveries, $isFreeHit) {
                    if (! $value) {
                        return;
                    }

                    $isWide = $this->boolean('is_wide', (bool) $this->route('delivery')->is_wide);
                    $isNoBall = $this->boolean('is_no_ball', (bool) $this->route('delivery')->is_no_ball);

                    if (! in_array($value, $deliveries->validWicketTypesForDelivery($isWide, $isNoBall, $isFreeHit), true)) {
                        $fail($isFreeHit
                            ? __('Only Run Out or Obstructing the Field may dismiss the batter on a Free Hit.')
                            : __('This dismissal type is not valid for this kind of delivery.'));
                    }
                },
            ],
            'dismissed_match_player_id' => [
                'nullable',
                'required_if:is_wicket,1',
                'integer',
                function ($attribute, $value, $fail) use ($delivery) {
                    if (! $value) {
                        return;
                    }

                    if (! in_array((int) $value, [(int) $delivery->striker_match_player_id, (int) $delivery->non_striker_match_player_id], true)) {
                        $fail(__('The dismissed player must be the striker or non-striker on this delivery.'));
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
                        $fail(__('A fielder is required for this dismissal type.'));

                        return;
                    }

                    if ($deliveries->dismissalForbidsFielder($wicketType) && $value) {
                        $fail(__('A fielder must not be recorded for this dismissal type.'));

                        return;
                    }

                    if ($value && ! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->bowling_team_id)) {
                        $fail(__('The fielder must be a selected player from the bowling team.'));
                    }
                },
            ],
            'confirmed_survivor_end' => [
                'nullable',
                'string',
                Rule::in(['striker', 'non_striker']),
                function ($attribute, $value, $fail) {
                    if ($value && ! in_array($this->input('wicket_type'), ['run_out', 'obstructing_field'], true)) {
                        $fail(__('The survivor\'s end can only be confirmed for a run out or obstructing the field.'));
                    }
                },
            ],
            'commentary' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
