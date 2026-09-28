@extends('layouts.public')

@section('title', 'Scorecard · '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

@section('content')
    <div class="mb-3 flex items-center justify-between gap-3">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All matches
        </a>
        <a href="{{ route('public.matches.scorecard.pdf', $match) }}" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-2.5 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">
            <x-icon name="document-chart" class="h-3.5 w-3.5" />
            Download PDF
        </a>
    </div>

    @include('public.matches._match-tabs', ['match' => $match, 'active' => 'scorecard'])

    <div class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
        <p class="truncate text-[11px] text-neutral-500">{{ $match->edition->name }}</p>
        <div class="mt-0.5 flex items-center justify-between gap-3">
            <h1 class="min-w-0 text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h1>
            <x-status-badge :status="$match->match_status" />
        </div>

        @if($match->match_status === 'completed' && $match->match_result)
            <p class="mt-2 text-[13px] font-semibold theme-primary-text">{{ $match->match_result }}</p>
        @endif
    </div>

    {{-- shared.scorecard._innings is also used by the admin scorecard, so
         it is deliberately left untouched here (Admin is out of scope). --}}
    @forelse($inningsScorecards as $card)
        @include('shared.scorecard._innings', ['card' => $card])
    @empty
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center text-xs text-neutral-400">
            Scorecard will be available once the match begins.
        </div>
    @endforelse
@endsection
