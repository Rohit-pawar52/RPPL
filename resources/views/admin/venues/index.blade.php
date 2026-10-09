@extends('layouts.admin')

@section('title', 'Venues')

@section('subtitle', number_format($venues->total()).' '.\Illuminate\Support\Str::plural('ground', $venues->total()).(array_filter($filters) ? ' match your filters.' : ' where matches are played.'))

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.venues.create')" variant="primary">+ New venue</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.venues.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" placeholder="Search name, village or district&hellip;" />
            <x-crud.select name="status" all="All statuses" :value="$filters['status'] ?? ''" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-14"></th>
                        <th>Venue</th>
                        <th class="hidden md:table-cell">Location</th>
                        <th>Status</th>
                        <th class="hidden md:table-cell">Matches</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($venues as $venue)
                        <tr class="crud-row">
                            <td class="c-media w-14">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-soft text-brand"><x-icon name="map-pin" class="h-5 w-5" /></span>
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.venues.show', $venue) }}" class="crud-row-link">{{ $venue->name }}</a>
                                <span class="crud-meta md:hidden">{{ $venue->locationLabel() ?: 'No location' }}</span>
                            </td>
                            <td class="hidden md:table-cell">{{ $venue->locationLabel() ?: '—' }}</td>
                            <td class="c-sub">
                                <span class="inline-flex flex-wrap items-center gap-1.5">
                                    <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" />
                                    @if($venue->is_default)
                                        <span class="crud-pill crud-pill-brand">Default</span>
                                    @endif
                                </span>
                            </td>
                            <td class="hidden tabular-nums md:table-cell">{{ $venue->matches_count }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.venues.show', $venue)"
                                    :edit="route('admin.venues.edit', $venue)"
                                    :delete="route('admin.venues.destroy', $venue)"
                                    :name="$venue->name"
                                    confirm-text="This cannot be undone. Venues with existing match history cannot be deleted."
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="6" icon="map-pin">
                            {{ array_filter($filters) ? 'No venues match these filters.' : 'No venues yet.' }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.venues.index')" variant="secondary" size="sm">Clear filters</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.venues.create')" size="sm">+ New venue</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $venues->links() }}
    </div>

    <x-crud.fab :href="route('admin.venues.create')" label="New venue" />
@endsection
