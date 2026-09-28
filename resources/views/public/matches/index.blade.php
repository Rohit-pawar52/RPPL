@extends('layouts.public')

@section('title', 'Matches · '.$branding->shortName)

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-base font-semibold text-neutral-900">Matches</h1>

        <form method="GET" action="{{ route('public.matches.index') }}" class="flex items-center gap-2">
            <select
                name="edition_id"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            >
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected($selectedEditionId === $edition->id)>{{ $edition->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>
        </form>
    </div>

    @php
        // Presentation-only split of the controller's single "upcoming"
        // collection (scheduled/toss/live) so live matches get their own
        // always-on-top card — no extra query, same eager-loaded models.
        $liveMatches = $upcoming->where('match_status', 'live');
        $scheduledMatches = $upcoming->where('match_status', '!=', 'live');
    @endphp

    @if($liveMatches->isNotEmpty())
        <div class="mb-4 rounded-lg border border-red-200 bg-white p-3 sm:p-4">
            <h3 class="mb-1 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-red-600">
                <span class="relative flex h-2 w-2" aria-hidden="true">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                </span>
                Live Now
            </h3>
            @foreach($liveMatches as $match)
                @include('public.matches._list-row', ['match' => $match])
            @endforeach
        </div>
    @endif

    <div class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
        <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Upcoming</h3>
        @forelse($scheduledMatches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">
                {{ $liveMatches->isNotEmpty() ? 'No other matches scheduled yet.' : 'No matches scheduled yet.' }}
            </p>
        @endforelse
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
        <h3 class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Results</h3>
        @forelse($past as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
        @endforelse

        @if($past->hasPages())
            <div class="mt-3">
                {{ $past->links() }}
            </div>
        @endif
    </div>
@endsection
