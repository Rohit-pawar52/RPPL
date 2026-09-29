@extends('layouts.public')

@section('title', $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name.' · '.$branding->shortName)

@section('content')
    <div class="mb-3">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; {{ __('public.common.all_matches') }}
        </a>
    </div>

    @include('public.matches._match-tabs', ['match' => $match, 'active' => 'info'])

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} {{ __('matches.common.vs') }} {{ $match->teamB->team->name }}
            </h1>
            <x-status-badge :status="$match->match_status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $match->edition->name }}
            @if($match->match_number)
                &middot; {{ __('matches.info.match_number', ['number' => $match->match_number]) }}
            @endif
            @if($match->match_stage)
                &middot; {{ ucwords(str_replace('_', ' ', $match->match_stage)) }}
            @endif
        </p>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-[13px] sm:grid-cols-3">
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.info.venue') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->venue->name ?? __('matches.info.tbd') }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.info.scheduled') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.info.overs') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $match->overs_per_innings }}</dd>
            </div>
        </dl>
    </div>

    @if($match->tossWinner)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.info.toss') }}</h3>
            <p class="text-[13px] text-neutral-800">
                {{ __('matches.info.toss_result', ['team' => $match->tossWinner->team->name, 'decision' => __('matches.info.toss_decision.'.$match->toss_decision)]) }}
            </p>
        </div>
    @endif

    @if($match->firstInnings || $match->secondInnings)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.info.innings') }}</h3>

            <div class="divide-y divide-neutral-100">
                @foreach([$match->firstInnings, $match->secondInnings] as $inn)
                    @if($inn)
                        <div class="flex items-baseline justify-between gap-3 py-1.5 text-[13px]">
                            <span class="min-w-0 truncate font-medium text-neutral-800">{{ $inn->battingTeam->team->name }}</span>
                            <span class="shrink-0 whitespace-nowrap">
                                <span class="font-semibold tabular-nums text-neutral-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                                <span class="text-[11px] text-neutral-500">({{ __('matches.common.overs_count', ['overs' => $inn->oversDisplay()]) }})</span>
                            </span>
                        </div>
                    @endif
                @endforeach
            </div>

            @if($match->match_status === 'completed' && $match->match_result)
                <p class="mt-2 border-t border-neutral-100 pt-2 text-[13px] font-semibold theme-primary-text">{{ $match->match_result }}</p>
            @endif
        </div>
    @elseif($match->match_status === 'completed' && $match->match_result)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <p class="text-[13px] font-semibold theme-primary-text">{{ $match->match_result }}</p>
        </div>
    @endif
@endsection
