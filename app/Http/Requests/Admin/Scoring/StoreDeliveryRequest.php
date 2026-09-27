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
     * Backward-compatibility shim: translates the pre-S02 single-select
     * extra_type/extra_amount shape (still used by some existing
     * callers/tests exercising the plain, single-extra-category case)
     * into the new independent is_wide/is_no_ball/bye_runs/leg_bye_runs/
     * wide_running_runs fields before validation runs, so both shapes
     * validate and record identically. A caller using the new fields
     * directly is completely unaffected — this only fires when
     * extra_type is actually present. DeliveryService::recordDelivery()
     * has the same shim for direct (non-HTTP) callers.
     */
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
     * S02 completion rule B: normal ball entry no longer submits
     * striker_match_player_id/non_striker_match_player_id/
     * bowler_match_player_id at all — the server already knows all
     * three from the innings' current state (see DeliveryService::
     * resolveImplicitParticipants()). All three are therefore optional
     * here; when a value IS submitted (backward compatibility for any
     * remaining direct caller, and defense-in-depth), the exact same
     * eligibility validation as before still applies — this never
     * weakens server-side validation, it only stops requiring the
     * scorer to re-supply facts the server already has.
     *
     * is_wide/is_no_ball are independent flags rather than a single
     * mutually-exclusive extra_type — a no-ball may legitimately carry
     * byes or leg-byes on the same delivery. bye_runs/leg_bye_runs are
     * themselves mutually exclusive with each other and with
     * runs_off_bat (real cricket: a ball is either hit for runs, or
     * missed entirely for byes, or off the body for leg-byes — never
     * more than one of those on the same ball), and none of the three
     * may be combined with is_wide (an unplayable wide is scored
     * entirely as wide runs).
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
        $isFreeHit = $deliveries->isFreeHit($innings);

        return [
            'striker_match_player_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if ($value !== null && ! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->batting_team_id)) {
                        $fail('The striker must be a selected player from the batting team.');
                    }
                },
            ],
            'non_striker_match_player_id' => [
                'nullable',
                'integer',
                'different:striker_match_player_id',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if ($value !== null && ! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->batting_team_id)) {
                        $fail('The non-striker must be a selected player from the batting team.');
                    }
                },
            ],
            'bowler_match_player_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $match, $innings) {
                    if ($value !== null && ! $deliveries->matchPlayerBelongsToTeam((int) $value, $match, $innings->bowling_team_id)) {
                        $fail('The bowler must be a selected player from the bowling team.');
                    }
                },
                function ($attribute, $value, $fail) use ($deliveries, $innings) {
                    if ($value === null || $innings->legal_balls % 6 !== 0) {
                        return;
                    }

                    $previousBowlerId = $deliveries->bowlerOfPreviousOver($innings);

                    if ($previousBowlerId !== null && $previousBowlerId === (int) $value) {
                        $fail('The same bowler cannot bowl two overs in a row.');
                    }
                },
            ],

            'is_wide' => ['nullable', 'boolean'],
            'is_no_ball' => ['nullable', 'boolean'],
            'no_ball_reason' => ['nullable', 'string', 'max:255'],
            'wide_running_runs' => ['nullable', 'integer', 'min:0', 'max:6'],
            'bye_runs' => [
                'nullable', 'integer', 'min:0', 'max:6',
                function ($attribute, $value, $fail) {
                    if ($value && $this->boolean('is_wide')) {
                        $fail('Byes cannot be recorded on a wide.');
                    }
                    if ($value && (int) $this->input('leg_bye_runs', 0) > 0) {
                        $fail('A delivery cannot be both a bye and a leg-bye.');
                    }
                    if ($value && (int) $this->input('runs_off_bat', 0) > 0) {
                        $fail('A delivery cannot have both bat runs and byes.');
                    }
                },
            ],
            'leg_bye_runs' => [
                'nullable', 'integer', 'min:0', 'max:6',
                function ($attribute, $value, $fail) {
                    if ($value && $this->boolean('is_wide')) {
                        $fail('Leg-byes cannot be recorded on a wide.');
                    }
                    if ($value && (int) $this->input('runs_off_bat', 0) > 0) {
                        $fail('A delivery cannot have both bat runs and leg-byes.');
                    }
                },
            ],
            // Raised from the old fixed max of 6 to allow for an
            // overthrow scenario (frozen S02 rules 34/35), where runs
            // credited off the bat can genuinely exceed a single boundary
            // — kept to a conservative, documented cap rather than
            // unbounded.
            'runs_off_bat' => [
                'nullable', 'integer', 'min:0', 'max:11',
                function ($attribute, $value, $fail) {
                    if ($value && $this->boolean('is_wide')) {
                        $fail('A wide cannot carry bat runs.');
                    }
                },
            ],
            // A short run, or an unusual run-out, where the physically-
            // completed run count genuinely differs from the credited
            // total (frozen S02 rule 12/19). Optional and independent of
            // every credited-runs field above — omitting it leaves
            // strike-rotation parity based on the credited total, exactly
            // as before.
            'runs_physically_run' => ['nullable', 'integer', 'min:0', 'max:11'],
            'is_short_run' => ['nullable', 'boolean'],

            'is_wicket' => ['nullable', 'boolean'],
            'wicket_type' => [
                'nullable',
                'required_if:is_wicket,1',
                'string',
                Rule::in(Delivery::WICKET_TYPES),
                function ($attribute, $value, $fail) use ($deliveries, $isFreeHit) {
                    if ($value && ! in_array($value, $deliveries->validWicketTypesForDelivery($this->boolean('is_wide'), $this->boolean('is_no_ball'), $isFreeHit), true)) {
                        $fail($isFreeHit
                            ? 'Only Run Out or Obstructing the Field may dismiss the batter on a Free Hit.'
                            : 'This dismissal type is not valid for this kind of delivery.');
                    }
                },
            ],
            'dismissed_match_player_id' => [
                'nullable',
                'required_if:is_wicket,1',
                'integer',
                function ($attribute, $value, $fail) use ($deliveries, $innings) {
                    if (! $value) {
                        return;
                    }

                    // Normal ball entry (frozen S02 completion rule B)
                    // never submits striker/non-striker at all — fall
                    // back to the server's own current state so this
                    // still validates against who is ACTUALLY batting,
                    // not against absent request input.
                    [$strikerId, $nonStrikerId] = $this->effectiveStrikerAndNonStriker($deliveries, $innings);

                    if (! in_array((int) $value, [$strikerId, $nonStrikerId], true)) {
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
            'confirmed_survivor_end' => [
                'nullable',
                'string',
                Rule::in(['striker', 'non_striker']),
                function ($attribute, $value, $fail) {
                    if ($value && ! in_array($this->input('wicket_type'), ['run_out', 'obstructing_field'], true)) {
                        $fail('The survivor\'s end can only be confirmed for a run out or obstructing the field.');
                    }
                },
            ],
            'commentary' => ['nullable', 'string', 'max:2000'],
            // Idempotency (frozen S02 rule 51): a client-generated token
            // for one quick-scoring tap, unique per innings. Optional —
            // a caller that never supplies one (any existing direct
            // caller/test) simply gets no duplicate-submission
            // protection, exactly as before this phase.
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The striker/non-striker this request will actually record against
     * — the raw submitted values when BOTH are present (explicit/
     * backward-compat path), otherwise the innings' own current state
     * (frozen S02 completion rule B's server-derived normal ball entry).
     * Never mixes one explicit value with one derived value, since
     * DeliveryService::resolveImplicitParticipants() treats the pair as
     * a unit the same way.
     *
     * @return array{0: int, 1: int}
     */
    private function effectiveStrikerAndNonStriker(DeliveryService $deliveries, Innings $innings): array
    {
        if ($this->filled('striker_match_player_id') && $this->filled('non_striker_match_player_id')) {
            return [(int) $this->input('striker_match_player_id'), (int) $this->input('non_striker_match_player_id')];
        }

        $state = $deliveries->expectedBattingState($innings);

        return [(int) $state['striker_id'], (int) $state['non_striker_id']];
    }
}
