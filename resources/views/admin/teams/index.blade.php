@extends('layouts.admin')

@section('title', 'Teams')

@section('subtitle', 'Master list of teams, separate from any edition.')

@section('actions')
    <x-admin.button href="{{ route('admin.teams.create') }}" variant="primary">+ New team</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.teams.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by name&hellip;"
                class="w-full max-w-[220px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

            <x-admin.button variant="secondary">Filter</x-admin.button>

            @if(array_filter($filters))
                <a href="{{ route('admin.teams.index') }}" class="text-[13px] text-slate-400 hover:text-slate-600">
                    Clear filters
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Logo</th>
                    <th class="px-4 py-2 font-medium">Team</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Editions</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($teams as $team)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-300">
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
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.teams.show', $team) }}" class="hover:underline">
                                {{ $team->name }}
                            </a>
                            @if($team->short_name)
                                <span class="ml-1 text-[11px] font-normal text-slate-400">({{ $team->short_name }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$team->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $team->edition_teams_count }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.teams.show', $team) }}"
                                    title="View"
                                    aria-label="View {{ $team->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.teams.edit', $team) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $team->name }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
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
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="5">No teams found.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $teams->links() }}
    </div>
@endsection
