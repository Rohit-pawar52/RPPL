@extends('layouts.public')

@section('title', 'Matches &middot; RPPL')

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

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Upcoming &amp; Live</h3>
        @forelse($upcoming as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">No matches scheduled yet.</p>
        @endforelse
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Completed</h3>
        @forelse($past as $match)
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                <div class="min-w-0">
                    <a href="{{ route('public.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                        {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                    </a>
                    <p class="text-[11px] text-neutral-500">
                        {{ display_datetime($match->scheduled_at, 'd M Y') }}
                        @if($match->venue)
                            &middot; {{ $match->venue->name }}
                        @endif
                    </p>
                    @if($match->match_result)
                        <p class="mt-0.5 text-[11px] text-neutral-600">{{ $match->match_result }}</p>
                    @endif
                </div>
                <x-status-badge :status="$match->match_status" />
            </div>
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
        @endforelse

        <div class="mt-3">
            {{ $past->links() }}
        </div>
    </div>
@endsection
