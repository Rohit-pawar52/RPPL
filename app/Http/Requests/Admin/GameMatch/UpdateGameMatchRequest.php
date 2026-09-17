<?php

namespace App\Http\Requests\Admin\GameMatch;

use App\Models\EditionTeam;
use App\Models\GameMatch;
use App\Services\GameMatch\GameMatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameMatchRequest extends FormRequest
{
    /**
     * Authorization is handled explicitly in GameMatchController via
     * $this->authorize() (GameMatchPolicy), so this stays true to avoid
     * duplicating that check.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Scheduling metadata (venue, stage, overs, scheduled_at,
     * match_number) is always updatable. edition_id/edition_team_a_id/
     * edition_team_b_id are only included here — and therefore only
     * ever present in $request->validated() — while
     * GameMatchService::canChangeFixtureIdentity() is still true (no
     * MatchPlayer/Innings recorded yet). Once locked, those keys are
     * simply absent from rules(), so a client submitting them has the
     * values silently ignored rather than trusted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var GameMatch $match */
        $match = $this->route('match');

        $canChangeIdentity = app(GameMatchService::class)->canChangeFixtureIdentity($match);

        $rules = [
            'venue_id' => [
                'nullable',
                'integer',
                // A venue that was active when this match was scheduled
                // but has since been deactivated must remain valid here
                // if it's not being changed — only a genuinely NEW venue
                // selection must be active.
                Rule::exists('venues', 'id')->where(function ($query) use ($match) {
                    $query->where(function ($query) use ($match) {
                        $query->where('is_active', true);

                        if ($match->venue_id) {
                            $query->orWhere('id', $match->venue_id);
                        }
                    });
                }),
            ],
            'match_stage' => ['nullable', 'string', Rule::in(GameMatch::STAGES)],
            'overs_per_innings' => ['nullable', 'integer', 'min:1', 'max:50'],
            'scheduled_at' => ['required', 'date'],
            'match_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('matches', 'match_number')
                    ->where(fn ($query) => $query->where(
                        'edition_id',
                        $canChangeIdentity ? $this->input('edition_id', $match->edition_id) : $match->edition_id
                    ))
                    ->ignore($match->id),
            ],
        ];

        if ($canChangeIdentity) {
            // Same "preserve the current value" reasoning as venue_id
            // above: an edition that was open when this match was
            // scheduled but has since been completed must remain valid
            // here if it isn't being changed — only a genuinely NEW
            // edition selection must be non-completed. Without this, an
            // admin editing an unrelated field (e.g. venue) on an
            // otherwise-still-unstarted fixture would be blocked purely
            // because the edition happened to be marked completed later.
            $rules['edition_id'] = [
                'required',
                'integer',
                Rule::exists('editions', 'id')->where(function ($query) use ($match) {
                    $query->where(function ($query) use ($match) {
                        $query->where('status', '!=', 'completed')
                            ->orWhere('id', $match->edition_id);
                    });
                }),
            ];
            $rules['edition_team_a_id'] = [
                'required',
                'integer',
                'exists:edition_teams,id',
                'different:edition_team_b_id',
                function ($attribute, $value, $fail) use ($match) {
                    $this->validateEditionTeamEligibility($value, $fail, $match, 'edition_team_a_id');
                },
            ];
            $rules['edition_team_b_id'] = [
                'required',
                'integer',
                'exists:edition_teams,id',
                function ($attribute, $value, $fail) use ($match) {
                    $this->validateEditionTeamEligibility($value, $fail, $match, 'edition_team_b_id');
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'edition_id.exists' => 'The selected edition is not currently accepting new fixtures.',
            'edition_team_a_id.different' => 'Team A and Team B must be different teams.',
            'venue_id.exists' => 'The selected venue is not available for scheduling.',
            'match_number.unique' => 'This match number is already used in the selected edition.',
        ];
    }

    /**
     * The "belongs to the chosen edition" check always applies — that's
     * a structural correctness rule, never bypassed. The "team must be
     * active" check is skipped only when the submitted value is exactly
     * the match's own current value for that column (i.e. the admin
     * isn't actually changing which team this is), mirroring venue_id's
     * preserve-existing-value treatment above.
     */
    protected function validateEditionTeamEligibility(mixed $value, \Closure $fail, GameMatch $match, string $currentColumn): void
    {
        $editionTeam = EditionTeam::with('team')->find($value);

        if (! $editionTeam) {
            return; // already caught by the 'exists' rule
        }

        if ((int) $editionTeam->edition_id !== (int) $this->input('edition_id')) {
            $fail('The selected team does not belong to the chosen edition.');

            return;
        }

        $isUnchanged = (int) $value === (int) $match->getAttribute($currentColumn);

        if (! $isUnchanged && ! $editionTeam->team->is_active) {
            $fail('The selected team is not available for scheduling.');
        }
    }
}
