@extends('layouts.admin')

@section('title', 'Rules & Regulations')

@section('subtitle', 'Tournament rules shown on the public Rules & Regulations page, grouped by type.')

@section('actions')
    <x-admin.button href="{{ route('admin.rule-types.index') }}" variant="secondary">Manage Rule Types</x-admin.button>
    <x-admin.button href="{{ route('admin.rules.create') }}" variant="primary">+ New rule</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <form method="GET" action="{{ route('admin.rules.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search by title&hellip;"
                class="w-full max-w-[220px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="rule_type_id" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All rule types</option>
                @foreach($ruleTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) ($filters['rule_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>

            <select name="status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            </select>

            <x-admin.button variant="secondary">Filter</x-admin.button>

            @if(array_filter($filters))
                <a href="{{ route('admin.rules.index') }}" class="text-[13px] text-slate-400 hover:text-slate-600">
                    Clear filters
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 text-right font-medium">Order</th>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Type</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-center font-medium sm:table-cell">Important</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Updated</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rules as $rule)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 text-right text-slate-600">
                            {{ $rule->sort_order }}
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ Illuminate\Support\Str::limit($rule->title, 60) }}
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $rule->ruleType?->name }}
                            @if($rule->ruleType && ! $rule->ruleType->is_active)
                                <span class="ml-1 text-[11px] text-slate-400">(inactive type)</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$rule->status" />
                        </td>
                        <td class="hidden px-4 py-2 text-center sm:table-cell">
                            @if($rule->is_important)
                                <span title="Important rule" aria-label="Important rule" class="inline-flex text-amber-500">
                                    <x-icon name="star" class="h-4 w-4" />
                                </span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 md:table-cell">
                            {{ display_datetime($rule->updated_at, 'd M Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.rules.edit', $rule) }}"
                                    title="Edit"
                                    aria-label="Edit rule"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.rules.destroy', $rule) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this rule?"
                                    data-confirm-text="This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete rule"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="7">{{ array_filter($filters) ? 'No rules match these filters.' : 'No rules yet.' }}</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $rules->links() }}
    </div>
@endsection
