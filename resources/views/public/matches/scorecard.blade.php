@extends('layouts.public')

@section('title', 'Scorecard &middot; '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3">
        <a href="{{ route('public.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
        <a href="{{ route('public.matches.scorecard.pdf', $match) }}" class="rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
            Download PDF
        </a>
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
        </p>

        @if($match->match_status === 'completed' && $match->match_result)
            <p class="mt-2 text-sm font-medium text-neutral-800">{{ $match->match_result }}</p>
        @endif
    </div>

    @forelse($inningsScorecards as $card)
        @include('shared.scorecard._innings', ['card' => $card])
    @empty
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center text-xs text-neutral-400">
            Scorecard will be available once the match begins.
        </div>
    @endforelse
@endsection
