@extends('layouts.admin')

@section('title', 'Players')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-table-filters :action="route('admin.players.index')" :filters="$filters" :per-page="$perPage">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search name, phone, email&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="primary_role" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All roles</option>
                @foreach(\App\Models\Player::PRIMARY_ROLES as $role)
                    <option value="{{ $role }}" @selected(($filters['primary_role'] ?? '') === $role)>
                        {{ ucwords(str_replace('_', ' ', $role)) }}
                    </option>
                @endforeach
            </select>

            <select name="batting_style" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All batting styles</option>
                @foreach(\App\Models\Player::BATTING_STYLES as $style)
                    <option value="{{ $style }}" @selected(($filters['batting_style'] ?? '') === $style)>
                        {{ ucwords(str_replace('_', ' ', $style)) }}
                    </option>
                @endforeach
            </select>

            <select name="bowling_style" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All bowling styles</option>
                @foreach(\App\Models\Player::BOWLING_STYLES as $style)
                    <option value="{{ $style }}" @selected(($filters['bowling_style'] ?? '') === $style)>
                        {{ ucwords(str_replace('_', ' ', $style)) }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

        </x-table-filters>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.players.export', $filters) }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Export
            </a>
            <a
                href="{{ route('admin.players.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
            >
                + New player
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Photo</th>
                    <th class="px-4 py-2 font-medium"><x-sortable-header column="name" :sort="$sort" :direction="$direction">Name</x-sortable-header></th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium"><x-sortable-header column="primary_role" :sort="$sort" :direction="$direction">Role</x-sortable-header></th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Batting</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Bowling</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell"><x-sortable-header column="player_registrations_count" :sort="$sort" :direction="$direction">Registrations</x-sortable-header></th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($players as $player)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                                @if($player->photo_path)
                                    <img
                                        src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                                        alt="{{ $player->name }}"
                                        class="h-full w-full object-cover"
                                    />
                                @else
                                    <x-icon name="user" class="h-4 w-4" />
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.players.show', $player) }}" class="hover:underline">
                                {{ $player->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$player->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="px-4 py-2 capitalize text-neutral-600">
                            {{ $player->primary_role ? str_replace('_', ' ', $player->primary_role) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 capitalize text-neutral-600 md:table-cell">
                            {{ $player->batting_style ? str_replace('_', ' ', $player->batting_style) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 capitalize text-neutral-600 md:table-cell">
                            {{ $player->bowling_style ? str_replace('_', ' ', $player->bowling_style) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 lg:table-cell">
                            {{ $player->player_registrations_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.players.show', $player) }}"
                                    title="View"
                                    aria-label="View {{ $player->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.players.edit', $player) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $player->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.players.destroy', $player) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete {{ $player->name }}?"
                                    data-confirm-text="This cannot be undone. Players with tournament history cannot be deleted."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete {{ $player->name }}"
                                        class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-neutral-400">
                            No players found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $players->links() }}
    </div>
@endsection
