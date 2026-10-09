@extends('layouts.admin')

@section('title', __('Squads'))

@php
    $total = $teamPlayers->total();
    $count = number_format($total);
    $subtitle = $total === 1
        ? __(':count squad place: which players belong to which team in each edition.', ['count' => $count])
        : __(':count squad places: which players belong to which team in each edition.', ['count' => $count]);
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.team-players.create')" variant="primary">{{ __('+ Add to squad') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.team-players.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search player name&hellip;')" />
            <x-crud.select
                name="edition_team_id"
                :all="__('All teams')"
                :value="$filters['edition_team_id'] ?? ''"
                :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->edition->name.' — '.$editionTeam->team->name])->all()"
            />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">{{ __('Photo') }}</th>
                        <th>{{ __('Player') }}</th>
                        <th>{{ __('Team / Edition') }}</th>
                        <th class="hidden md:table-cell">{{ __('Jersey') }}</th>
                        <th class="hidden md:table-cell">{{ __('Role') }}</th>
                        <th class="hidden lg:table-cell">{{ __('Matches') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teamPlayers as $teamPlayer)
                        @php $player = $teamPlayer->playerRegistration->player; @endphp
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$player->photo_path" kind="user" size="sm" />
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.team-players.show', $teamPlayer) }}" class="crud-row-link">{{ $player->name }}</a>
                                <span class="crud-meta md:hidden">
                                    {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
                                    @if($teamPlayer->jersey_number) &middot; #{{ $teamPlayer->jersey_number }} @endif
                                </span>
                            </td>
                            <td class="c-sub max-md:hidden">
                                {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
                            </td>
                            <td class="hidden tabular-nums md:table-cell">{{ $teamPlayer->jersey_number ?? '—' }}</td>
                            <td class="hidden capitalize md:table-cell">{{ $teamPlayer->role ? __(str_replace('_', ' ', $teamPlayer->role)) : '—' }}</td>
                            <td class="hidden tabular-nums lg:table-cell">{{ $teamPlayer->match_players_count }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.team-players.show', $teamPlayer)"
                                    :edit="route('admin.team-players.edit', $teamPlayer)"
                                    :delete="route('admin.team-players.destroy', $teamPlayer)"
                                    :name="$player->name"
                                    :delete-label="__('Remove')"
                                    :confirm-title="__('Remove this player from the squad?')"
                                    :confirm-text="__('This cannot be undone. Players with existing match history cannot be removed.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="users">
                            {{ array_filter($filters) ? __('No squad places match these filters.') : __('No squad assignments yet.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.team-players.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.team-players.create')" size="sm">{{ __('+ Add to squad') }}</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $teamPlayers->links() }}
    </div>

    <x-crud.fab :href="route('admin.team-players.create')" :label="__('Add to squad')" />
@endsection
