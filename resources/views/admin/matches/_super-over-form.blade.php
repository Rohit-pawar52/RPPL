{{--
    Tied Match / Super Over (frozen S02 rule 7) — the main innings ended
    tied; the admin/scorer manually records who won the Super Over
    instead of finalizing as a plain tie. No ball-by-ball Super Over
    scoring exists (out of scope for this phase) — the main innings
    scores are never touched by this action.
--}}
<div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 sm:p-4">
    <p class="mb-3 text-xs text-amber-800">{{ __('The main innings are tied. If a Super Over was played, record its winner here instead of finalizing as a tie.') }}</p>

    <form method="POST" action="{{ route('admin.matches.super-over', $match) }}" class="grid gap-x-3 sm:grid-cols-[minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-end">
        @csrf
        <x-form.select
            name="winner_team_id"
            :label="__('Super Over winner')"
            :placeholder="__('Select team')"
            :options="[
                $match->edition_team_a_id => $match->teamA->team->name,
                $match->edition_team_b_id => $match->teamB->team->name,
            ]"
        />
        <x-form.input name="reason" :label="__('Reason / Super Over note')" :placeholder="__('e.g. Super Over: Team A 12/0, Team B 9/1')" />
        <div class="mb-3.5">
            <button type="submit" class="btn btn-primary min-h-10 max-sm:w-full">
                {{ __('Record Super Over Result') }}
            </button>
        </div>
    </form>
</div>
