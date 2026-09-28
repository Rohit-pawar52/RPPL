@extends('layouts.admin')

@section('title', 'Rule Types')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rules.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to rules
        </a>
    </div>

    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-[13px] text-neutral-500">
            Categories that Rules &amp; Regulations are grouped under. Deactivating a type hides all of its rules from the public website; a type that still has rules can't be deleted.
        </p>
        <a
            href="{{ route('admin.rule-types.create') }}"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
        >
            + New rule type
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 text-right font-medium">Order</th>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Slug</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Rules</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($ruleTypes as $ruleType)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 text-right text-neutral-600">
                            {{ $ruleType->sort_order }}
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-neutral-800">
                            {{ $ruleType->name }}
                        </td>
                        <td class="hidden px-4 py-2 font-mono text-[12px] text-neutral-500 md:table-cell">
                            {{ $ruleType->slug }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$ruleType->is_active ? 'active' : 'inactive'" />
                        </td>
                        <td class="hidden px-4 py-2 text-right text-neutral-600 sm:table-cell">
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
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
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
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                            No rule types yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $ruleTypes->links() }}
    </div>
@endsection
