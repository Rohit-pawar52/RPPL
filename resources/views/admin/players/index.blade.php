@extends('layouts.admin')

@section('title', 'Players')

@section('subtitle', number_format($players->total()).' '.\Illuminate\Support\Str::plural('player', $players->total()).(array_filter($filters) ? ' match your filters.' : ' in the master directory, separate from any edition.'))

@section('actions')
    <x-admin.button :href="route('admin.players.export', $filters)" variant="secondary" icon="document-chart">Export</x-admin.button>
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.players.create')" variant="primary">+ New player</x-admin.button></span>
@endsection

@section('content')
    @php
        $label = fn (string $value) => ucwords(str_replace('_', ' ', $value));
        $roleOptions = collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $battingOptions = collect(\App\Models\Player::BATTING_STYLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $bowlingOptions = collect(\App\Models\Player::BOWLING_STYLES)->mapWithKeys(fn ($v) => [$v => $label($v)])->all();
        $moreOpen = ($filters['batting_style'] ?? '') !== '' || ($filters['bowling_style'] ?? '') !== '';
    @endphp

    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.players.index')" :filters="$filters" :per-page="$perPage">
            <x-crud.search :value="$filters['search'] ?? ''" placeholder="Search name, phone, email&hellip;" />
            <x-crud.select name="primary_role" all="All roles" :value="$filters['primary_role'] ?? ''" :options="$roleOptions" />
            <x-crud.select name="status" all="All statuses" :value="$filters['status'] ?? ''" :options="['active' => 'Active', 'inactive' => 'Inactive']" />

            <details class="crud-more order-last" @if($moreOpen) open @endif>
                <summary><x-crud.glyph name="filter" /> Batting and bowling style</summary>
                <div class="crud-more-grid">
                    <x-crud.select name="batting_style" all="All batting styles" :value="$filters['batting_style'] ?? ''" :options="$battingOptions" />
                    <x-crud.select name="bowling_style" all="All bowling styles" :value="$filters['bowling_style'] ?? ''" :options="$bowlingOptions" />
                </div>
            </details>
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">Photo</th>
                        <th><x-sortable-header column="name" :sort="$sort" :direction="$direction">Name</x-sortable-header></th>
                        <th>Status</th>
                        <th><x-sortable-header column="primary_role" :sort="$sort" :direction="$direction">Role</x-sortable-header></th>
                        <th class="hidden md:table-cell">Batting</th>
                        <th class="hidden md:table-cell">Bowling</th>
                        <th class="hidden lg:table-cell"><x-sortable-header column="player_registrations_count" :sort="$sort" :direction="$direction">Registrations</x-sortable-header></th>
                        <th class="text-right">Actions</th>
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
                                    {{ $player->primary_role ? $label($player->primary_role) : 'No role' }}
                                    @if($player->batting_style) &middot; {{ $label($player->batting_style) }} @endif
                                </span>
                                @if($player->phone || $player->email)
                                    <span class="crud-meta max-md:hidden">{{ $player->phone ?: $player->email }}</span>
                                @endif
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$player->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="capitalize">{{ $player->primary_role ? str_replace('_', ' ', $player->primary_role) : '—' }}</td>
                            <td class="hidden capitalize md:table-cell">{{ $player->batting_style ? str_replace('_', ' ', $player->batting_style) : '—' }}</td>
                            <td class="hidden capitalize md:table-cell">{{ $player->bowling_style ? str_replace('_', ' ', $player->bowling_style) : '—' }}</td>
                            <td class="hidden tabular-nums lg:table-cell">{{ $player->player_registrations_count }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.players.show', $player)"
                                    :edit="route('admin.players.edit', $player)"
                                    :delete="route('admin.players.destroy', $player)"
                                    :name="$player->name"
                                    confirm-text="This cannot be undone. Players with tournament history cannot be deleted."
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="8" icon="user">
                            {{ array_filter($filters) ? 'No players match these filters.' : 'No players yet.' }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.players.index')" variant="secondary" size="sm">Clear filters</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.players.create')" size="sm">+ New player</x-admin.button>
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

    <x-crud.fab :href="route('admin.players.create')" label="New player" />
@endsection
