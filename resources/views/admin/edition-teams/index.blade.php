@extends('layouts.admin')

@section('title', 'Edition Teams')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.edition-teams.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search team name&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />

            <select name="edition_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.edition-teams.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <a
            href="{{ route('admin.edition-teams.create') }}"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500"
        >
            + Add team to edition
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Team</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Team Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Squad</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Added</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($editionTeams as $editionTeam)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 text-neutral-600">{{ $editionTeam->edition->name }}</td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.edition-teams.show', $editionTeam) }}" class="hover:underline">
                                {{ $editionTeam->team->name }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 md:table-cell">
                            <x-status-badge :status="$editionTeam->team->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $editionTeam->team_players_count }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-500 lg:table-cell">
                            {{ $editionTeam->created_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.edition-teams.show', $editionTeam) }}"
                                    title="View"
                                    aria-label="View {{ $editionTeam->team->name }} in {{ $editionTeam->edition->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.edition-teams.destroy', $editionTeam) }}"
                                    data-confirm-delete
                                    data-confirm-title="Remove {{ $editionTeam->team->name }} from {{ $editionTeam->edition->name }}?"
                                    data-confirm-text="This cannot be undone. Teams with existing squad or match data cannot be removed."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Remove"
                                        aria-label="Remove {{ $editionTeam->team->name }} from {{ $editionTeam->edition->name }}"
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
                            No teams have been added to any edition yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $editionTeams->links() }}
    </div>
@endsection
