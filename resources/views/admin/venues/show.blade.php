@extends('layouts.admin')

@section('title', 'Venue Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.venues.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to venues
        </a>
        <a
            href="{{ route('admin.venues.edit', $venue) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-900">{{ $venue->name }}</h2>
            <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" />
        </div>
        <p class="mt-1 text-xs text-slate-500">
            {{ $venue->locationLabel() ?: 'No location set' }}
        </p>
        @if($venue->hasCoordinates())
            <p class="mt-1 text-xs text-slate-500">
                {{ $venue->latitude }}, {{ $venue->longitude }}
                &middot;
                <a href="{{ $venue->directionsUrl() }}" target="_blank" rel="noopener noreferrer" class="font-medium text-green-700 hover:underline">Open in Google Maps</a>
            </p>
        @endif
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Matches" :value="$venue->matches_count" icon="trophy" />
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Recent Matches</h3>

        @forelse($venue->matches as $match)
            <div class="flex items-center justify-between border-b border-slate-100 py-2 text-[13px] last:border-b-0">
                <span class="font-medium text-slate-800">
                    {{ $match->edition->name }} &middot; Match {{ $match->match_number ?? '—' }}
                </span>
                <span class="flex items-center gap-2 text-slate-500">
                    {{ display_datetime($match->scheduled_at, 'd M Y') ?? '—' }}
                    <x-status-badge :status="$match->match_status" />
                </span>
            </div>
        @empty
            <p class="text-xs text-slate-400">No matches scheduled at this venue yet.</p>
        @endforelse
    </div>
@endsection
