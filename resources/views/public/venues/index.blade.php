@extends('layouts.public')

@section('title', __('directory.venues.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.venues.title')">
        <form method="GET" action="{{ route('public.venues.index') }}" class="flex w-full items-center gap-2 sm:w-auto" role="search">
            <div class="relative min-w-0 flex-1 sm:w-64 sm:flex-none">
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="{{ __('directory.venues.search_placeholder') }}"
                    aria-label="{{ __('directory.venues.search_placeholder') }}"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-[13px] focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
                />
            </div>
            <button type="submit" class="btn btn-primary h-10">{{ __('directory.common.search') }}</button>
            @if($search !== '')
                <a href="{{ route('public.venues.index') }}" class="btn btn-ghost h-10">{{ __('ux_public_matches.venue.search_clear') }}</a>
            @endif
        </form>
    </x-public.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($venues as $venue)
            <a href="{{ route('public.venues.show', $venue) }}" class="mx-place">
                <span class="mx-place-icon">
                    <x-icon name="map-pin" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $venue->name }}</span>
                    <span class="mt-0.5 block truncate text-xs text-slate-500">
                        {{ $venue->locationLabel() ?: __('directory.venues.location_unavailable') }}
                    </span>
                </span>
                <span class="text-slate-300" aria-hidden="true">&rarr;</span>
            </a>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty icon="map-pin">{{ $search !== '' ? __('ux_public_matches.venue.no_results') : __('directory.venues.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $venues->links() }}
    </div>
@endsection
