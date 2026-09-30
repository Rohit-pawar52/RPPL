@extends('layouts.public')

@section('title', __('matches.list.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('matches.list.title')" :subtitle="__('matches.list.subtitle')">
        <form method="GET" action="{{ route('public.matches.index') }}" class="flex items-center gap-2">
            <label for="edition_id" class="sr-only">{{ __('matches.list.all_editions') }}</label>
            <select
                name="edition_id"
                id="edition_id"
                class="min-h-10 rounded-lg border border-slate-300 bg-white px-2.5 text-[13px] text-slate-700 focus:outline-2 focus:outline-green-600"
            >
                <option value="">{{ __('matches.list.all_editions') }}</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected($selectedEditionId === $edition->id)>{{ $edition->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="pub-btn-outline min-h-10">{{ __('matches.list.filter') }}</button>
        </form>
    </x-public.page-header>

    @php
        // Presentation-only split of the controller's single "upcoming"
        // collection (scheduled/toss/live) so live matches get their own
        // always-on-top section — no extra query, same eager-loaded models.
        $liveMatches = $upcoming->where('match_status', 'live');
        $scheduledMatches = $upcoming->where('match_status', '!=', 'live');
    @endphp

    @if($liveMatches->isNotEmpty())
        <section class="mb-8" aria-labelledby="matches-live">
            <h2 id="matches-live" class="pub-h2 mb-3 flex items-center gap-2 text-green-700">
                <span class="live-dot" aria-hidden="true"></span>
                {{ __('matches.list.live_now') }}
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($liveMatches as $match)
                    @include('public.matches._card', ['match' => $match])
                @endforeach
            </div>
        </section>
    @endif

    <section class="mb-8" aria-labelledby="matches-upcoming">
        <h2 id="matches-upcoming" class="pub-h2 mb-3">{{ __('matches.list.upcoming') }}</h2>
        @if($scheduledMatches->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($scheduledMatches as $match)
                    @include('public.matches._card', ['match' => $match])
                @endforeach
            </div>
        @else
            <div class="pub-card">
                <x-public.empty>{{ $liveMatches->isNotEmpty() ? __('matches.list.no_other_scheduled') : __('matches.list.no_scheduled') }}</x-public.empty>
            </div>
        @endif
    </section>

    <section aria-labelledby="matches-results">
        <h2 id="matches-results" class="pub-h2 mb-3">{{ __('matches.list.results') }}</h2>
        @if($past->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($past as $match)
                    @include('public.matches._card', ['match' => $match])
                @endforeach
            </div>
        @else
            <div class="pub-card">
                <x-public.empty>{{ __('matches.list.no_completed') }}</x-public.empty>
            </div>
        @endif

        @if($past->hasPages())
            <div class="mt-4">
                {{ $past->links() }}
            </div>
        @endif
    </section>
@endsection
