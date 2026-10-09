@extends('layouts.admin')

@section('title', __('Venue Details'))

@section('content')
    <x-crud.back :href="route('admin.venues.index')">{{ __('Venues') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$venue->name"
            icon="map-pin"
            :status="$venue->is_active ? 'active' : 'inactive'"
            :subtitle="$venue->locationLabel() ?: __('No location set')"
        >
            @if($venue->is_default)
                <span class="crud-pill crud-pill-brand">{{ __('Default venue') }}</span>
            @endif
            <span class="inline-flex items-center gap-1.5"><x-icon name="trophy" class="h-3.5 w-3.5" /> {{ (int) $venue->matches_count === 1 ? __(':count match', ['count' => $venue->matches_count]) : __(':count matches', ['count' => $venue->matches_count]) }}</span>

            <x-slot:actions>
                @if($venue->hasCoordinates())
                    <x-admin.button :href="$venue->directionsUrl()" target="_blank" rel="noopener noreferrer" variant="secondary" icon="external">{{ __('Open in Google Maps') }}</x-admin.button>
                @endif
                <x-admin.button :href="route('admin.venues.edit', $venue)" icon="pencil">{{ __('Edit') }}</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        @if($venue->hasCoordinates())
            <x-admin.card :title="__('Coordinates')">
                <p class="text-[13px] tabular-nums text-slate-700">{{ $venue->latitude }}, {{ $venue->longitude }}</p>
            </x-admin.card>
        @endif

        <x-admin.card :title="__('Recent Matches')">
            @forelse($venue->matches as $match)
                <div class="crud-list-row">
                    <span class="min-w-0 truncate font-medium text-slate-800">
                        {{ $match->edition->name }} &middot; {{ __('Match :number', ['number' => $match->match_number ?? '—']) }}
                    </span>
                    <span class="flex shrink-0 items-center gap-2 text-slate-500">
                        <span class="max-sm:hidden">{{ display_datetime($match->scheduled_at, 'd M Y') ?? '—' }}</span>
                        <x-status-badge :status="$match->match_status" />
                    </span>
                </div>
            @empty
                <x-admin.empty icon="trophy" class="py-6!">{{ __('No matches scheduled at this venue yet.') }}</x-admin.empty>
            @endforelse
        </x-admin.card>
    </div>
@endsection
