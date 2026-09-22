@extends('layouts.admin')

@section('title', 'Matches')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <x-table-filters :action="route('admin.matches.index')" :filters="$filters" :date-range="true">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search team or venue&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="edition_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <select name="match_status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All statuses</option>
                @foreach(\App\Models\GameMatch::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['match_status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>
        </x-table-filters>

        <div class="flex items-center gap-2">
            <x-selected-report-action
                id="matches-selected-export"
                :action="route('admin.matches.export-selected')"
                label="Export Selected ({count})"
            />
            <a
                href="{{ route('admin.matches.export', $filters) }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Export
            </a>
            @can('create', \App\Models\GameMatch::class)
                <a
                    href="{{ route('admin.matches.create') }}"
                    class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
                >
                    + Schedule match
                </a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white" data-row-selection="#matches-selected-export-button">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="w-8 px-4 py-2">
                        <input type="checkbox" data-select-all aria-label="Select all matches on this page" />
                    </th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">#</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Edition</th>
                    <th class="px-4 py-2 font-medium">Teams</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Venue</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell"><x-sortable-header column="scheduled_at" :sort="$sort" :direction="$direction">Scheduled</x-sortable-header></th>
                    <th class="px-4 py-2 font-medium"><x-sortable-header column="match_status" :sort="$sort" :direction="$direction">Status</x-sortable-header></th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($matches as $match)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <input
                                type="checkbox"
                                data-row-checkbox
                                form="matches-selected-export"
                                name="selected_ids[]"
                                value="{{ $match->id }}"
                                aria-label="Select match {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}"
                            />
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $match->match_number ?? '—' }}</td>
                        <td class="hidden px-4 py-2 text-neutral-600 lg:table-cell">{{ $match->edition->name }}</td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.matches.show', $match) }}" class="hover:underline">
                                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $match->venue->name ?? 'TBD' }}</td>
                        <td class="hidden px-4 py-2 text-neutral-500 lg:table-cell">
                            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$match->match_status" />
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.matches.show', $match) }}"
                                    title="View"
                                    aria-label="View match"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                @can('update', $match)
                                    <a
                                        href="{{ route('admin.matches.edit', $match) }}"
                                        title="Edit"
                                        aria-label="Edit match"
                                        class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                    >
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                @endcan
                                @can('delete', $match)
                                    <form
                                        method="POST"
                                        action="{{ route('admin.matches.destroy', $match) }}"
                                        data-confirm-delete
                                        data-confirm-title="Delete this match?"
                                        data-confirm-text="This cannot be undone. Matches with existing squad or scoring data cannot be deleted."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="Delete"
                                            aria-label="Delete match"
                                            class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-neutral-400">
                            No matches scheduled yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $matches->links() }}
    </div>
@endsection
