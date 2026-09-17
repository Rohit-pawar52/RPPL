@extends('layouts.admin')

@section('title', 'Team Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.teams.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to teams
        </a>
        <a
            href="{{ route('admin.teams.edit', $team) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($team->logo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($team->logo_path) }}"
                        alt="{{ $team->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="shield" class="h-6 w-6" />
                @endif
            </div>

            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold text-neutral-900">{{ $team->name }}</h2>
                    <x-status-badge :status="$team->is_active ? 'active' : 'inactive'" />
                </div>
                <p class="text-xs text-neutral-500">
                    {{ $team->short_name ?: 'No short name' }}
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Edition participations" :value="$team->edition_teams_count" icon="calendar" />
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Edition History</h3>

        @forelse($team->editionTeams as $editionTeam)
            <div class="flex items-center justify-between border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                <span class="font-medium text-neutral-800">{{ $editionTeam->edition->name }}</span>
                <span class="text-neutral-500">{{ $editionTeam->edition->year }}</span>
            </div>
        @empty
            <p class="text-xs text-neutral-400">No edition participations yet.</p>
        @endforelse
    </div>
@endsection
