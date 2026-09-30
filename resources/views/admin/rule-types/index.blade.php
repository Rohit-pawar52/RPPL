@extends('layouts.admin')

@section('title', 'Rule Types')

@section('subtitle', "Categories that rules are grouped under. A type that still has rules can't be deleted.")

@section('actions')
    <x-admin.button href="{{ route('admin.rule-types.create') }}" variant="primary">+ New rule type</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rules.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to rules
        </a>
    </div>


    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 text-right font-medium">Order</th>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Slug</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Rules</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($ruleTypes as $ruleType)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 text-right text-slate-600">
                            {{ $ruleType->sort_order }}
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ $ruleType->name }}
                        </td>
                        <td class="hidden px-4 py-2 font-mono text-[12px] text-slate-500 md:table-cell">
                            {{ $ruleType->slug }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$ruleType->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">
                            <a href="{{ route('admin.rules.index', ['rule_type_id' => $ruleType->id]) }}" class="hover:underline">
                                {{ $ruleType->rules_count }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.rule-types.edit', $ruleType) }}"
                                    title="Edit"
                                    aria-label="Edit rule type"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.rule-types.destroy', $ruleType) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this rule type?"
                                    data-confirm-text="Only a rule type with no rules can be deleted. This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete rule type"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="6">No rule types yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $ruleTypes->links() }}
    </div>
@endsection
