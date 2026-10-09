@extends('layouts.admin')

@section('title', 'Teams')

@section('subtitle', number_format($teams->total()).' '.\Illuminate\Support\Str::plural('team', $teams->total()).(array_filter($filters) ? ' match your filters.' : ' in the master list, separate from any edition.'))

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.teams.create')" variant="primary">+ New team</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.teams.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" placeholder="Search by name&hellip;" />
            <x-crud.select name="status" all="All statuses" :value="$filters['status'] ?? ''" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">Logo</th>
                        <th>Team</th>
                        <th>Status</th>
                        <th class="hidden md:table-cell">Editions</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$team->logo_path" kind="image" size="sm" />
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.teams.show', $team) }}" class="crud-row-link">{{ $team->name }}</a>
                                @if($team->short_name)
                                    <span class="ml-1 text-[11px] font-normal text-slate-400">({{ $team->short_name }})</span>
                                @endif
                                <span class="crud-meta md:hidden">{{ $team->edition_teams_count }} {{ \Illuminate\Support\Str::plural('edition', $team->edition_teams_count) }}</span>
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$team->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="hidden tabular-nums md:table-cell">{{ $team->edition_teams_count }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.teams.show', $team)"
                                    :edit="route('admin.teams.edit', $team)"
                                    :delete="route('admin.teams.destroy', $team)"
                                    :name="$team->name"
                                    confirm-text="This cannot be undone. Teams with existing tournament history cannot be deleted."
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="5" icon="shield">
                            {{ array_filter($filters) ? 'No teams match these filters.' : 'No teams yet.' }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.teams.index')" variant="secondary" size="sm">Clear filters</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.teams.create')" size="sm">+ New team</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $teams->links() }}
    </div>

    <x-crud.fab :href="route('admin.teams.create')" label="New team" />
@endsection
