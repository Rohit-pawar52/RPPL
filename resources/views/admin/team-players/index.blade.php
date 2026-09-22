@extends('layouts.admin')

@section('title', 'Squads')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.team-players.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search player name&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="edition_team_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All teams</option>
                @foreach($editionTeams as $editionTeam)
                    <option value="{{ $editionTeam->id }}" @selected(($filters['edition_team_id'] ?? '') == $editionTeam->id)>
                        {{ $editionTeam->edition->name }} — {{ $editionTeam->team->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.team-players.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <a
            href="{{ route('admin.team-players.create') }}"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
        >
            + Add to squad
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Player</th>
                    <th class="px-4 py-2 font-medium">Team / Edition</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Jersey</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Role</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Matches</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($teamPlayers as $teamPlayer)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.team-players.show', $teamPlayer) }}" class="hover:underline">
                                {{ $teamPlayer->playerRegistration->player->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-neutral-600">
                            {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $teamPlayer->jersey_number ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-2 capitalize text-neutral-600 md:table-cell">
                            {{ $teamPlayer->role ? str_replace('_', ' ', $teamPlayer->role) : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 lg:table-cell">
                            {{ $teamPlayer->match_players_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.team-players.show', $teamPlayer) }}"
                                    title="View"
                                    aria-label="View squad player"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.team-players.edit', $teamPlayer) }}"
                                    title="Edit"
                                    aria-label="Edit squad player"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.team-players.destroy', $teamPlayer) }}"
                                    data-confirm-delete
                                    data-confirm-title="Remove this player from the squad?"
                                    data-confirm-text="This cannot be undone. Players with existing match history cannot be removed."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Remove"
                                        aria-label="Remove squad player"
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
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                            No squad assignments yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $teamPlayers->links() }}
    </div>
@endsection
