@extends('layouts.public')

@section('title', $venue->name.' · '.$branding->shortName)

@section('content')
    <a href="{{ route('public.venues.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
        <span aria-hidden="true">&larr;</span> {{ __('directory.venues.back') }}
    </a>

    <div class="pub-card flex items-center gap-4 p-4 sm:p-5">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-700">
            <x-icon name="map-pin" class="h-6 w-6" />
        </div>
        <div class="min-w-0">
            <h1 class="pub-h1 break-words">{{ $venue->name }}</h1>
            <p class="pub-meta mt-0.5">
                {{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: __('directory.venues.location_unavailable') }}
                &middot; {{ trans_choice('directory.venues.match_count', $venue->matches_count) }}
            </p>
        </div>
    </div>

    <x-public.card class="mt-4" :title="__('directory.venues.upcoming_live')">
        @forelse($upcomingMatches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <x-public.empty class="!py-4">{{ __('directory.venues.upcoming_empty') }}</x-public.empty>
        @endforelse
    </x-public.card>

    <x-public.card class="mt-4" :title="__('directory.venues.recent_completed')">
        @forelse($completedMatches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <x-public.empty class="!py-4">{{ __('directory.venues.completed_empty') }}</x-public.empty>
        @endforelse
    </x-public.card>
@endsection
