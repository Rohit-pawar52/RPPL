@extends('layouts.public')

@section('title', __('directory.venues.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.venues.title')">
        <form method="GET" action="{{ route('public.venues.index') }}" class="flex items-center gap-2" role="search">
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="{{ __('directory.venues.search_placeholder') }}"
                aria-label="{{ __('directory.venues.search_placeholder') }}"
                class="h-10 w-44 rounded-lg border border-slate-300 bg-white px-3 text-[13px] focus:border-green-600 focus:outline-none focus:ring-2 focus:ring-green-600/20 sm:w-56"
            />
            <button type="submit" class="pub-btn h-10">{{ __('directory.common.search') }}</button>
        </form>
    </x-public.page-header>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($venues as $venue)
            <a href="{{ route('public.venues.show', $venue) }}" class="pub-card pub-card-link flex items-center gap-3 p-3.5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-700">
                    <x-icon name="map-pin" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">{{ $venue->name }}</p>
                    <p class="pub-meta truncate">
                        {{ $venue->locationLabel() ?: __('directory.venues.location_unavailable') }}
                    </p>
                </div>
            </a>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.venues.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $venues->links() }}
    </div>
@endsection
