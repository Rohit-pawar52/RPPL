@extends('layouts.public')

@section('title', $match->teamA->team->name.' vs '.$match->teamB->team->name.' · '.$branding->shortName)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All matches
        </a>

        @if($match->innings_count > 0)
            <div class="flex items-center gap-2">
                <a
                    href="{{ route('public.matches.live', $match) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="chart-bar" class="h-3.5 w-3.5" />
                    {{ $match->match_status === 'live' ? 'Live Match' : 'Ball-by-Ball' }}
                </a>
                <a
                    href="{{ route('public.matches.scorecard', $match) }}"
                    class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    Scorecard
                </a>
            </div>
        @endif
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h1>
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

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Venue</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->venue->name ?? 'TBD' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Scheduled</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Overs</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->overs_per_innings }}</dd>
            </div>
        </dl>
    </div>

    @if($match->tossWinner)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Toss</h3>
            <p class="text-[13px] text-neutral-800">
                {{ $match->tossWinner->team->name }} won the toss and chose to {{ $match->toss_decision }}
            </p>
        </div>
    @endif

    @if($match->firstInnings || $match->secondInnings)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Innings</h3>

            @if($match->firstInnings)
                <p class="text-[13px] text-neutral-800">
                    {{ $match->firstInnings->battingTeam->team->name }}
                    {{ $match->firstInnings->total_runs }}/{{ $match->firstInnings->total_wickets }}
                    <span class="text-xs text-neutral-500">({{ $match->firstInnings->oversDisplay() }} overs)</span>
                </p>
            @endif
            @if($match->secondInnings)
                <p class="mt-1 text-[13px] text-neutral-800">
                    {{ $match->secondInnings->battingTeam->team->name }}
                    {{ $match->secondInnings->total_runs }}/{{ $match->secondInnings->total_wickets }}
                    <span class="text-xs text-neutral-500">({{ $match->secondInnings->oversDisplay() }} overs)</span>
                </p>
            @endif
        </div>
    @endif

    @if($match->match_status === 'completed' && $match->match_result)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <p class="text-sm font-medium text-neutral-900">{{ $match->match_result }}</p>
        </div>
    @endif
@endsection
