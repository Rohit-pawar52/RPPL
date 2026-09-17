@extends('layouts.admin')

@section('title', 'Venues')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.venues.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search name or city&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100"
            />

            <select name="status" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.venues.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <a
            href="{{ route('admin.venues.create') }}"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500"
        >
            + New venue
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Venue</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Location</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Matches</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($venues as $venue)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.venues.show', $venue) }}" class="hover:underline">
                                {{ $venue->name }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ collect([$venue->city, $venue->country])->filter()->implode(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $venue->matches_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.venues.show', $venue) }}"
                                    title="View"
                                    aria-label="View {{ $venue->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.venues.edit', $venue) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $venue->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-blue-600"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.venues.destroy', $venue) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete {{ $venue->name }}?"
                                    data-confirm-text="This cannot be undone. Venues with existing match history cannot be deleted."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete {{ $venue->name }}"
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
                        <td colspan="5" class="px-4 py-8 text-center text-neutral-400">
                            No venues found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $venues->links() }}
    </div>
@endsection
