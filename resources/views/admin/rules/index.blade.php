@extends('layouts.admin')

@section('title', __('Rules & Regulations'))

@php
    $total = $rules->total();
    $count = number_format($total);
    $subtitle = $total === 1
        ? __(':count rule shown on the public Rules & Regulations page, grouped by type.', ['count' => $count])
        : __(':count rules shown on the public Rules & Regulations page, grouped by type.', ['count' => $count]);
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <x-admin.button :href="route('admin.rule-types.index')" variant="secondary">{{ __('Manage Rule Types') }}</x-admin.button>
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.rules.create')" variant="primary">{{ __('+ New rule') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.rules.index')" :filters="$filters">
            <x-crud.search :value="$filters['search'] ?? ''" :placeholder="__('Search by title&hellip;')" />
            <x-crud.select name="rule_type_id" :all="__('All rule types')" :value="$filters['rule_type_id'] ?? ''" :options="$ruleTypes->pluck('name', 'id')->all()" />
            <x-crud.select name="status" :all="__('All statuses')" :value="$filters['status'] ?? ''" :options="['active' => __('Active'), 'inactive' => __('Inactive')]" />
        </x-table-filters>
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16 text-right">{{ __('Order') }}</th>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden text-center sm:table-cell">{{ __('Important') }}</th>
                        <th class="hidden md:table-cell">{{ __('Updated') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $rule)
                        <tr class="crud-row">
                            <td class="c-media w-16 text-right tabular-nums text-slate-400">{{ $rule->sort_order }}</td>
                            <td class="c-title max-w-md">
                                <a href="{{ route('admin.rules.edit', $rule) }}" class="crud-row-link" aria-label="{{ __('Edit rule') }}">{{ Illuminate\Support\Str::limit($rule->title, 70) }}</a>
                                @if($rule->is_important)
                                    <span class="ml-1 inline-flex align-middle text-amber-500 sm:hidden" aria-label="{{ __('Important rule') }}"><x-icon name="star" class="h-3.5 w-3.5" /></span>
                                @endif
                                <span class="crud-meta md:hidden">{{ $rule->ruleType?->name }}</span>
                            </td>
                            <td class="max-md:hidden">
                                <span class="crud-pill">{{ $rule->ruleType?->name }}</span>
                                @if($rule->ruleType && ! $rule->ruleType->is_active)
                                    <span class="ml-1 text-[11px] text-slate-400">{{ __('(inactive type)') }}</span>
                                @endif
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$rule->status" />
                            </td>
                            <td class="hidden text-center sm:table-cell">
                                @if($rule->is_important)
                                    <span title="{{ __('Important rule') }}" aria-label="{{ __('Important rule') }}" class="inline-flex text-amber-500">
                                        <x-icon name="star" class="h-4 w-4" />
                                    </span>
                                @endif
                            </td>
                            <td class="hidden whitespace-nowrap text-slate-500 md:table-cell">{{ display_datetime($rule->updated_at, 'd M Y') }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :edit="route('admin.rules.edit', $rule)"
                                    :delete="route('admin.rules.destroy', $rule)"
                                    :name="__('rule')"
                                    :confirm-title="__('Delete this rule?')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="book">
                            {{ array_filter($filters) ? __('No rules match these filters.') : __('No rules yet.') }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.rules.index')" variant="secondary" size="sm">{{ __('Clear filters') }}</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.rules.create')" size="sm">{{ __('+ New rule') }}</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $rules->links() }}
    </div>

    <x-crud.fab :href="route('admin.rules.create')" :label="__('New rule')" />
@endsection
