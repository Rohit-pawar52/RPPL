@extends('layouts.admin')

@section('title', __('Venues'))

@php
    $total = $venues->total();
    $count = number_format($total);
    $subtitle = array_filter($filters)
        ? ($total === 1 ? __(':count ground match your filters.', ['count' => $count]) : __(':count grounds match your filters.', ['count' => $count]))
        : ($total === 1 ? __(':count ground where matches are played.', ['count' => $count]) : __(':count grounds where matches are played.', ['count' => $count]));
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.venues.create')" variant="primary">{{ __('+ New venue') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.venues.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search name, village or district&hellip;')" />
            <x-crud.select name="status" :all="__('All statuses')" :value="$filters['status'] ?? ''" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-14"></th>
                        <th>{{ __('Venue') }}</th>
                        <th class="hidden md:table-cell">{{ __('Location') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden md:table-cell">{{ __('Matches') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
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
                                <span class="crud-meta md:hidden">{{ $venue->locationLabel() ?: __('No location') }}</span>
                            </td>
                            <td class="hidden md:table-cell">{{ $venue->locationLabel() ?: '—' }}</td>
                            <td class="c-sub">
                                <span class="inline-flex flex-wrap items-center gap-1.5">
                                    <x-status-badge :status="$venue->is_active ? 'active' : 'inactive'" />
                                    @if($venue->is_default)
                                        <span class="crud-pill crud-pill-brand">{{ __('Default') }}</span>
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
                                    :confirm-text="__('This cannot be undone. Venues with existing match history cannot be deleted.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="6" icon="map-pin">
                            {{ array_filter($filters) ? __('No venues match these filters.') : __('No venues yet.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.venues.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.venues.create')" size="sm">{{ __('+ New venue') }}</x-admin.button>
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

    <x-crud.fab :href="route('admin.venues.create')" :label="__('New venue')" />
@endsection
