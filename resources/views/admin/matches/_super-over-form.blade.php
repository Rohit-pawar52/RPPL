{{--
    Tied Match / Super Over (frozen S02 rule 7) — the main innings ended
    tied; the admin/scorer manually records who won the Super Over
    instead of finalizing as a plain tie. No ball-by-ball Super Over
    scoring exists (out of scope for this phase) — the main innings
    scores are never touched by this action.
--}}
<div class="mt-3 rounded-md border border-neutral-100 bg-neutral-50 p-3">
    <p class="mb-2 text-xs text-neutral-500">The main innings are tied. If a Super Over was played, record its winner here instead of finalizing as a tie.</p>

    <form method="POST" action="{{ route('admin.matches.super-over', $match) }}" class="flex flex-wrap items-end gap-2">
        @csrf
        <div class="w-44">
            <x-form.select
                name="winner_team_id"
                label="Super Over winner"
                placeholder="Select team"
                :options="[
                    $match->edition_team_a_id => $match->teamA->team->name,
                    $match->edition_team_b_id => $match->teamB->team->name,
                ]"
            />
        </div>
        <div class="min-w-[220px] flex-1">
            <x-form.input name="reason" label="Reason / Super Over note" placeholder="e.g. Super Over: Team A 12/0, Team B 9/1" />
        </div>
        <button type="submit" class="mb-3.5 rounded-md theme-button px-3 py-1.5 text-[13px] font-medium">
            Record Super Over Result
        </button>
    </form>
</div>
