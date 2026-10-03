@extends('layouts.admin')

@section('title', 'Venues')

@section('subtitle', 'Grounds where matches are played.')

@section('actions')
    <x-admin.button href="{{ route('admin.venues.create') }}" variant="primary">+ New venue</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.venues.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search name, village or district&hellip;"
                class="w-full max-w-[220px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

            <x-admin.button variant="secondary">Filter</x-admin.button>

            @if(array_filter($filters))
                <a href="{{ route('admin.venues.index') }}" class="text-[13px] text-slate-400 hover:text-slate-600">
                    Clear filters
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Venue</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Location</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Matches</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($venues as $venue)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.venues.show', $venue) }}" class="hover:underline">
                                {{ $venue->name }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $venue->locationLabel() ?: '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" />
                            @if($venue->is_default)
                                <span class="ml-1 rounded-full bg-green-50 px-1.5 py-0.5 text-[10px] font-medium text-green-700 ring-1 ring-inset ring-green-200">Default</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $venue->matches_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.venues.show', $venue) }}"
                                    title="View"
                                    aria-label="View {{ $venue->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.venues.edit', $venue) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $venue->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
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
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="5">No venues found.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $venues->links() }}
    </div>
@endsection
