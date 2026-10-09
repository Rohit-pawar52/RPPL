@extends('layouts.admin')

@section('title', 'Match Details')

@section('actions')
    @if($match->innings_count > 0)
        <a href="{{ route('admin.matches.scorecard', $match) }}" class="btn btn-secondary">
            <x-icon name="document-chart" class="h-4 w-4" />
            View Scorecard
        </a>
    @endif
    @can('update', $match)
        <a href="{{ route('admin.matches.edit', $match) }}" class="btn btn-secondary">
            <x-icon name="pencil" class="h-4 w-4" />
            Edit
        </a>
    @endcan
@endsection

@section('content')
    @php
        $status = $match->match_status;
        $first = $match->firstInnings;
        $second = $match->secondInnings;
        $liveInnings = collect([$first, $second])->filter()->firstWhere('status', 'live');
        $canScoreLive = $liveInnings && auth()->user()->can('score', $match);
        $canFlow = auth()->user()->can('manageMatchFlow', $match);
        $canInnings = auth()->user()->can('manageInnings', $match);
        $tossSaved = $match->toss_winner_team_id && $match->toss_decision;

        // Which step is next, so the page can say it and highlight it.
        $step = match (true) {
            in_array($status, ['abandoned', 'cancelled'], true) => 'closed',
            $status === 'completed' => 'done',
            $status === 'live' => $resultPreview ? 'result' : 'innings',
            $status === 'toss' => 'flow',
            $canStartToss => 'flow',
            default => 'xi',
        };

        [$nextTitle, $nextHint] = match (true) {
            $step === 'xi' => ['Choose the Playing XI', 'Both teams need their players selected before the toss.'],
            $step === 'flow' && $status === 'scheduled' => ['Start the toss', 'The Playing XI is ready. Start the toss, then record who won it.'],
            $step === 'flow' && ! $tossSaved => ['Record the toss', 'Pick the toss winner and what they chose.'],
            $step === 'flow' => ['Start the match', 'The toss is saved. Starting locks the Playing XI and the toss.'],
            $step === 'innings' && ! $first => ['Start the first innings', 'The match is live. Start innings 1 to begin scoring.'],
            $step === 'innings' && $first->status === 'live' => ['Innings 1 is live', 'Keep scoring ball by ball.'],
            $step === 'innings' && ! $second => ['Start the second innings', 'Innings 1 is complete.'],
            $step === 'innings' && $second->status === 'live' => ['Innings 2 is live', 'Keep scoring ball by ball.'],
            $step === 'result' => ['Finalize the result', 'Both innings are complete. Check the expected result and finalize.'],
            $step === 'done' => ['Match completed', $match->match_result ?: 'The result is recorded.'],
            default => ['This match is '.$status, 'No further steps.'],
        };

        $stepClass = fn (string $key) => $step === $key ? 'ring-2 ring-brand' : '';
        $teamAName = $match->teamA->team->name;
        $teamBName = $match->teamB->team->name;
    @endphp

    <div class="mb-3">
        <a href="{{ route('admin.matches.index') }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to matches
        </a>
    </div>

    {{-- The match at a glance. --}}
    <header class="rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-4 text-white shadow-raised sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="sc-kicker">
                {{ $match->edition->name }}
                @if($match->match_number) &middot; Match {{ $match->match_number }} @endif
                @if($match->match_stage) &middot; {{ ucwords(str_replace('_', ' ', $match->match_stage)) }} @endif
            </p>
            <x-status-badge :status="$status" />
        </div>
        <h2 class="mt-2 break-words text-2xl font-bold tracking-tight sm:text-3xl">
            {{ $teamAName }} <span class="font-normal text-white/50">vs</span> {{ $teamBName }}
        </h2>
        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-xs sm:grid-cols-4">
            <div>
                <dt class="sc-kicker">Venue</dt>
                <dd class="mt-0.5 text-[13px] font-medium">{{ $match->venue->name ?? 'TBD' }}</dd>
            </div>
            <div>
                <dt class="sc-kicker">Scheduled</dt>
                <dd class="mt-0.5 text-[13px] font-medium">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
            </div>
            <div>
                <dt class="sc-kicker">Overs per innings</dt>
                <dd class="mt-0.5 text-[13px] font-medium tabular-nums">{{ $match->overs_per_innings }}</dd>
            </div>
            <div>
                <dt class="sc-kicker">Started / Completed</dt>
                <dd class="mt-0.5 text-[13px] font-medium">
                    {{ display_datetime($match->started_at, 'd M Y, h:i A') ?? '—' }}
                    /
                    {{ display_datetime($match->completed_at, 'd M Y, h:i A') ?? '—' }}
                </dd>
            </div>
        </dl>
        @if($first || $second)
            <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach(array_filter([$first, $second]) as $innings)
                    <div class="flex min-w-0 items-center justify-between gap-3 rounded-xl bg-white/10 px-3 py-2.5">
                        <div class="min-w-0">
                            <p class="sc-kicker truncate">Innings {{ $innings->innings_number }} &middot; {{ $innings->battingTeam->team->name }}</p>
                            <p class="text-xl font-bold tabular-nums">{{ $innings->total_runs }}/{{ $innings->total_wickets }}
                                <span class="text-xs font-medium text-white/70">({{ $innings->oversDisplay() }} ov)</span></p>
                        </div>
                        <x-status-badge :status="$innings->status" />
                    </div>
                @endforeach
            </div>
        @endif
    </header>

    {{-- What to do next. --}}
    <section class="mt-4 flex flex-wrap items-center gap-3 rounded-xl border border-brand/30 bg-brand-soft px-4 py-3 sm:px-5" aria-label="Next step">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand text-brand-fg"><x-ops.icon name="{{ in_array($step, ['done', 'closed'], true) ? 'check' : 'flag' }}" class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1 basis-48">
            <p class="ops-kicker !text-brand">{{ in_array($step, ['done', 'closed'], true) ? 'Status' : 'Next step' }}</p>
            <p class="text-[15px] font-semibold text-slate-900">{{ $nextTitle }}</p>
            <p class="text-xs text-slate-600">{{ $nextHint }}</p>
        </div>
        @if($canScoreLive)
            <a href="{{ route('admin.matches.innings.score', [$match, $liveInnings]) }}" class="btn btn-primary btn-lg max-sm:w-full"><x-ops.icon name="bolt" /> Score innings {{ $liveInnings->innings_number }}</a>
        @elseif($step === 'xi')
            <a href="{{ route('admin.matches.players.index', $match) }}" class="btn btn-primary btn-lg max-sm:w-full"><x-icon name="users" class="h-4 w-4" /> Choose Playing XI</a>
        @elseif($step === 'flow' && $canFlow)
            <a href="#step-flow" class="btn btn-primary btn-lg max-sm:w-full">Go to the step <x-ops.icon name="chevron-down" /></a>
        @elseif($step === 'innings' && $canInnings)
            <a href="#step-innings" class="btn btn-primary btn-lg max-sm:w-full">Go to the step <x-ops.icon name="chevron-down" /></a>
        @elseif($step === 'result')
            <a href="#step-innings" class="btn btn-primary btn-lg max-sm:w-full">Go to the step <x-ops.icon name="chevron-down" /></a>
        @endif
    </section>

    <div class="mt-4 space-y-4">
        {{-- Step 1: the squads --}}
        <section class="ops-card {{ $stepClass('xi') }}" id="step-xi">
            <div class="ops-card-head">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">1</span>
                    <div>
                        <h3 class="ops-title">Playing XI</h3>
                        <p class="text-xs text-slate-500">
                            {{ $teamAName }}: {{ $teamASelectedCount }} selected
                            &middot;
                            {{ $teamBName }}: {{ $teamBSelectedCount }} selected
                        </p>
                    </div>
                </div>
                <a href="{{ route('admin.matches.players.index', $match) }}" class="btn btn-secondary btn-sm">
                    <x-icon name="users" class="h-3.5 w-3.5" />
                    Manage Playing XI
                </a>
            </div>
        </section>

        {{-- Step 2: toss and start --}}
        @if($canFlow)
            <section class="ops-card {{ $stepClass('flow') }}" id="step-flow">
                <div class="ops-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">2</span>
                        <h3 class="ops-title">Match Flow</h3>
                    </div>
                    @if($step === 'flow')<span class="ops-pill ops-pill-amber">Do this now</span>@endif
                </div>
                <div class="ops-card-body">
                    @if($match->match_status === 'scheduled')
                        <p class="mb-3 text-xs text-slate-500">
                            Team A: {{ $teamASelectedCount }} selected &middot; Team B: {{ $teamBSelectedCount }} selected
                        </p>

                        <form method="POST" action="{{ route('admin.matches.start-toss', $match) }}">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-primary btn-lg max-sm:w-full"
                                @disabled(! $canStartToss)
                            >
                                Start Toss
                            </button>
                        </form>

                        @unless($canStartToss)
                            <p class="mt-2 text-xs text-amber-700">Both teams must have selected players before starting the toss.</p>
                        @endunless

                        @can('cancelMatch', $match)
                            <form
                                method="POST"
                                action="{{ route('admin.matches.cancel', $match) }}"
                                class="mt-4 border-t border-line pt-4"
                                onsubmit="event.preventDefault(); window.confirmAction({title: 'Cancel this match?', text: 'Cancel this scheduled match? This cannot be undone.', confirmButtonText: 'Yes, cancel match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                            >
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-danger-soft"
                                    @disabled(! $canCancelMatch)
                                >
                                    Cancel Match
                                </button>
                            </form>
                        @endcan
                    @elseif($match->match_status === 'toss')
                        <form method="POST" action="{{ route('admin.matches.toss.update', $match) }}" class="mb-3 grid gap-x-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,12rem)_auto] sm:items-end" novalidate>
                            @csrf
                            @method('PUT')
                            <x-form.select
                                name="toss_winner_team_id"
                                label="Toss winner"
                                placeholder="Select team"
                                :options="[
                                    $match->edition_team_a_id => $match->teamA->team->name,
                                    $match->edition_team_b_id => $match->teamB->team->name,
                                ]"
                                :value="$match->toss_winner_team_id"
                            />
                            <x-form.select
                                name="toss_decision"
                                label="Decision"
                                placeholder="Select"
                                :options="['bat' => 'Bat', 'bowl' => 'Bowl']"
                                :value="$match->toss_decision"
                            />
                            <div class="mb-3.5">
                                <button type="submit" class="btn btn-primary min-h-10 max-sm:w-full">
                                    Save Toss
                                </button>
                            </div>
                        </form>

                        @if($match->toss_winner_team_id && $match->toss_decision)
                            <div class="border-t border-line pt-4">
                                <form
                                    method="POST"
                                    action="{{ route('admin.matches.start', $match) }}"
                                    id="start-match-form"
                                    onsubmit="event.preventDefault(); window.confirmAction({title: 'Start this match?', text: 'This locks the Playing XI and toss for this match.', confirmButtonText: 'Yes, start match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="btn btn-primary btn-lg max-sm:w-full"
                                        @disabled(! $canStartMatch)
                                    >
                                        Start Match
                                    </button>
                                </form>

                                @unless($canStartMatch)
                                    <p class="mt-2 text-xs text-amber-700">Both teams must have selected players before starting the match.</p>
                                @endunless
                            </div>
                        @endif

                        @can('abandonMatch', $match)
                            <div class="mt-4 border-t border-line pt-4">
                                <form
                                    method="POST"
                                    action="{{ route('admin.matches.abandon', $match) }}"
                                    onsubmit="event.preventDefault(); window.confirmAction({title: 'Abandon this match?', text: 'Existing scoring data will be preserved.', confirmButtonText: 'Yes, abandon match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="btn btn-danger-soft"
                                        @disabled(! $canAbandonMatch)
                                    >
                                        Abandon Match
                                    </button>
                                </form>
                            </div>
                        @endcan
                    @else
                        <p class="text-xs text-slate-500">
                            Status: <span class="font-semibold capitalize text-slate-800">{{ $match->match_status }}</span>
                            @if($match->started_at)
                                &middot; Started: {{ display_datetime($match->started_at, 'd M Y, h:i A') }}
                            @endif
                        </p>

                        @if($match->tossWinner)
                            <p class="mt-1 text-xs text-slate-500">
                                Toss: {{ $match->tossWinner->team->name }} won and chose to {{ $match->toss_decision }}
                            </p>
                        @endif

                        @if($match->match_status === 'live')
                            <p class="mt-1 text-xs text-slate-400">Playing XI locked.</p>

                            @can('abandonMatch', $match)
                                <form
                                    method="POST"
                                    action="{{ route('admin.matches.abandon', $match) }}"
                                    class="mt-4 border-t border-line pt-4"
                                    onsubmit="event.preventDefault(); window.confirmAction({title: 'Abandon this match?', text: 'Existing scoring data will be preserved.', confirmButtonText: 'Yes, abandon match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        class="btn btn-danger-soft"
                                        @disabled(! $canAbandonMatch)
                                    >
                                        Abandon Match
                                    </button>
                                </form>
                            @endcan
                        @endif
                    @endif
                </div>
            </section>
        @endif

        {{-- Step 3: innings --}}
        @can('manageInnings', $match)
            <section class="ops-card {{ $stepClass('innings') }} {{ $stepClass('result') }}" id="step-innings">
                <div class="ops-card-head">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600">3</span>
                        <h3 class="ops-title">Innings</h3>
                    </div>
                    @if($step === 'innings' || $step === 'result')<span class="ops-pill ops-pill-amber">Do this now</span>@endif
                </div>
                <div class="ops-card-body">
                    @if(! $match->firstInnings)
                        <p class="text-xs text-slate-500">No innings started.</p>

                        @if($firstInningsPreview)
                            <p class="mt-2 text-xs text-slate-500">
                                First batting: <span class="font-semibold text-slate-800">{{ $firstInningsPreview['battingTeamName'] }}</span>
                                &middot;
                                Bowling: <span class="font-semibold text-slate-800">{{ $firstInningsPreview['bowlingTeamName'] }}</span>
                            </p>
                        @endif

                        <form method="POST" action="{{ route('admin.matches.innings.first.start', $match) }}" class="mt-3">
                            @csrf
                            <button
                                type="submit"
                                class="btn btn-primary btn-lg max-sm:w-full"
                                @disabled(! $canStartFirstInnings)
                            >
                                Start First Innings
                            </button>
                        </form>
                    @else
                        <div class="space-y-2">
                            @include('admin.matches._innings-card', ['innings' => $match->firstInnings, 'match' => $match])

                            @if($match->secondInnings)
                                @include('admin.matches._innings-card', ['innings' => $match->secondInnings, 'match' => $match])
                            @endif
                        </div>

                        @if($match->firstInnings->status === 'live')
                            @include('admin.matches._complete-innings-form', ['innings' => $match->firstInnings])
                        @elseif($match->firstInnings->status === 'completed')
                            @include('admin.matches._reopen-innings-form', ['innings' => $match->firstInnings])
                        @endif

                        @if(! $match->secondInnings)
                            <p class="mt-4 text-xs text-slate-500">
                                Next batting: <span class="font-semibold text-slate-800">{{ $match->firstInnings->bowlingTeam->team->name }}</span>
                            </p>
                            <form method="POST" action="{{ route('admin.matches.innings.second.start', $match) }}" class="mt-2">
                                @csrf
                                <button
                                    type="submit"
                                    class="btn btn-primary btn-lg max-sm:w-full"
                                    @disabled(! $canStartSecondInnings)
                                >
                                    Start Second Innings
                                </button>
                            </form>
                        @elseif($match->secondInnings->status === 'live')
                            @include('admin.matches._complete-innings-form', ['innings' => $match->secondInnings])
                        @elseif($match->match_status === 'live')
                            @include('admin.matches._reopen-innings-form', ['innings' => $match->secondInnings])

                            @can('finalizeResult', $match)
                                @if($resultPreview && $resultPreview['result_type'] === 'tied')
                                    @include('admin.matches._super-over-form')
                                @endif
                            @endcan
                            {{-- Both innings are completed but the match itself
                                 isn't yet — this is the one gap Phase 3.11/3.12
                                 deliberately leave open until "Finalize Match"
                                 is used. Once match_status is 'completed', the
                                 Toss & Result card below already shows the
                                 stored final result, so nothing further renders
                                 here. --}}
                            @can('finalizeResult', $match)
                                @if($resultPreview)
                                    <div class="mt-4 rounded-xl border border-line bg-slate-50 p-4">
                                        <p class="ops-kicker">Expected result:</p>
                                        <p class="mt-1 text-lg font-bold tracking-tight text-slate-900">{{ $resultPreview['match_result'] }}</p>
                                    </div>

                                    <form
                                        method="POST"
                                        action="{{ route('admin.matches.finalize', $match) }}"
                                        class="mt-3"
                                        onsubmit="event.preventDefault(); window.confirmAction({title: 'Finalize match?', text: 'This will mark the match as completed and lock further scoring.', confirmButtonText: 'Yes, finalize'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-lg max-sm:w-full"
                                            @disabled(! $canFinalize)
                                        >
                                            Finalize Match
                                        </button>
                                    </form>
                                @else
                                    <p class="mt-3 text-xs text-slate-500">Match result pending.</p>
                                @endif
                            @else
                                <p class="mt-3 text-xs text-slate-500">Match result pending.</p>
                            @endcan
                        @endif
                    @endif
                </div>
            </section>
        @endcan

        {{-- Toss and result --}}
        @if($match->toss_winner_team_id || $match->winner_team_id || $match->match_result)
            <section class="ops-card">
                <div class="ops-card-head"><h3 class="ops-title">Toss &amp; Result</h3></div>
                <div class="ops-card-body">
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-4 text-xs sm:grid-cols-4">
                        <div>
                            <dt class="ops-kicker">Toss</dt>
                            <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                                @if($match->tossWinner)
                                    {{ $match->tossWinner->team->name }} chose to {{ $match->toss_decision ?? '—' }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="ops-kicker">Winner</dt>
                            <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $match->winner->team->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="ops-kicker">Margin</dt>
                            <dd class="mt-0.5 text-[13px] font-medium text-slate-800">
                                @if($match->win_margin && $match->win_margin_type)
                                    {{ $match->win_margin }} {{ $match->win_margin_type }}
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="ops-kicker">Result</dt>
                            <dd class="mt-0.5 text-[13px] font-medium text-slate-800">{{ $match->match_result ?? '—' }}</dd>
                        </div>
                    </dl>

                    {{--
                        Result-notification status — never rendered for a match
                        the concept doesn't apply to (not yet completed). "Queued"
                        only ever means the job was queued, never that Firebase
                        finished — see GameMatch::resultNotificationStatusLabel().
                    --}}
                    @php $resultNotificationStatus = $match->resultNotificationStatusLabel(); @endphp
                    @if($resultNotificationStatus !== 'not_applicable')
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-4">
                            <p class="text-xs text-slate-500">
                                Result Notification:
                                <span class="font-semibold text-slate-800">
                                    {{ match($resultNotificationStatus) { 'pending' => 'Not sent', 'dispatched' => 'Queued', 'completed' => 'Completed' } }}
                                </span>
                            </p>

                            @can('finalizeResult', $match)
                                @if($resultNotificationStatus === 'pending')
                                    <form method="POST" action="{{ route('admin.matches.resend-result-notification', $match) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm">
                                            Send Result Notification
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    @endif

                    @can('reopenResult', $match)
                        @if($match->match_status === 'completed')
                            <form
                                method="POST"
                                action="{{ route('admin.matches.reopen', $match) }}"
                                class="mt-4 grid gap-x-3 border-t border-line pt-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end"
                            >
                                @csrf
                                <x-form.input name="reason" label="Reason for reopening this match (admin only)" placeholder="e.g. Scoring error found after finalization" />
                                <div class="mb-3.5">
                                    <button type="submit" class="btn btn-danger-soft min-h-10 max-sm:w-full">
                                        Reopen Match
                                    </button>
                                </div>
                            </form>
                        @endif
                    @endcan
                </div>
            </section>
        @endif
    </div>
@endsection
