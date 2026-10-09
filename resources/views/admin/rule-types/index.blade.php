@extends('layouts.admin')

@section('title', __('Rule Types'))

@section('subtitle', __("Categories that rules are grouped under. A type that still has rules can't be deleted."))

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.rule-types.create')" variant="primary">{{ __('+ New rule type') }}</x-admin.button></span>
@endsection

@section('content')
    <x-crud.back :href="route('admin.rules.index')">{{ __('Rules') }}</x-crud.back>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-16 text-right">{{ __('Order') }}</th>
                        <th>{{ __('Name') }}</th>
                        <th class="hidden md:table-cell">{{ __('Slug') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('Rules') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ruleTypes as $ruleType)
                        <tr class="crud-row">
                            <td class="c-media w-16 text-right tabular-nums text-slate-400">{{ $ruleType->sort_order }}</td>
                            <td class="c-title max-w-xs">
                                <a href="{{ route('admin.rule-types.edit', $ruleType) }}" class="crud-row-link" aria-label="{{ __('Edit rule type') }}">{{ $ruleType->name }}</a>
                                <span class="crud-meta sm:hidden">{{ $ruleType->rules_count }} {{ (int) $ruleType->rules_count === 1 ? __('rule') : __('rules') }}</span>
                            </td>
                            <td class="hidden font-mono text-[12px] text-slate-500 md:table-cell">{{ $ruleType->slug }}</td>
                            <td class="c-sub">
                                <x-status-badge :status="$ruleType->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="hidden text-right tabular-nums sm:table-cell">
                                <a href="{{ route('admin.rules.index', ['rule_type_id' => $ruleType->id]) }}" class="relative z-1 crud-link">{{ $ruleType->rules_count }}</a>
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :edit="route('admin.rule-types.edit', $ruleType)"
                                    :delete="route('admin.rule-types.destroy', $ruleType)"
                                    :name="__('rule type')"
                                    :confirm-title="__('Delete this rule type?')"
                                    :confirm-text="__('Only a rule type with no rules can be deleted. This cannot be undone.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="6" icon="book">
                            {{ __('No rule types yet.') }}
                            <x-slot:action>
                                <x-admin.button :href="route('admin.rule-types.create')" size="sm">{{ __('+ New rule type') }}</x-admin.button>
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $ruleTypes->links() }}
    </div>

    <x-crud.fab :href="route('admin.rule-types.create')" :label="__('New rule type')" />
@endsection
