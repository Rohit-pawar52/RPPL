@extends('layouts.public')

@section('title', $venue->name.' · '.$branding->shortName)

@section('content')
    <a href="{{ route('public.venues.index') }}" class="mb-4 inline-block text-xs text-neutral-500 hover:text-neutral-700">
        &larr; {{ __('directory.venues.back') }}
    </a>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                <x-icon name="map-pin" class="h-5 w-5" />
            </div>
            <div>
                <h1 class="text-base font-semibold text-neutral-900">{{ $venue->name }}</h1>
                <p class="text-xs text-neutral-500">
                    {{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: __('directory.venues.location_unavailable') }}
                    &middot; {{ trans_choice('directory.venues.match_count', $venue->matches_count) }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('directory.venues.upcoming_live') }}</h3>
        @forelse($upcomingMatches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">{{ __('directory.venues.upcoming_empty') }}</p>
        @endforelse
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('directory.venues.recent_completed') }}</h3>
        @forelse($completedMatches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">{{ __('directory.venues.completed_empty') }}</p>
        @endforelse
    </div>
@endsection
