@extends('layouts.public')

@section('title', 'Live · '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('public.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
        <a
            href="{{ route('public.matches.scorecard', $match) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="document-chart" class="h-3.5 w-3.5" />
            Full Scorecard
        </a>
    </div>

    <div
        id="live-match-root"
        data-match-id="{{ $match->id }}"
        data-live-data-url="{{ route('public.matches.live-data', $match) }}"
        data-should-poll="{{ $liveData['should_poll'] ? '1' : '0' }}"
    >
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <h1 class="text-base font-semibold text-neutral-900">
                    {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                </h1>
                <span id="live-status-badge"><x-status-badge :status="$match->match_status" /></span>
            </div>
            <p class="mt-1 text-xs text-neutral-500">
                {{ $match->edition->name }}
                @if($match->venue)
                    &middot; {{ $match->venue->name }}
                @endif
                &middot; {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
            </p>

            @if($match->tossWinner)
                <p class="mt-1 text-xs text-neutral-500">
                    {{ $match->tossWinner->team->name }} won the toss and chose to {{ $match->toss_decision }}
                </p>
            @endif

            @if($liveData['match_result'])
                <p id="live-match-result" class="mt-2 text-sm font-medium text-neutral-900">{{ $liveData['match_result'] }}</p>
            @else
                <p id="live-match-result" class="mt-2 text-sm font-medium text-neutral-900"></p>
            @endif
        </div>

        <div id="live-innings" class="mt-4 space-y-2" aria-live="polite">
            @foreach($liveData['innings'] as $innings)
                @include('public.matches._live-innings-row', ['innings' => $innings])
            @endforeach
        </div>

        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Ball-by-Ball</h3>
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
