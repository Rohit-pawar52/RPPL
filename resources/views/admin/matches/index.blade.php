@extends('layouts.admin')

@section('title', 'Matches')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.matches.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search team or venue&hellip;"
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

            <select name="match_status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">All statuses</option>
                @foreach(\App\Models\GameMatch::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['match_status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.matches.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        @can('create', \App\Models\GameMatch::class)
            <a
                href="{{ route('admin.matches.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500"
            >
                + Schedule match
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">#</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Edition</th>
                    <th class="px-4 py-2 font-medium">Teams</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Venue</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Scheduled</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($matches as $match)
                    <tr class="hover:bg-neutral-50">
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $match->match_number ?? '—' }}</td>
                        <td class="hidden px-4 py-2 text-neutral-600 lg:table-cell">{{ $match->edition->name }}</td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.matches.show', $match) }}" class="hover:underline">
                                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $match->venue->name ?? 'TBD' }}</td>
                        <td class="hidden px-4 py-2 text-neutral-500 lg:table-cell">
                            {{ $match->scheduled_at->format('d M Y, h:i A') }}
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
                                        class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-blue-600"
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
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
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
