@extends('layouts.admin')

@section('title', __('Players'))

@php
    $total = $players->total();
    $count = number_format($total);
    $subtitle = array_filter($filters)
        ? ($total === 1 ? __(':count player match your filters.', ['count' => $count]) : __(':count players match your filters.', ['count' => $count]))
        : ($total === 1 ? __(':count player in the master directory, separate from any edition.', ['count' => $count]) : __(':count players in the master directory, separate from any edition.', ['count' => $count]));
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <x-admin.button :href="route('admin.players.export', $filters)" variant="secondary" icon="document-chart">{{ __('Export') }}</x-admin.button>
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.players.create')" variant="primary">{{ __('+ New player') }}</x-admin.button></span>
@endsection

@section('content')
    @php
        $label = fn (string $value) => ucwords(__(str_replace('_', ' ', $value)));
        $roleOptions = collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $battingOptions = collect(\App\Models\Player::BATTING_STYLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $bowlingOptions = collect(\App\Models\Player::BOWLING_STYLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $moreOpen = ($filters['batting_style'] ?? '') !== '' || ($filters['bowling_style'] ?? '') !== '';
    @endphp

    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.players.index')" :filters="$filters" :per-page="$perPage">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search name, phone, email&hellip;')" />
            <x-crud.select name="primary_role" :all="__('All roles')" :value="$filters['primary_role'] ?? ''" :options="$roleOptions" />
            <x-crud.select name="status" :all="__('All statuses')" :value="$filters['status'] ?? ''" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />

            <details class="crud-more order-last" @if($moreOpen) open @endif>
                <summary><x-crud.glyph name="filter" /> {{ __('Batting and bowling style') }}</summary>
                <div class="crud-more-grid">
                    <x-crud.select name="batting_style" :all="__('All batting styles')" :value="$filters['batting_style'] ?? ''" :options="$battingOptions" />
                    <x-crud.select name="bowling_style" :all="__('All bowling styles')" :value="$filters['bowling_style'] ?? ''" :options="$bowlingOptions" />
                </div>
            </details>
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">{{ __('Photo') }}</th>
                        <th><x-sortable-header column="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header></th>
                        <th>{{ __('Status') }}</th>
                        <th><x-sortable-header column="primary_role" :sort="$sort" :direction="$direction">{{ __('Role') }}</x-sortable-header></th>
                        <th class="hidden md:table-cell">{{ __('Batting') }}</th>
                        <th class="hidden md:table-cell">{{ __('Bowling') }}</th>
                        <th class="hidden lg:table-cell"><x-sortable-header column="player_registrations_count" :sort="$sort" :direction="$direction">{{ __('Registrations') }}</x-sortable-header></th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($players as $player)
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$player->photo_path" kind="user" size="sm" />
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.players.show', $player) }}" class="crud-row-link">{{ $player->name }}</a>
                                <span class="crud-meta md:hidden">
                                    {{ $player->primary_role ? $label($player->primary_role) : __('No role') }}
                                    @if($player->batting_style) &middot; {{ $label($player->batting_style) }} @endif
                                </span>
                                @if($player->phone || $player->email)
                                    <span class="crud-meta max-md:hidden">{{ $player->phone ?: $player->email }}</span>
                                @endif
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$player->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="capitalize">{{ $player->primary_role ? __(str_replace('_', ' ', $player->primary_role)) : '—' }}</td>
                            <td class="hidden capitalize md:table-cell">{{ $player->batting_style ? __(str_replace('_', ' ', $player->batting_style)) : '—' }}</td>
                            <td class="hidden capitalize md:table-cell">{{ $player->bowling_style ? __(str_replace('_', ' ', $player->bowling_style)) : '—' }}</td>
                            <td class="hidden tabular-nums lg:table-cell">{{ $player->player_registrations_count }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.players.show', $player)"
                                    :edit="route('admin.players.edit', $player)"
                                    :delete="route('admin.players.destroy', $player)"
                                    :name="$player->name"
                                    :confirm-text="__('This cannot be undone. Players with tournament history cannot be deleted.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="8" icon="user">
                            {{ array_filter($filters) ? __('No players match these filters.') : __('No players yet.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.players.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.players.create')" size="sm">{{ __('+ New player') }}</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $players->links() }}
    </div>

    <x-crud.fab :href="route('admin.players.create')" :label="__('New player')" />
@endsection
