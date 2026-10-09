@extends('layouts.public')

@section('title', $venue->name.' · '.$branding->shortName)

@section('content')
    @php
        // The results query loads only the teams; one batched load adds the scores.
        $completedMatches->loadMissing(['firstInnings.battingTeam.team', 'secondInnings.battingTeam.team']);
        $liveHere = $upcomingMatches->firstWhere('match_status', 'live');
    @endphp

    <header class="mx-hero">
        <div class="mx-hero-glow" aria-hidden="true"></div>

        <div class="relative px-4 pb-5 pt-3 sm:px-6 sm:pb-6 sm:pt-4">
            <a href="{{ route('public.venues.index') }}" class="mx-hero-back">
                <span aria-hidden="true">&larr;</span> {{ __('directory.venues.back') }}
            </a>

            <div class="mt-3 flex items-start gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl bg-white/10 text-accent-dark ring-1 ring-inset ring-white/15 sm:size-16">
                    <x-icon name="map-pin" class="size-7" />
                </span>
                <div class="min-w-0">
                    <h1 class="mx-hero-title">{{ $venue->name }}</h1>
                    <p class="mt-1 text-sm text-slate-300">{{ $venue->locationLabel() ?: __('directory.venues.location_unavailable') }}</p>
                </div>
            </div>

            <dl class="mx-stats mt-5">
                <div class="mx-stat"><b>{{ $venue->matches_count }}</b><span>{{ __('directory.editions.matches') }}</span></div>
                <div class="mx-stat"><b>{{ $upcomingMatches->count() }}</b><span>{{ __('ux_public_matches.venue.matches_coming') }}</span></div>
                <div class="mx-stat"><b>{{ $completedMatches->count() }}</b><span>{{ __('ux_public_matches.venue.matches_played') }}</span></div>
            </dl>

            @if($liveHere || $venue->directionsUrl())
                <div class="mx-chips mt-5">
                    @if($liveHere)
                        <a href="{{ route('public.matches.live', $liveHere) }}" class="mx-chip-dark bg-green-600! text-white!">
                            <span class="live-dot" aria-hidden="true"></span> {{ __('matches.list.follow_live') }}
                        </a>
                    @endif
                    @if($venue->directionsUrl())
                        <a href="{{ $venue->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="mx-chip-dark">
                            <x-icon name="map-pin" class="size-4" /> {{ __('directory.venues.get_directions') }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </header>

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start lg:gap-5">
        <div class="min-w-0 space-y-4">
            <x-public.card flush :title="__('directory.venues.upcoming_live')" icon="calendar">
                @forelse($upcomingMatches as $match)
                    @include('public.matches._list-row', ['match' => $match])
                @empty
                    <x-public.empty icon="calendar">{{ __('directory.venues.upcoming_empty') }}</x-public.empty>
                @endforelse
            </x-public.card>

            <x-public.card flush :title="__('directory.venues.recent_completed')" icon="trophy">
                @forelse($completedMatches as $match)
                    @include('public.matches._list-row', ['match' => $match])
                @empty
                    <x-public.empty icon="trophy">{{ __('directory.venues.completed_empty') }}</x-public.empty>
                @endforelse
            </x-public.card>
        </div>

        @if($venue->hasCoordinates())
            <x-public.card :title="__('directory.venues.location')" icon="map-pin">
                @include('public.venues._map', ['venue' => $venue])
            </x-public.card>
        @endif
    </div>
@endsection
