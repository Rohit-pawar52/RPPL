@extends('layouts.admin')

@section('title', 'Edition Team Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-teams.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to edition teams
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $editionTeam->team->name }} &middot; {{ $editionTeam->edition->name }}
            </h2>
            <x-status-badge :status="$editionTeam->team->is_active ? 'active' : 'inactive'" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            Added {{ $editionTeam->created_at->format('d M Y') }}
            @unless($editionTeam->team->is_active)
                &middot; <span class="text-neutral-400">team is currently inactive</span>
            @endunless
        </p>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Squad players" :value="$editionTeam->team_players_count" icon="users" />
        <x-stat-card label="Matches" :value="$editionTeam->matches_as_team_a_count + $editionTeam->matches_as_team_b_count" icon="trophy" />
    </div>
@endsection
