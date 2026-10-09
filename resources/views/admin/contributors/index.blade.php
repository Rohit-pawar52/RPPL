@extends('layouts.admin')

@section('title', __('Finance — Contributors'))

@php
    $total = $contributors->total();
    $count = number_format($total);
    $subtitle = array_filter($filters)
        ? ($total === 1 ? __(':count contributor match your filters.', ['count' => $count]) : __(':count contributors match your filters.', ['count' => $count]))
        : ($total === 1 ? __(':count contributor who give to the tournament.', ['count' => $count]) : __(':count contributors who give to the tournament.', ['count' => $count]));
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.contributors.create')" variant="primary">{{ __('+ New contributor') }}</x-admin.button></span>
@endsection

@section('content')
    @include('admin.finance._tabs')

    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.contributors.index')" :filters="$filters" :per-page="$perPage">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search name, village or phone&hellip;')" />
            <x-crud.select name="status" :all="__('All statuses')" :value="$filters['status'] ?? ''" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16">{{ __('Photo') }}</th>
                        <th><x-sortable-header column="name" :sort="$sort" :direction="$direction">{{ __('Name') }}</x-sortable-header></th>
                        <th class="hidden sm:table-cell"><x-sortable-header column="village" :sort="$sort" :direction="$direction">{{ __('Village') }}</x-sortable-header></th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden md:table-cell"><x-sortable-header column="contributions_count" :sort="$sort" :direction="$direction">{{ __('Contributions') }}</x-sortable-header></th>
                        <th class="hidden lg:table-cell">{{ __('Committee') }}{{ $currentEdition ? ' ('.$currentEdition->name.')' : '' }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contributors as $contributor)
                        @php $isMember = $currentEdition && $contributor->committeeMemberships->isNotEmpty(); @endphp
                        <tr class="crud-row">
                            <td class="c-media w-16">
                                <x-crud.thumb :path="$contributor->photo_path" kind="user" size="sm" />
                            </td>
                            <td class="c-title">
                                <a href="{{ route('admin.contributors.show', $contributor) }}" class="crud-row-link">{{ $contributor->name }}</a>
                                <span class="crud-meta md:hidden">
                                    @if($contributor->village){{ $contributor->village }} &middot; @endif
                                    {{ $contributor->contributions_count }} {{ (int) $contributor->contributions_count === 1 ? __('contribution') : __('contributions') }}
                                    @if($isMember) &middot; {{ __('Committee') }} @endif
                                </span>
                            </td>
                            <td class="hidden sm:table-cell">
                                @if($contributor->village)
                                    {{ $contributor->village }}
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$contributor->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="hidden tabular-nums md:table-cell">{{ $contributor->contributions_count }}</td>
                            <td class="hidden lg:table-cell">
                                @if($isMember)
                                    <span class="crud-pill crud-pill-blue">{{ __('Committee Member') }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.contributors.show', $contributor)"
                                    :edit="route('admin.contributors.edit', $contributor)"
                                    :delete="route('admin.contributors.destroy', $contributor)"
                                    :name="$contributor->name"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="users">
                            {{ array_filter($filters) ? __('No contributors match these filters.') : __('No contributors found.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.contributors.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.contributors.create')" size="sm">{{ __('+ New contributor') }}</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $contributors->links() }}
    </div>

    <x-crud.fab :href="route('admin.contributors.create')" :label="__('New contributor')" />
@endsection
