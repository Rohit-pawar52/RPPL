@extends('layouts.admin')

@section('title', __('Teams'))

@php
    $total = $teams->total();
    $count = number_format($total);
    $subtitle = array_filter($filters)
        ? ($total === 1 ? __(':count team match your filters.', ['count' => $count]) : __(':count teams match your filters.', ['count' => $count]))
        : ($total === 1 ? __(':count team in the master list, separate from any edition.', ['count' => $count]) : __(':count teams in the master list, separate from any edition.', ['count' => $count]));
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.teams.create')" variant="primary">{{ __('+ New team') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.teams.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search by name&hellip;')" />
            <x-crud.select name="status" :all="__('All statuses')" :value="$filters['status'] ?? ''" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">{{ __('Logo') }}</th>
                        <th>{{ __('Team') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden md:table-cell">{{ __('Editions') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teams as $team)
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$team->logo_path" kind="image" size="sm" />
                            </td>
                            <td class="c-title">
                                @if($team->color)
                                    <span class="mr-1 inline-block h-2.5 w-2.5 rounded-full align-middle ring-1 ring-black/10" style="background: {{ $team->color }}" title="{{ __('Team colour') }}"></span>
                                @endif
                                <a href="{{ route('admin.teams.show', $team) }}" class="crud-row-link">{{ $team->name }}</a>
                                @if($team->short_name)
                                    <span class="ml-1 text-[11px] font-normal text-slate-400">({{ $team->short_name }})</span>
                                @endif
                                <span class="crud-meta md:hidden">{{ $team->edition_teams_count }} {{ (int) $team->edition_teams_count === 1 ? __('edition') : __('editions') }}</span>
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
                                    :confirm-text="__('This cannot be undone. Teams with existing tournament history cannot be deleted.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="5" icon="shield">
                            {{ array_filter($filters) ? __('No teams match these filters.') : __('No teams yet.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.teams.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.teams.create')" size="sm">{{ __('+ New team') }}</x-admin.button>
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

    <x-crud.fab :href="route('admin.teams.create')" :label="__('New team')" />
@endsection
