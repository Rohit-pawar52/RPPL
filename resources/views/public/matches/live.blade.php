@extends('layouts.public')

@section('title', 'Live · '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

@section('content')
    @php
        // 'chase' may be absent on an older payload shape — treat as null.
        $chase = $liveData['chase'] ?? null;
        $chasingTeam = collect($liveData['innings'])->firstWhere('innings_number', 2)['batting_team'] ?? null;
    @endphp

    <div class="mb-3">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All matches
        </a>
    </div>

    @include('public.matches._match-tabs', ['match' => $match, 'active' => 'live'])

    <div
        id="live-match-root"
        data-match-id="{{ $match->id }}"
        data-live-data-url="{{ route('public.matches.live-data', $match) }}"
        data-should-poll="{{ $liveData['should_poll'] ? '1' : '0' }}"
    >
        <div class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
            <p class="truncate text-[11px] text-neutral-500">
                {{ $match->edition->name }}
                @if($match->venue)
                    &middot; {{ $match->venue->name }}
                @endif
                &middot; {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
            </p>
            <div class="mt-0.5 flex items-center justify-between gap-3">
                <h1 class="min-w-0 text-base font-semibold text-neutral-900">
                    {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                </h1>
                <span id="live-status-badge" class="shrink-0"><x-status-badge :status="$liveData['match_status']" /></span>
            </div>

            @if($match->tossWinner)
                <p class="mt-1 text-[11px] text-neutral-500">
                    {{ $match->tossWinner->team->name }} won the toss and chose to {{ $match->toss_decision }}
                </p>
            @endif

            <p id="live-match-result" class="mt-2 text-[13px] font-semibold theme-primary-text {{ $liveData['match_result'] ? '' : 'hidden' }}">{{ $liveData['match_result'] }}</p>
        </div>

        <div id="live-innings" class="mt-3 grid gap-2 sm:grid-cols-2" aria-live="polite">
            @foreach($liveData['innings'] as $innings)
                @include('public.matches._live-innings-row', ['innings' => $innings])
            @endforeach
        </div>

        <div id="live-chase" class="mt-2 rounded-lg border theme-primary-border theme-primary-soft-bg p-3 {{ $chase ? '' : 'hidden' }}" aria-live="polite">
            @if($chase)
                @include('public.matches._live-chase', ['chase' => $chase, 'chasingTeam' => $chasingTeam])
            @endif
        </div>

        <div class="mt-3 rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
            <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Ball-by-Ball</h3>
            <div id="live-deliveries">
                @forelse($liveData['recent_deliveries'] as $delivery)
                    @include('public.matches._live-delivery-row', ['delivery' => $delivery])
                @empty
                    <p class="py-4 text-center text-xs text-neutral-400">No deliveries recorded yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    @vite(['resources/js/public-live-match.js'])
@endsection
