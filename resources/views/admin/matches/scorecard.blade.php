@extends('layouts.admin')

@section('title', 'Scorecard')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('admin.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
        @if($match->innings()->exists())
            <a href="{{ route('public.matches.scorecard.pdf', $match) }}" class="rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
                Download PDF
            </a>
        @endif
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
            @if($match->venue)
                &middot; {{ $match->venue->name }}
            @endif
        </p>

        @if($match->match_status === 'completed' && $match->match_result)
            <p class="mt-2 text-sm font-medium text-neutral-800">{{ $match->match_result }}</p>
        @endif
    </div>

    @forelse($inningsScorecards as $card)
        @include('shared.scorecard._innings', ['card' => $card])
    @empty
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center text-xs text-neutral-400">
            No innings started yet.
        </div>
    @endforelse
@endsection
