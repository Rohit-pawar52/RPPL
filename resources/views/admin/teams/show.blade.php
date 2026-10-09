@extends('layouts.admin')

@section('title', __('Team Details'))

@section('content')
    <x-crud.back :href="route('admin.teams.index')">{{ __('Teams') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$team->name"
            :path="$team->logo_path"
            kind="image"
            shape="square"
            :status="$team->is_active ? 'active' : 'inactive'"
            :subtitle="$team->short_name ?: __('No short name')"
        >
            <span class="inline-flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5" /> {{ $team->edition_teams_count }} {{ (int) $team->edition_teams_count === 1 ? __('edition') : __('editions') }}</span>

            <x-slot:actions>
                <x-admin.button :href="route('admin.teams.edit', $team)" icon="pencil">{{ __('Edit') }}</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <x-admin.card :title="__('Edition History')">
            @forelse($team->editionTeams as $editionTeam)
                <div class="crud-list-row">
                    <span class="min-w-0 truncate font-medium text-slate-800">{{ $editionTeam->edition->name }}</span>
                    <span class="tabular-nums text-slate-500">{{ $editionTeam->edition->year }}</span>
                </div>
            @empty
                <x-admin.empty icon="calendar" class="py-6!">
                    {{ __('No edition participations yet.') }}
                    <x-slot:action>
                        <x-admin.button :href="route('admin.edition-teams.create')" variant="secondary" size="sm">{{ __('Add to an edition') }}</x-admin.button>
                    </x-slot:action>
                </x-admin.empty>
            @endforelse
        </x-admin.card>
    </div>
@endsection
