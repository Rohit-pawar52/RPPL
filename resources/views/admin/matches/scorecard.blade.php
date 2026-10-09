@extends('layouts.admin')

@section('title', __('Scorecard'))

@section('content')
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.matches.show', $match) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            {{ __('Back to match') }}
        </a>
        @if($match->innings()->exists())
            <a href="{{ route('public.matches.scorecard.pdf', $match) }}" class="btn btn-secondary btn-sm">
                <x-ops.icon name="download" class="h-3.5 w-3.5" />
                {{ __('Download PDF') }}
            </a>
        @endif
    </div>

    <header class="rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-4 text-white shadow-raised sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <p class="sc-kicker">
                {{ $match->edition->name }}
                @if($match->venue)
                    &middot; {{ $match->venue->name }}
                @endif
            </p>
            <x-status-badge :status="$match->match_status" />
        </div>
        <h2 class="mt-2 break-words text-2xl font-bold tracking-tight">
            {{ $match->teamA->team->name }} <span class="font-normal text-white/50">{{ __('vs') }}</span> {{ $match->teamB->team->name }}
        </h2>

        @if($match->match_status === 'completed' && $match->match_result)
            <p class="mt-2 text-base font-semibold text-accent-dark">{{ $match->match_result }}</p>
        @endif
    </header>

    @forelse($inningsScorecards as $card)
        @include('shared.scorecard._innings', ['card' => $card])
    @empty
        <x-admin.empty icon="document-chart" class="ops-card mt-4 py-12">
            {{ __('No innings started yet.') }}
        </x-admin.empty>
    @endforelse
@endsection
