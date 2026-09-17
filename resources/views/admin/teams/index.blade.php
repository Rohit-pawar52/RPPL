@extends('layouts.admin')

@section('title', 'Teams')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.teams.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name&hellip;"
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
                <a href="{{ route('admin.teams.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <a
            href="{{ route('admin.teams.create') }}"
            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-blue-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-blue-500"
        >
            + New team
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Logo</th>
                    <th class="px-4 py-2 font-medium">Team</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Editions</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($teams as $team)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                                @if($team->logo_path)
                                    <img
                                        src="{{ Illuminate\Support\Facades\Storage::url($team->logo_path) }}"
                                        alt="{{ $team->name }}"
                                        class="h-full w-full object-cover"
                                    />
                                @else
                                    <x-icon name="shield" class="h-4 w-4" />
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.teams.show', $team) }}" class="hover:underline">
                                {{ $team->name }}
                            </a>
                            @if($team->short_name)
                                <span class="ml-1 text-[11px] font-normal text-neutral-400">({{ $team->short_name }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$team->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $team->edition_teams_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.teams.show', $team) }}"
                                    title="View"
                                    aria-label="View {{ $team->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.teams.edit', $team) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $team->name }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-blue-600"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.teams.destroy', $team) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete {{ $team->name }}?"
                                    data-confirm-text="This cannot be undone. Teams with existing tournament history cannot be deleted."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete {{ $team->name }}"
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
                            No teams found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $teams->links() }}
    </div>
@endsection
