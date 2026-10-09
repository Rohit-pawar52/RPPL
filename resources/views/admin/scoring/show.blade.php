@extends('layouts.admin')

@section('title', __('Score Innings'))

@section('content')
    @php
        $battingOptions = $battingMatchPlayers->mapWithKeys(fn ($mp) => [
            $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
        ]);
        $bowlingOptions = $bowlingMatchPlayers->mapWithKeys(fn ($mp) => [
            $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
        ]);
        $playerName = fn ($id) => $battingOptions->get($id) ?? $bowlingOptions->get($id) ?? '—';

        // The run keys show only while a ball can be recorded right now: not before the
        // opening setup, and not while a new batter / new over bowler must be chosen first.
        $padVisible = $canRecordDelivery && ! $awaitingSetup && ! $expectedBattingState['requires_replacement'] && ! $expectedBattingState['awaiting_new_over_bowler'];
        $statusLabel = $innings->status;
    @endphp

    {{-- With the fixed keys at the bottom of a phone, the page needs room under it. --}}
    <div @class(['max-lg:pb-52' => $padVisible])>
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('admin.matches.show', $match) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Back to match') }}
        </a>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.matches.scorecard', $match) }}" class="btn btn-secondary btn-sm">
                <x-icon name="document-chart" class="h-3.5 w-3.5" /> {{ __('Scorecard') }}
            </a>
            <x-status-badge :status="$innings->status" />
        </div>
    </div>

    {{-- Frozen S02 rules 50-61: the live scorer-state panels. Server-
         rendered from $liveState for a fast first paint; admin-scoring.js
         re-renders these same elements from the identical JSON shape
         after every quick action, poll, and realtime signal — never a
         second, independently-computed source of truth. --}}
    <div
        id="scorer-root"
        data-match-id="{{ $match->id }}"
        data-score-data-url="{{ route('admin.matches.innings.score-data', [$match, $innings]) }}"
        data-store-url="{{ route('admin.matches.innings.deliveries.store', [$match, $innings]) }}"
        data-undo-url="{{ route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]) }}"
        data-correct-url-base="{{ url('admin/matches/'.$match->id.'/innings/'.$innings->id.'/deliveries') }}"
        data-batting-options="{{ $battingOptions->toJson() }}"
        data-bowling-options="{{ $bowlingOptions->toJson() }}"
        data-wicket-types="{{ collect($wicketTypes)->mapWithKeys(fn ($type) => [$type => __(ucwords(str_replace('_', ' ', $type)))])->toJson() }}"
    >
        {{-- The scoreboard: always on screen, the only thing a scorer has to read. --}}
        <section class="sc-board" aria-label="{{ __('Scoreboard') }}">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="sc-kicker truncate">
                        {{ $match->edition->name }}
                        @if($match->match_number) &middot; {{ __('Match :number', ['number' => $match->match_number]) }} @endif
                        &middot; {{ __('Innings :number', ['number' => $innings->innings_number]) }}
                    </p>
                    <h2 class="mt-1 truncate text-lg font-semibold tracking-tight sm:text-xl">{{ $innings->battingTeam->team->name }} <span class="font-normal text-white/60">{{ __('batting') }}</span></h2>
                    <p class="truncate text-xs text-white/60">{{ $match->teamA->team->name }} {{ __('vs') }} {{ $match->teamB->team->name }} &middot; {{ $innings->bowlingTeam->team->name }} {{ __('bowling') }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="sc-score"><span id="scorer-score">{{ $innings->total_runs }}/{{ $innings->total_wickets }}</span></p>
                    <p class="mt-1 text-xs text-white/70 tabular-nums">
                        <span id="scorer-overs" class="text-sm font-semibold text-white">{{ $innings->oversDisplay() }}</span>
                        @if($match->overs_per_innings) / {{ $match->overs_per_innings }} @endif
                        {{ __('overs') }}
                    </p>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span id="scorer-crr" class="sc-chip">{{ __('CRR :rate', ['rate' => number_format($liveState['innings']['crr'], 2)]) }}</span>
                <span id="scorer-chase" class="sc-chip" @unless($liveState['chase']) hidden @endif>@if($liveState['chase'])@include('admin.scoring._chase', ['chase' => $liveState['chase']])@endif</span>
                <span id="scorer-free-hit" class="sc-chip sc-chip-warn" @unless($isFreeHit) hidden @endif>{{ __('Free Hit — only Run Out or Obstructing the Field may dismiss the batter') }}</span>
            </div>

            <div class="mt-3 grid grid-cols-3 gap-2">
                <div class="sc-seat sc-seat-on">
                    <p class="sc-seat-label">{{ __('Striker *') }}</p>
                    <div id="scorer-striker">@include('admin.scoring._batter-figure', ['player' => $liveState['striker']])</div>
                </div>
                <div class="sc-seat">
                    <p class="sc-seat-label">{{ __('Non-striker') }}</p>
                    <div id="scorer-non-striker">@include('admin.scoring._batter-figure', ['player' => $liveState['non_striker']])</div>
                </div>
                <div class="sc-seat">
                    <p class="sc-seat-label">{{ __('Bowler') }}</p>
                    <div id="scorer-bowler">@include('admin.scoring._bowler-figure', ['player' => $liveState['bowler']])</div>
                </div>
            </div>

            <div class="mt-3">
                <p class="sc-seat-label">{{ __('This over') }}</p>
                <div id="scorer-this-over" class="mt-1.5 flex min-h-9 flex-wrap items-center gap-1.5">@include('admin.scoring._over-strip', ['over' => $liveState['this_over']])</div>
            </div>
        </section>

        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <p id="scorer-partnership" class="ops-card px-4 py-3 text-xs text-slate-500">
                {{ __('Partnership:') }} <span class="font-semibold text-slate-800">{{ __(':runs runs (:balls balls)', ['runs' => $liveState['partnership']['runs'], 'balls' => $liveState['partnership']['balls']]) }}</span>
            </p>
            <p id="scorer-last-wicket" class="ops-card px-4 py-3 text-xs text-slate-500 sm:col-span-2">
                @include('admin.scoring._last-wicket', ['lastWicket' => $liveState['last_wicket']])
            </p>
        </div>

        <div class="ops-card mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
            <span class="ops-kicker">{{ __('Previous over') }}</span>
            <div id="scorer-previous-over" class="flex min-h-9 flex-wrap items-center gap-1.5 [&_.sc-ball]:bg-slate-100 [&_.sc-ball]:text-slate-700 [&_.sc-ball-dot]:text-slate-400 [&_.sc-ball-four]:bg-sky-100 [&_.sc-ball-four]:text-sky-800 [&_.sc-ball-six]:bg-green-100 [&_.sc-ball-six]:text-green-800 [&_.sc-ball-extra]:bg-amber-100 [&_.sc-ball-extra]:text-amber-800 [&_.sc-ball-wicket]:bg-red-100 [&_.sc-ball-wicket]:text-red-700 [&_.sc-ball-correctable]:ring-sky-300">@include('admin.scoring._over-strip', ['over' => $liveState['previous_over']])</div>
        </div>

        <div id="scorer-correction-panel" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 p-3"></div>

        @if($padVisible)
            <div id="scorer-quick-pad" class="sc-pad mt-3">
                <div id="scorer-situational-panel" class="sc-panel" hidden></div>

                <div class="sc-keys">
                    @foreach(['0','1','2','3','4','6'] as $run)
                        <button type="button" class="scorer-quick-run sc-key {{ $run === '4' ? 'sc-key-four' : ($run === '6' ? 'sc-key-six' : '') }}" data-runs="{{ $run }}" aria-label="{{ $run === '0' ? __('Dot ball') : ($run === '1' ? __('1 run') : __(':count runs', ['count' => $run])) }}">{{ $run }}</button>
                    @endforeach
                    <button type="button" id="scorer-quick-wide" class="sc-key sc-key-extra" aria-label="{{ __('Wide') }}">
                        {{ __('Wd') }}
                        <span data-extra-runs-for="wide" class="sc-plus" title="{{ __('Add extra physically-run runs') }}" role="button" aria-label="{{ __('Wide with extra runs') }}">+</span>
                    </button>
                    <button type="button" id="scorer-quick-noball" class="sc-key sc-key-extra" aria-label="{{ __('No ball') }}">
                        {{ __('Nb') }}
                        <span data-extra-runs-for="no_ball" class="sc-plus" title="{{ __('Add bat runs off this no ball') }}" role="button" aria-label="{{ __('No ball with bat runs') }}">+</span>
                    </button>
                    <button type="button" id="scorer-quick-bye" class="sc-key sc-key-extra" aria-label="{{ __('Byes') }}">{{ __('B') }}</button>
                    <button type="button" id="scorer-quick-legbye" class="sc-key sc-key-extra" aria-label="{{ __('Leg byes') }}">{{ __('LB') }}</button>
                    <button type="button" id="scorer-quick-wicket" class="sc-key sc-key-wicket" aria-label="{{ __('Wicket') }}">{{ __('W') }}</button>
                    @include('admin.scoring._undo', ['inPad' => true])
                </div>
                <p id="scorer-saving-indicator" class="mt-2 text-center text-[11px] text-slate-500" hidden>{{ __('Saving…') }}</p>
            </div>
        @endif
    </div>

    @unless($canRecordDelivery)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
            @if($isOverLimitReached)
                {{ __('Over limit reached for this innings. Complete the innings from the match page, or use Undo Last Delivery to make a correction.') }}
            @else
                {{ __('This innings can no longer be scored.') }}
            @endif
        </div>
    @endunless

    @if($canRecordDelivery && $awaitingSetup)
        {{-- Explicit Start Innings setup (S02 completion rule A) — asked
             ONCE per innings, before any ball can be recorded. --}}
        <div class="ops-card ops-card-body mt-4">
            <h3 class="ops-title">{{ __('Start Innings') }}</h3>
            <p class="mb-4 mt-1 text-xs text-slate-500">{{ __('Select the opening striker, non-striker, and first bowler. Ball entry begins once confirmed.') }}</p>

            <form method="POST" action="{{ route('admin.matches.innings.setup', [$match, $innings]) }}" novalidate>
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.select name="striker_match_player_id" :label="__('Striker')" :placeholder="__('Select striker')" :options="$battingOptions" />
                    <x-form.select name="non_striker_match_player_id" :label="__('Non-striker')" :placeholder="__('Select non-striker')" :options="$battingOptions" />
                    <x-form.select name="bowler_match_player_id" :label="__('First bowler')" :placeholder="__('Select bowler')" :options="$bowlingOptions" />
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-full sm:w-auto">
                    {{ __('Confirm and Start Scoring') }}
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
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 sm:p-5">
            <h3 class="text-base font-semibold tracking-tight text-amber-900">{{ __('Select New Batter') }}</h3>
            <p class="mb-4 mt-1 text-xs text-amber-800">
                @if($expectedBattingState['survivor_end'] === 'striker')
                    {{ __(":name continues at the striker's end. Choose the incoming batter for the other end.", ['name' => $playerName($expectedBattingState['survivor_id'])]) }}
                @else
                    {{ __(":name continues at the non-striker's end. Choose the incoming batter for the other end.", ['name' => $playerName($expectedBattingState['survivor_id'])]) }}
                @endif
            </p>

            <form method="POST" action="{{ route('admin.matches.innings.select-new-batter', [$match, $innings]) }}" novalidate>
                @csrf
                <x-form.select name="match_player_id" :label="__('New batter')" :placeholder="__('Select batter')" :options="$eligibleNewBatters" />
                <button type="submit" class="btn btn-primary btn-lg w-full sm:w-auto">
                    {{ __('Confirm New Batter') }}
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
        <div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 sm:p-5">
            <h3 class="text-base font-semibold tracking-tight text-amber-900">{{ __('Select Bowler') }}</h3>
            <p class="mb-4 mt-1 text-xs text-amber-800">{{ __("A new over is starting. The previous over's bowler cannot bowl this one.") }}</p>

            <form method="POST" action="{{ route('admin.matches.innings.select-over-bowler', [$match, $innings]) }}" novalidate>
                @csrf
                <x-form.select name="bowler_match_player_id" :label="__('Bowler')" :placeholder="__('Select bowler')" :options="$eligibleOverBowlers" />
                <button type="submit" class="btn btn-primary btn-lg w-full sm:w-auto">
                    {{ __('Confirm Bowler') }}
                </button>
            </form>
        </div>
    @elseif($canRecordDelivery)
        {{-- Frozen S02 rule 50: the quick-tap pad above covers the common
             case in one tap. This full manual form stays available,
             collapsed, for anything the quick pad's situational follow-
             ups don't cover (e.g. an unusual overthrow/short-run
             combination) — never removed, just no longer the primary
             entry point. Auto-expanded on a validation error so the
             scorer immediately sees what needs fixing. --}}
        <details class="ops-card mt-4" @if($errors->has('delivery') || $errors->any()) open @endif>
            <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 sm:px-5">
                <span class="ops-title">{{ __('Manual Entry (Advanced)') }}</span>
                <x-ops.icon name="chevron-down" class="h-4 w-4 text-slate-400" />
            </summary>
            <div class="border-t border-line p-4 sm:p-5">
            @error('delivery')
                <p class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.matches.innings.deliveries.store', [$match, $innings]) }}" novalidate>
                @csrf

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input name="runs_off_bat" :label="__('Runs off bat')" type="number" min="0" max="11" :value="0" />

                    <label class="mb-3.5 flex min-h-10 items-center gap-2 text-xs font-medium text-slate-700">
                        <input type="checkbox" id="is_wide" name="is_wide" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('is_wide')) />
                        {{ __('Wide') }}
                    </label>

                    <label class="mb-3.5 flex min-h-10 items-center gap-2 text-xs font-medium text-slate-700">
                        <input type="checkbox" id="is_no_ball" name="is_no_ball" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('is_no_ball')) />
                        {{ __('No Ball') }}
                    </label>
                </div>

                <div id="wide-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.input name="wide_running_runs" :label="__('Runs physically run on the wide')" type="number" min="0" max="6" :value="0" />
                </div>

                <div id="no-ball-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.input name="no_ball_reason" :label="__('No ball reason (optional)')" :placeholder="__('e.g. Overstepping')" />
                </div>

                <div id="bye-fields" class="grid gap-4 sm:grid-cols-3">
                    <x-form.input name="bye_runs" :label="__('Byes')" type="number" min="0" max="6" :value="0" />
                    <x-form.input name="leg_bye_runs" :label="__('Leg-byes')" type="number" min="0" max="6" :value="0" />
                </div>

                <label class="mb-3.5 flex min-h-10 items-center gap-2 text-xs font-medium text-slate-700">
                    <input type="checkbox" id="is_wicket" name="is_wicket" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('is_wicket')) />
                    {{ __('Wicket') }}
                </label>

                <div id="wicket-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.select
                        name="dismissed_match_player_id"
                        :label="__('Dismissed player')"
                        :placeholder="__('Select dismissed player')"
                        :options="[
                            $expectedBattingState['striker_id'] => $playerName($expectedBattingState['striker_id']),
                            $expectedBattingState['non_striker_id'] => $playerName($expectedBattingState['non_striker_id']),
                        ]"
                    />
                    <x-form.select
                        name="wicket_type"
                        :label="__('Dismissal type')"
                        :placeholder="__('Select dismissal type')"
                        :options="collect($wicketTypes)->mapWithKeys(fn ($type) => [$type => __(ucwords(str_replace('_', ' ', $type)))])"
                    />
                    <x-form.select name="fielder_match_player_id" :label="__('Fielder (optional)')" :placeholder="__('None')" :options="$bowlingOptions" />
                </div>

                <div id="survivor-end-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.select
                        name="confirmed_survivor_end"
                        :label="__('Confirm surviving batter\'s actual end (optional, run out / obstructing only)')"
                        :placeholder="__('Use computed end')"
                        :options="['striker' => __('Striker'), 'non_striker' => __('Non-striker')]"
                    />
                </div>

                <details class="mb-3.5 rounded-lg border border-line p-3">
                    <summary class="cursor-pointer text-xs font-medium text-slate-600">{{ __('Advanced (short run)') }}</summary>
                    <div class="mt-3 grid gap-4 sm:grid-cols-3">
                        <label class="mb-3.5 flex min-h-10 items-center gap-2 text-xs font-medium text-slate-700">
                            <input type="checkbox" name="is_short_run" value="1" class="h-4 w-4 rounded border-slate-300" @checked(old('is_short_run')) />
                            {{ __('Short run called') }}
                        </label>
                        <x-form.input name="runs_physically_run" :label="__('Runs physically completed (if different from credited)')" type="number" min="0" max="11" />
                    </div>
                </details>

                <div class="mb-3.5">
                    <label for="commentary" class="mb-1 block text-xs font-medium text-slate-700">{{ __('Commentary') }}</label>
                    <textarea
                        id="commentary"
                        name="commentary"
                        rows="2"
                        class="w-full rounded-lg border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('commentary') ? 'border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-brand focus:ring-brand/30' }}"
                    >{{ old('commentary') }}</textarea>
                    @error('commentary')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-full sm:w-auto">
                    {{ __('Record Delivery') }}
                </button>
            </form>
            </div>
        </details>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <details class="ops-card group">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3">
                    <span class="ops-title">{{ __('Change Strike') }}</span>
                    <x-ops.icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-line p-4">
                    <p class="mb-3 text-xs text-slate-500">{{ __('Corrects the actual ends — no delivery, runs, or wickets change.') }}</p>
                    <form method="POST" action="{{ route('admin.matches.innings.change-strike', [$match, $innings]) }}">
                        @csrf
                        <x-form.select name="striker_match_player_id" :label="__('Striker')" :placeholder="__('Select striker')" :options="$battingOptions" />
                        <x-form.select name="non_striker_match_player_id" :label="__('Non-striker')" :placeholder="__('Select non-striker')" :options="$battingOptions" />
                        <x-form.input name="reason" :label="__('Reason')" :placeholder="__('e.g. Wrong ends recorded')" />
                        <button type="submit" class="btn btn-secondary">{{ __('Correct Strike') }}</button>
                    </form>
                </div>
            </details>

            <details class="ops-card group">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3">
                    <span class="ops-title">{{ __('Retire Batter') }}</span>
                    <x-ops.icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-line p-4">
                    <p class="mb-3 text-xs text-slate-500">{{ __('Retired Hurt may return later; Retired Out counts as a wicket and cannot.') }}</p>
                    <form method="POST" action="{{ route('admin.matches.innings.retire-batter', [$match, $innings]) }}">
                        @csrf
                        <x-form.select name="match_player_id" :label="__('Batter')" :placeholder="__('Select batter at the crease')" :options="$battingOptions" />
                        <x-form.select name="type" :label="__('Type')" :placeholder="__('Select type')" :options="['hurt' => __('Retired Hurt'), 'out' => __('Retired Out')]" />
                        <x-form.input name="reason" :label="__('Reason')" :placeholder="__('e.g. Injury')" />
                        <button type="submit" class="btn btn-secondary">{{ __('Retire Batter') }}</button>
                    </form>
                </div>
            </details>

            <details class="ops-card group">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3">
                    <span class="ops-title">{{ __('Change Bowler Mid-Over') }}</span>
                    <x-ops.icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-line p-4">
                    <p class="mb-3 text-xs text-slate-500">{{ __('For a genuine mid-over swap (e.g. injury). Balls already bowled keep their original bowler.') }}</p>
                    <form method="POST" action="{{ route('admin.matches.innings.change-bowler', [$match, $innings]) }}">
                        @csrf
                        <x-form.select
                            name="bowler_match_player_id"
                            :label="__('Replacement bowler')"
                            :placeholder="__('Select bowler')"
                            :options="$bowlingOptions->except($expectedBattingState['bowler_id'])"
                        />
                        <x-form.input name="reason" :label="__('Reason')" :placeholder="__('e.g. Injury')" />
                        <button type="submit" class="btn btn-secondary">{{ __('Change Bowler') }}</button>
                    </form>
                </div>
            </details>

            <details class="ops-card group">
                <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-2 px-4 py-3">
                    <span class="ops-title">{{ __('Penalty Runs') }}</span>
                    <x-ops.icon name="chevron-down" class="h-4 w-4 text-slate-400 transition group-open:rotate-180" />
                </summary>
                <div class="border-t border-line p-4">
                    <p class="mb-3 text-xs text-slate-500">{{ __("Standard 5-run award as Penalty extras. If the awarded team hasn't batted yet, it's added to their innings total as soon as that innings starts.") }}</p>
                    <form method="POST" action="{{ route('admin.matches.innings.penalty-runs', [$match, $innings]) }}">
                        @csrf
                        <x-form.select
                            name="awarded_team_id"
                            :label="__('Awarded to')"
                            :placeholder="__('Select team')"
                            :options="[
                                $match->edition_team_a_id => $match->teamA->team->name,
                                $match->edition_team_b_id => $match->teamB->team->name,
                            ]"
                        />
                        <x-form.input name="reason" :label="__('Reason')" :placeholder="__('e.g. Fielding restriction breach')" />
                        <button type="submit" class="btn btn-secondary">{{ __('Award 5 Penalty Runs') }}</button>
                    </form>
                </div>
            </details>
        </div>
    @endif

    <section class="ops-card mt-4">
        <div class="ops-card-head">
            <h3 class="ops-title">{{ __('Recent Deliveries') }}</h3>

            {{-- With the run keys on screen, Undo is one of them; otherwise it lives here. --}}
            @if(! $padVisible && $liveState['can_undo'])
                @include('admin.scoring._undo', ['inPad' => false])
            @endif
        </div>

        {{-- A phone gets a card per ball; a wide screen gets the table. --}}
        <ul class="divide-y divide-line md:hidden">
            @forelse($recentDeliveries as $delivery)
                <li class="flex items-start gap-3 px-4 py-3">
                    <span class="w-12 shrink-0 pt-0.5 text-sm font-bold tabular-nums text-slate-900">
                        {{ $delivery->over_number }}.{{ $delivery->ball_number }}
                        @if($delivery->is_free_hit)
                            <span class="ml-0.5 rounded bg-amber-50 px-1 text-[10px] font-semibold text-amber-700">FH</span>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1 text-xs text-slate-500">
                        <p class="truncate"><span class="font-semibold text-slate-800">{{ $delivery->striker->teamPlayer->playerRegistration->player->name }}</span> &middot; {{ $delivery->bowler->teamPlayer->playerRegistration->player->name }}</p>
                        <p class="mt-0.5">
                            <span class="font-semibold text-slate-800">{{ $delivery->total_runs }} {{ $delivery->total_runs == 1 ? __('run') : __('runs') }}</span>
                            @if($delivery->is_wide) &middot; {{ __('Wide') }}
                            @elseif($delivery->is_no_ball) &middot; {{ __('No-ball') }}
                            @elseif($delivery->bye_runs) &middot; {{ __('Bye') }}
                            @elseif($delivery->leg_bye_runs) &middot; {{ __('Leg-bye') }}
                            @endif
                            @if($delivery->is_wicket)
                                &middot; <span class="font-semibold text-red-600">{{ __(ucwords(str_replace('_', ' ', $delivery->wicket_type))) }} ({{ $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name }})</span>
                            @endif
                        </p>
                        @if($delivery->commentary)
                            <p class="mt-0.5 text-slate-400">{{ $delivery->commentary }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-[13px] text-slate-400">{{ __('No deliveries recorded yet.') }}</li>
            @endforelse
        </ul>

        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[720px] text-left text-[13px]">
                <thead class="border-b border-line bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-semibold">{{ __('Ball') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Batter') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Bowler') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Runs') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Extra') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Wicket') }}</th>
                        <th class="px-4 py-2 font-semibold">{{ __('Commentary') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @forelse($recentDeliveries as $delivery)
                        <tr class="hover:bg-hover/50">
                            <td class="whitespace-nowrap px-4 py-2 font-semibold tabular-nums text-slate-700">
                                {{ $delivery->over_number }}.{{ $delivery->ball_number }}
                                @if($delivery->is_free_hit)
                                    <span class="ml-1 rounded bg-amber-50 px-1 text-[10px] font-semibold text-amber-700">FH</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-700">{{ $delivery->striker->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-4 py-2 text-slate-700">{{ $delivery->bowler->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-4 py-2 font-semibold tabular-nums text-slate-800">{{ $delivery->total_runs }}</td>
                            <td class="px-4 py-2 text-slate-500">
                                @if($delivery->is_wide)
                                    {{ __('Wide') }}
                                @elseif($delivery->is_no_ball)
                                    {{ __('No-ball') }}
                                @elseif($delivery->bye_runs)
                                    {{ __('Bye') }}
                                @elseif($delivery->leg_bye_runs)
                                    {{ __('Leg-bye') }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-700">
                                @if($delivery->is_wicket)
                                    <span class="font-semibold text-red-600">{{ __(ucwords(str_replace('_', ' ', $delivery->wicket_type))) }}</span> ({{ $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name }})
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-2 text-slate-500">{{ $delivery->commentary ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-400">{{ __('No deliveries recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
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

    @vite(['resources/js/admin-scoring.js'])
@endsection
