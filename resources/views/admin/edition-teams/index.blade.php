@extends('layouts.admin')

@section('title', 'Edition Teams')

@section('subtitle', number_format($editionTeams->total()).' team '.\Illuminate\Support\Str::plural('entry', $editionTeams->total()).' taking part in the editions.')

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.edition-teams.create')" variant="primary">+ Add team to edition</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.edition-teams.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" placeholder="Search team name&hellip;" />
            <x-crud.select name="edition_id" all="All editions" :value="$filters['edition_id'] ?? ''" :options="$editions->pluck('name', 'id')->all()" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">Logo</th>
                        <th>Team</th>
                        <th>Edition</th>
                        <th class="hidden md:table-cell">Team Status</th>
                        <th class="hidden md:table-cell">Squad</th>
                        <th class="hidden lg:table-cell">Added</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($editionTeams as $editionTeam)
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$editionTeam->team->logo_path" kind="image" size="sm" />
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.edition-teams.show', $editionTeam) }}" class="crud-row-link">{{ $editionTeam->team->name }}</a>
                                <span class="crud-meta md:hidden">{{ $editionTeam->edition->name }} &middot; {{ $editionTeam->team_players_count }} in squad</span>
                            </td>
                            <td class="c-sub max-md:hidden">
                                <span class="crud-pill crud-pill-brand">{{ $editionTeam->edition->name }}</span>
                            </td>
                            <td class="hidden md:table-cell">
                                <x-status-badge :status="$editionTeam->team->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="hidden tabular-nums md:table-cell">{{ $editionTeam->team_players_count }}</td>
                            <td class="hidden whitespace-nowrap text-slate-500 lg:table-cell">{{ display_datetime($editionTeam->created_at, 'd M Y') }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.edition-teams.show', $editionTeam)"
                                    :view-label="'View'"
                                    :delete="route('admin.edition-teams.destroy', $editionTeam)"
                                    delete-label="Remove"
                                    :name="$editionTeam->team->name.' in '.$editionTeam->edition->name"
                                    :confirm-title="'Remove '.$editionTeam->team->name.' from '.$editionTeam->edition->name.'?'"
                                    confirm-text="This cannot be undone. Teams with existing squad or match data cannot be removed."
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="shield">
                            {{ array_filter($filters) ? 'No teams match these filters.' : 'No teams have been added to any edition yet.' }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.edition-teams.index')" variant="secondary" size="sm">Clear filters</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.edition-teams.create')" size="sm">+ Add team to edition</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $editionTeams->links() }}
    </div>

    <x-crud.fab :href="route('admin.edition-teams.create')" label="Add team" />
@endsection
