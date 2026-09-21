@extends('layouts.admin')

@section('title', 'Match Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to matches
        </a>
        <div class="flex items-center gap-2">
            @if($match->innings_count > 0)
                <a
                    href="{{ route('admin.matches.scorecard', $match) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    View Scorecard
                </a>
            @endif
            @can('update', $match)
                <a
                    href="{{ route('admin.matches.edit', $match) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                    Edit
                </a>
            @endcan
        </div>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h2>
            <x-status-badge :status="$match->match_status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $match->edition->name }}
            @if($match->match_number)
                &middot; Match {{ $match->match_number }}
            @endif
            @if($match->match_stage)
                &middot; {{ ucwords(str_replace('_', ' ', $match->match_stage)) }}
            @endif
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
            <div>
                <dt class="text-neutral-400">Venue</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->venue->name ?? 'TBD' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Scheduled</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Overs per innings</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->overs_per_innings }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Started / Completed</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">
                    {{ $match->started_at?->format('d M Y, h:i A') ?? '—' }}
                    /
                    {{ $match->completed_at?->format('d M Y, h:i A') ?? '—' }}
                </dd>
            </div>
        </dl>
    </div>

    @if($match->toss_winner_team_id || $match->winner_team_id || $match->match_result)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Toss &amp; Result</h3>

            <dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-neutral-400">Toss</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">
                        @if($match->tossWinner)
                            {{ $match->tossWinner->team->name }} chose to {{ $match->toss_decision ?? '—' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Winner</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->winner->team->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Margin</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">
                        @if($match->win_margin && $match->win_margin_type)
                            {{ $match->win_margin }} {{ $match->win_margin_type }}
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Result</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->match_result ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    @endif

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <x-stat-card label="Squad players" :value="$match->match_players_count" icon="users" />
        <x-stat-card label="Innings" :value="$match->innings_count" icon="trophy" />
    </div>

    @can('manageMatchFlow', $match)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Match Flow</h3>

            @if($match->match_status === 'scheduled')
                <p class="mb-3 text-xs text-neutral-500">
                    Team A: {{ $teamASelectedCount }} selected &middot; Team B: {{ $teamBSelectedCount }} selected
                </p>

                <form method="POST" action="{{ route('admin.matches.start-toss', $match) }}">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                        @disabled(! $canStartToss)
                    >
                        Start Toss
                    </button>
                </form>

                @unless($canStartToss)
                    <p class="mt-2 text-xs text-amber-600">Both teams must have selected players before starting the toss.</p>
                @endunless

                @can('cancelMatch', $match)
                    <form
                        method="POST"
                        action="{{ route('admin.matches.cancel', $match) }}"
                        class="mt-3 border-t border-neutral-100 pt-3"
                        onsubmit="event.preventDefault(); window.confirmAction({title: 'Cancel this match?', text: 'Cancel this scheduled match? This cannot be undone.', confirmButtonText: 'Yes, cancel match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md bg-red-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                            @disabled(! $canCancelMatch)
                        >
                            Cancel Match
                        </button>
                    </form>
                @endcan
            @elseif($match->match_status === 'toss')
                <form method="POST" action="{{ route('admin.matches.toss.update', $match) }}" class="mb-3 flex flex-wrap items-end gap-2" novalidate>
                    @csrf
                    @method('PUT')
                    <div class="w-44">
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
                    </div>
                    <div class="w-32">
                        <x-form.select
                            name="toss_decision"
                            label="Decision"
                            placeholder="Select"
                            :options="['bat' => 'Bat', 'bowl' => 'Bowl']"
                            :value="$match->toss_decision"
                        />
                    </div>
                    <button type="submit" class="mb-3.5 rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500">
                        Save Toss
                    </button>
                </form>

                @if($match->toss_winner_team_id && $match->toss_decision)
                    <div class="border-t border-neutral-100 pt-3">
                        <form
                            method="POST"
                            action="{{ route('admin.matches.start', $match) }}"
                            id="start-match-form"
                            onsubmit="event.preventDefault(); window.confirmAction({title: 'Start this match?', text: 'This locks the Playing XI and toss for this match.', confirmButtonText: 'Yes, start match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md bg-green-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-green-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                @disabled(! $canStartMatch)
                            >
                                Start Match
                            </button>
                        </form>

                        @unless($canStartMatch)
                            <p class="mt-2 text-xs text-amber-600">Both teams must have selected players before starting the match.</p>
                        @endunless
                    </div>
                @endif

                @can('abandonMatch', $match)
                    <div class="mt-3 border-t border-neutral-100 pt-3">
                        <form
                            method="POST"
                            action="{{ route('admin.matches.abandon', $match) }}"
                            onsubmit="event.preventDefault(); window.confirmAction({title: 'Abandon this match?', text: 'Existing scoring data will be preserved.', confirmButtonText: 'Yes, abandon match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md bg-red-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                @disabled(! $canAbandonMatch)
                            >
                                Abandon Match
                            </button>
                        </form>
                    </div>
                @endcan
            @else
                <p class="text-xs text-neutral-500">
                    Status: <span class="font-medium capitalize text-neutral-800">{{ $match->match_status }}</span>
                    @if($match->started_at)
                        &middot; Started: {{ $match->started_at->format('d M Y, h:i A') }}
                    @endif
                </p>

                @if($match->tossWinner)
                    <p class="mt-1 text-xs text-neutral-500">
                        Toss: {{ $match->tossWinner->team->name }} won and chose to {{ $match->toss_decision }}
                    </p>
                @endif

                @if($match->match_status === 'live')
                    <p class="mt-1 text-xs text-neutral-400">Playing XI locked.</p>

                    @can('abandonMatch', $match)
                        <form
                            method="POST"
                            action="{{ route('admin.matches.abandon', $match) }}"
                            class="mt-3 border-t border-neutral-100 pt-3"
                            onsubmit="event.preventDefault(); window.confirmAction({title: 'Abandon this match?', text: 'Existing scoring data will be preserved.', confirmButtonText: 'Yes, abandon match'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                        >
                            @csrf
                            <button
                                type="submit"
                                class="rounded-md bg-red-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-red-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                @disabled(! $canAbandonMatch)
                            >
                                Abandon Match
                            </button>
                        </form>
                    @endcan
                @endif
            @endif
        </div>
    @endcan

    @can('manageInnings', $match)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Innings</h3>

            @if(! $match->firstInnings)
                <p class="text-xs text-neutral-500">No innings started.</p>

                @if($firstInningsPreview)
                    <p class="mt-2 text-xs text-neutral-500">
                        First batting: <span class="font-medium text-neutral-800">{{ $firstInningsPreview['battingTeamName'] }}</span>
                        &middot;
                        Bowling: <span class="font-medium text-neutral-800">{{ $firstInningsPreview['bowlingTeamName'] }}</span>
                    </p>
                @endif

                <form method="POST" action="{{ route('admin.matches.innings.first.start', $match) }}" class="mt-3">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
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
                    <form
                        method="POST"
                        action="{{ route('admin.matches.innings.complete', [$match, $match->firstInnings]) }}"
                        class="mt-3"
                        onsubmit="event.preventDefault(); window.confirmAction({title: 'Complete this innings?', confirmButtonText: 'Yes, complete'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                    >
                        @csrf
                        <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500">
                            Complete Innings
                        </button>
                    </form>
                @elseif(! $match->secondInnings)
                    <p class="mt-3 text-xs text-neutral-500">
                        Next batting: <span class="font-medium text-neutral-800">{{ $match->firstInnings->bowlingTeam->team->name }}</span>
                    </p>
                    <form method="POST" action="{{ route('admin.matches.innings.second.start', $match) }}" class="mt-2">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                            @disabled(! $canStartSecondInnings)
                        >
                            Start Second Innings
                        </button>
                    </form>
                @elseif($match->secondInnings->status === 'live')
                    <form
                        method="POST"
                        action="{{ route('admin.matches.innings.complete', [$match, $match->secondInnings]) }}"
                        class="mt-3"
                        onsubmit="event.preventDefault(); window.confirmAction({title: 'Complete this innings?', confirmButtonText: 'Yes, complete'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                    >
                        @csrf
                        <button type="submit" class="rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500">
                            Complete Innings
                        </button>
                    </form>
                @elseif($match->match_status !== 'completed')
                    {{-- Both innings are completed but the match itself
                         isn't yet — this is the one gap Phase 3.11/3.12
                         deliberately leave open until "Finalize Match"
                         is used. Once match_status is 'completed', the
                         Toss & Result card above already shows the
                         stored final result, so nothing further renders
                         here. --}}
                    @can('finalizeResult', $match)
                        @if($resultPreview)
                            <div class="mt-3 rounded-md border border-neutral-100 bg-neutral-50 p-3">
                                <p class="text-xs text-neutral-500">Expected result:</p>
                                <p class="text-sm font-medium text-neutral-800">{{ $resultPreview['match_result'] }}</p>
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
                                    class="rounded-md bg-green-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-green-500 disabled:cursor-not-allowed disabled:bg-neutral-300"
                                    @disabled(! $canFinalize)
                                >
                                    Finalize Match
                                </button>
                            </form>
                        @else
                            <p class="mt-3 text-xs text-neutral-500">Match result pending.</p>
                        @endif
                    @else
                        <p class="mt-3 text-xs text-neutral-500">Match result pending.</p>
                    @endcan
                @endif
            @endif
        </div>
    @endcan

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Playing XI</h3>
                <p class="mt-1 text-xs text-neutral-500">
                    {{ $match->teamA->team->name }}: {{ $teamASelectedCount }} selected
                    &middot;
                    {{ $match->teamB->team->name }}: {{ $teamBSelectedCount }} selected
                </p>
            </div>
            <a
                href="{{ route('admin.matches.players.index', $match) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="users" class="h-3.5 w-3.5" />
                Manage Playing XI
            </a>
        </div>
    </div>
@endsection
