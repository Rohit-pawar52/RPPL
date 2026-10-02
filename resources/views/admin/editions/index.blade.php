@extends('layouts.admin')

@section('title', 'Editions')

@section('subtitle', 'Tournament editions and their current status.')

@section('actions')
    <x-admin.button href="{{ route('admin.editions.create') }}" variant="primary">+ New edition</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.editions.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name&hellip;"
                class="w-full max-w-[200px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All statuses</option>
                @foreach(\App\Models\Edition::STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                        {{ ucfirst($status) }}
                    </option>
                @endforeach
            </select>

            <input
                type="number"
                name="year"
                value="{{ $filters['year'] ?? '' }}"
                placeholder="Year"
                class="w-24 rounded-md border border-slate-300 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <x-admin.button variant="secondary">Filter</x-admin.button>

            @if(array_filter($filters))
                <a href="{{ route('admin.editions.index') }}" class="text-[13px] text-slate-400 hover:text-slate-600">
                    Clear filters
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Year</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Registrations</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Teams</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Matches</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Created</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($editions as $edition)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">{{ $edition->name }}</td>
                        <td class="px-4 py-2 text-slate-600">{{ $edition->year }}</td>
                        <td class="px-4 py-2"><x-status-badge :status="$edition->status" /></td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">{{ $edition->player_registrations_count }}</td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">{{ $edition->edition_teams_count }}</td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">{{ $edition->matches_count }}</td>
                        <td class="hidden px-4 py-2 text-slate-500 lg:table-cell">{{ display_datetime($edition->created_at, 'd M Y') }}</td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.editions.show', $edition) }}"
                                    title="View"
                                    aria-label="View {{ $edition->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.editions.edit', $edition) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $edition->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.editions.destroy', $edition) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete {{ $edition->name }}?"
                                    data-confirm-text="This cannot be undone. Editions with existing tournament data cannot be deleted."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete {{ $edition->name }}"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="8">No editions found.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $editions->links() }}
    </div>
@endsection
