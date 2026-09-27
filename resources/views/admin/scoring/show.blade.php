@extends('layouts.admin')

@section('title', 'Score Innings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
    </div>

    @php
        $battingOptions = $battingMatchPlayers->mapWithKeys(fn ($mp) => [
            $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
        ]);
        $bowlingOptions = $bowlingMatchPlayers->mapWithKeys(fn ($mp) => [
            $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
        ]);
        $playerName = fn ($id) => $battingOptions->get($id) ?? $bowlingOptions->get($id) ?? '—';
    @endphp

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h2>
            <x-status-badge :status="$innings->status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $match->edition->name }}
            @if($match->match_number)
                &middot; Match {{ $match->match_number }}
            @endif
            &middot; Innings {{ $innings->innings_number }}
        </p>

        <p class="mt-3 text-2xl font-semibold text-neutral-900">
            {{ $innings->total_runs }}/{{ $innings->total_wickets }}
            <span class="text-sm font-normal text-neutral-500">({{ $innings->oversDisplay() }} overs)</span>
        </p>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $innings->battingTeam->team->name }} batting &middot; {{ $innings->bowlingTeam->team->name }} bowling
        </p>

        @if(! $awaitingSetup && ! $expectedBattingState['first_ball'] && ! $expectedBattingState['requires_replacement'])
            <p class="mt-2 text-xs text-neutral-500">
                On strike: <span class="font-medium text-neutral-800">{{ $playerName($expectedBattingState['striker_id']) }}</span>
                &middot; Non-striker: <span class="font-medium text-neutral-800">{{ $playerName($expectedBattingState['non_striker_id']) }}</span>
                @unless($expectedBattingState['awaiting_new_over_bowler'])
                    &middot; Bowler: <span class="font-medium text-neutral-800">{{ $playerName($expectedBattingState['bowler_id']) }}</span>
                @endunless
            </p>
        @endif

        @if($isFreeHit)
            <p class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                Free Hit — only Run Out or Obstructing the Field may dismiss the batter
            </p>
        @endif
    </div>

    @unless($canRecordDelivery)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-700">
            @if($isOverLimitReached)
                Over limit reached for this innings. Complete the innings from the match page, or use Undo Last Delivery to make a correction.
            @else
                This innings can no longer be scored.
            @endif
        </div>
    @endunless

    @if($canRecordDelivery && $awaitingSetup)
        {{-- Explicit Start Innings setup (S02 completion rule A) — asked
             ONCE per innings, before any ball can be recorded. --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Start Innings</h3>
            <p class="mb-3 text-xs text-neutral-500">Select the opening striker, non-striker, and first bowler. Ball entry begins once confirmed.</p>

            <form method="POST" action="{{ route('admin.matches.innings.setup', [$match, $innings]) }}" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.select name="striker_match_player_id" label="Striker" placeholder="Select striker" :options="$battingOptions" />
                    <x-form.select name="non_striker_match_player_id" label="Non-striker" placeholder="Select non-striker" :options="$battingOptions" />
                    <x-form.select name="bowler_match_player_id" label="First bowler" placeholder="Select bowler" :options="$bowlingOptions" />
                </div>
                <button type="submit" class="rounded-md theme-button px-4 py-2 text-[13px] font-medium">
                    Confirm and Start Scoring
                </button>
            </form>
        </div>
    @elseif($canRecordDelivery && $expectedBattingState['requires_replacement'])
        {{-- New Batter (S02 completion rule C) — a wicket, retirement, or
             strike correction vacated an end; select the incoming batter
             ONCE, never the whole pair again. --}}
        @php
            $eligibleNewBatters = $battingMatchPlayers
                ->reject(fn ($mp) => $mp->id === $expectedBattingState['survivor_id'])
                ->reject(fn ($mp) => in_array($mp->id, app(\App\Services\Scoring\DeliveryService::class)->dismissedMatchPlayerIds($innings), true))
                ->mapWithKeys(fn ($mp) => [$mp->id => $battingOptions->get($mp->id)]);
        @endphp
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">Select New Batter</h3>
            <p class="mb-3 text-xs text-amber-700">
                {{ $playerName($expectedBattingState['survivor_id']) }} continues at the {{ str_replace('_', '-', $expectedBattingState['survivor_end']) }}'s end. Choose the incoming batter for the other end.
            </p>

            <form method="POST" action="{{ route('admin.matches.innings.select-new-batter', [$match, $innings]) }}" novalidate>
                @csrf
                <x-form.select name="match_player_id" label="New batter" placeholder="Select batter" :options="$eligibleNewBatters" />
                <button type="submit" class="rounded-md theme-button px-4 py-2 text-[13px] font-medium">
                    Confirm New Batter
                </button>
            </form>
        </div>
    @elseif($canRecordDelivery && $expectedBattingState['awaiting_new_over_bowler'])
        {{-- New Over Bowler (S02 completion rule D) — the previous over
             just completed; select the bowler ONCE for the new over. The
             previous over's bowler is hard-blocked server-side. --}}
        @php
            $eligibleOverBowlers = $bowlingMatchPlayers
                ->reject(fn ($mp) => $previousOverBowlerId && $mp->id === $previousOverBowlerId)
                ->mapWithKeys(fn ($mp) => [$mp->id => $bowlingOptions->get($mp->id)]);
        @endphp
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-amber-700">Select Bowler</h3>
            <p class="mb-3 text-xs text-amber-700">A new over is starting. The previous over's bowler cannot bowl this one.</p>

            <form method="POST" action="{{ route('admin.matches.innings.select-over-bowler', [$match, $innings]) }}" novalidate>
                @csrf
                <x-form.select name="bowler_match_player_id" label="Bowler" placeholder="Select bowler" :options="$eligibleOverBowlers" />
                <button type="submit" class="rounded-md theme-button px-4 py-2 text-[13px] font-medium">
                    Confirm Bowler
                </button>
            </form>
        </div>
    @elseif($canRecordDelivery)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Record Delivery</h3>

            @error('delivery')
                <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.matches.innings.deliveries.store', [$match, $innings]) }}" novalidate>
                @csrf

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input name="runs_off_bat" label="Runs off bat" type="number" min="0" max="11" :value="0" />

                    <label class="mb-3.5 flex items-center gap-2 text-xs font-medium text-neutral-700">
                        <input type="checkbox" id="is_wide" name="is_wide" value="1" class="rounded border-neutral-300" @checked(old('is_wide')) />
                        Wide
                    </label>

                    <label class="mb-3.5 flex items-center gap-2 text-xs font-medium text-neutral-700">
                        <input type="checkbox" id="is_no_ball" name="is_no_ball" value="1" class="rounded border-neutral-300" @checked(old('is_no_ball')) />
                        No Ball
                    </label>
                </div>

                <div id="wide-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.input name="wide_running_runs" label="Runs physically run on the wide" type="number" min="0" max="6" :value="0" />
                </div>

                <div id="no-ball-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.input name="no_ball_reason" label="No ball reason (optional)" placeholder="e.g. Overstepping" />
                </div>

                <div id="bye-fields" class="grid gap-4 sm:grid-cols-3">
                    <x-form.input name="bye_runs" label="Byes" type="number" min="0" max="6" :value="0" />
                    <x-form.input name="leg_bye_runs" label="Leg-byes" type="number" min="0" max="6" :value="0" />
                </div>

                <label class="mb-3.5 flex items-center gap-2 text-xs font-medium text-neutral-700">
                    <input type="checkbox" id="is_wicket" name="is_wicket" value="1" class="rounded border-neutral-300" @checked(old('is_wicket')) />
                    Wicket
                </label>

                <div id="wicket-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.select
                        name="dismissed_match_player_id"
                        label="Dismissed player"
                        placeholder="Select dismissed player"
                        :options="[
                            $expectedBattingState['striker_id'] => $playerName($expectedBattingState['striker_id']),
                            $expectedBattingState['non_striker_id'] => $playerName($expectedBattingState['non_striker_id']),
                        ]"
                    />
                    <x-form.select
                        name="wicket_type"
                        label="Dismissal type"
                        placeholder="Select dismissal type"
                        :options="collect($wicketTypes)->mapWithKeys(fn ($type) => [$type => ucwords(str_replace('_', ' ', $type))])"
                    />
                    <x-form.select name="fielder_match_player_id" label="Fielder (optional)" placeholder="None" :options="$bowlingOptions" />
                </div>

                <div id="survivor-end-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.select
                        name="confirmed_survivor_end"
                        label="Confirm surviving batter's actual end (optional, run out / obstructing only)"
                        placeholder="Use computed end"
                        :options="['striker' => 'Striker', 'non_striker' => 'Non-striker']"
                    />
                </div>

                <details class="mb-3.5 rounded-md border border-neutral-200 p-3">
                    <summary class="cursor-pointer text-xs font-medium text-neutral-600">Advanced (short run)</summary>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <label class="mb-3.5 flex items-center gap-2 text-xs font-medium text-neutral-700">
                            <input type="checkbox" name="is_short_run" value="1" class="rounded border-neutral-300" @checked(old('is_short_run')) />
                            Short run called
                        </label>
                        <x-form.input name="runs_physically_run" label="Runs physically completed (if different from credited)" type="number" min="0" max="11" />
                    </div>
                </details>

                <div class="mb-3.5">
                    <label for="commentary" class="mb-1 block text-xs font-medium text-neutral-700">Commentary</label>
                    <textarea
                        id="commentary"
                        name="commentary"
                        rows="2"
                        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('commentary') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
                    >{{ old('commentary') }}</textarea>
                    @error('commentary')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-md theme-button px-4 py-2 text-[13px] font-medium">
                    Record Delivery
                </button>
            </form>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Change Strike</h3>
                <p class="mb-2 text-xs text-neutral-500">Corrects the actual ends — no delivery, runs, or wickets change.</p>
                <form method="POST" action="{{ route('admin.matches.innings.change-strike', [$match, $innings]) }}">
                    @csrf
                    <x-form.select name="striker_match_player_id" label="Striker" placeholder="Select striker" :options="$battingOptions" />
                    <x-form.select name="non_striker_match_player_id" label="Non-striker" placeholder="Select non-striker" :options="$battingOptions" />
                    <x-form.input name="reason" label="Reason" placeholder="e.g. Wrong ends recorded" />
                    <button type="submit" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                        Correct Strike
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Retire Batter</h3>
                <p class="mb-2 text-xs text-neutral-500">Retired Hurt may return later; Retired Out counts as a wicket and cannot.</p>
                <form method="POST" action="{{ route('admin.matches.innings.retire-batter', [$match, $innings]) }}">
                    @csrf
                    <x-form.select name="match_player_id" label="Batter" placeholder="Select batter at the crease" :options="$battingOptions" />
                    <x-form.select name="type" label="Type" placeholder="Select type" :options="['hurt' => 'Retired Hurt', 'out' => 'Retired Out']" />
                    <x-form.input name="reason" label="Reason" placeholder="e.g. Injury" />
                    <button type="submit" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                        Retire Batter
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Change Bowler Mid-Over</h3>
                <p class="mb-2 text-xs text-neutral-500">For a genuine mid-over swap (e.g. injury). Balls already bowled keep their original bowler.</p>
                <form method="POST" action="{{ route('admin.matches.innings.change-bowler', [$match, $innings]) }}">
                    @csrf
                    <x-form.select
                        name="bowler_match_player_id"
                        label="Replacement bowler"
                        placeholder="Select bowler"
                        :options="$bowlingOptions->except($expectedBattingState['bowler_id'])"
                    />
                    <x-form.input name="reason" label="Reason" placeholder="e.g. Injury" />
                    <button type="submit" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                        Change Bowler
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Penalty Runs</h3>
                <p class="mb-2 text-xs text-neutral-500">Standard 5-run award as Penalty extras. If the awarded team hasn't batted yet, it's added to their innings total as soon as that innings starts.</p>
                <form method="POST" action="{{ route('admin.matches.innings.penalty-runs', [$match, $innings]) }}">
                    @csrf
                    <x-form.select
                        name="awarded_team_id"
                        label="Awarded to"
                        placeholder="Select team"
                        :options="[
                            $match->edition_team_a_id => $match->teamA->team->name,
                            $match->edition_team_b_id => $match->teamB->team->name,
                        ]"
                    />
                    <x-form.input name="reason" label="Reason" placeholder="e.g. Fielding restriction breach" />
                    <button type="submit" class="rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                        Award 5 Penalty Runs
                    </button>
                </form>
            </div>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Deliveries</h3>

            @if($canRecordDelivery && $recentDeliveries->isNotEmpty())
                <form
                    method="POST"
                    action="{{ route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]) }}"
                    onsubmit="event.preventDefault(); window.confirmAction({title: 'Undo the last delivery?', confirmButtonText: 'Yes, undo'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">
                        <x-icon name="undo" class="h-3.5 w-3.5" />
                        Undo Last Delivery
                    </button>
                </form>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Ball</th>
                        <th class="px-2 py-1.5 font-medium">Batter</th>
                        <th class="px-2 py-1.5 font-medium">Bowler</th>
                        <th class="px-2 py-1.5 font-medium">Runs</th>
                        <th class="px-2 py-1.5 font-medium">Extra</th>
                        <th class="px-2 py-1.5 font-medium">Wicket</th>
                        <th class="px-2 py-1.5 font-medium">Commentary</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($recentDeliveries as $delivery)
                        <tr>
                            <td class="whitespace-nowrap px-2 py-1.5 text-neutral-500">
                                {{ $delivery->over_number }}.{{ $delivery->ball_number }}
                                @if($delivery->is_free_hit)
                                    <span class="ml-1 rounded bg-amber-50 px-1 text-[10px] font-semibold text-amber-700">FH</span>
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->striker->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->bowler->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->total_runs }}</td>
                            <td class="px-2 py-1.5 text-neutral-500">
                                @if($delivery->is_wide)
                                    Wide
                                @elseif($delivery->is_no_ball)
                                    No-ball
                                @elseif($delivery->bye_runs)
                                    Bye
                                @elseif($delivery->leg_bye_runs)
                                    Leg-bye
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-700">
                                @if($delivery->is_wicket)
                                    {{ ucwords(str_replace('_', ' ', $delivery->wicket_type)) }} ({{ $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name }})
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-500">{{ $delivery->commentary ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-6 text-center text-neutral-400">No deliveries recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wicketCheckbox = document.getElementById('is_wicket');
            const wicketFields = document.getElementById('wicket-fields');
            const survivorEndFields = document.getElementById('survivor-end-fields');
            const wicketTypeSelect = document.getElementById('wicket_type');

            const toggleWicket = () => {
                if (wicketFields) wicketFields.hidden = !wicketCheckbox.checked;
                refreshSurvivorEndVisibility();
            };
            const refreshSurvivorEndVisibility = () => {
                if (! survivorEndFields || ! wicketTypeSelect) return;
                const relevant = wicketCheckbox.checked && ['run_out', 'obstructing_field'].includes(wicketTypeSelect.value);
                survivorEndFields.hidden = !relevant;
            };
            if (wicketCheckbox) {
                wicketCheckbox.addEventListener('change', toggleWicket);
                toggleWicket();
            }
            if (wicketTypeSelect) {
                wicketTypeSelect.addEventListener('change', refreshSurvivorEndVisibility);
            }

            const isWide = document.getElementById('is_wide');
            const isNoBall = document.getElementById('is_no_ball');
            const wideFields = document.getElementById('wide-fields');
            const noBallFields = document.getElementById('no-ball-fields');
            const byeFields = document.getElementById('bye-fields');

            const refreshExtraFields = () => {
                if (wideFields) wideFields.hidden = !isWide.checked;
                if (noBallFields) noBallFields.hidden = !isNoBall.checked;
                if (byeFields) byeFields.hidden = isWide.checked;
            };

            if (isWide && isNoBall) {
                isWide.addEventListener('change', () => {
                    if (isWide.checked) isNoBall.checked = false;
                    refreshExtraFields();
                });
                isNoBall.addEventListener('change', () => {
                    if (isNoBall.checked) isWide.checked = false;
                    refreshExtraFields();
                });
                refreshExtraFields();
            }
        });
    </script>
@endsection
