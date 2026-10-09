@extends('layouts.admin')

@section('title', 'Edition Team Details')

@section('content')
    <x-crud.back :href="route('admin.edition-teams.index')">Edition teams</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$editionTeam->team->name"
            :path="$editionTeam->team->logo_path"
            kind="image"
            shape="square"
            :status="$editionTeam->team->is_active ? 'active' : 'inactive'"
            :subtitle="$editionTeam->edition->name.' · added '.display_datetime($editionTeam->created_at, 'd M Y')"
        >
            @unless($editionTeam->team->is_active)
                <span class="text-slate-400">team is currently inactive</span>
            @endunless

            <x-slot:actions>
                <x-admin.button :href="route('admin.team-players.index', ['edition_team_id' => $editionTeam->id])" variant="secondary" icon="users">See squad</x-admin.button>
                <x-admin.button :href="route('admin.teams.show', $editionTeam->team)" variant="secondary" icon="shield">Team page</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <div class="grid grid-cols-2 gap-3 sm:max-w-md">
            <x-crud.kpi label="Squad players" :value="$editionTeam->team_players_count" icon="users" tone="brand" />
            <x-crud.kpi label="Matches" :value="$editionTeam->matches_as_team_a_count + $editionTeam->matches_as_team_b_count" icon="trophy" />
        </div>
    </div>
@endsection
