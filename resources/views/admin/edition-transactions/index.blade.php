@extends('layouts.admin')

@section('title', 'Finance')

@section('content')
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form method="GET" action="{{ route('admin.edition-transactions.index') }}" class="flex flex-wrap items-center gap-2">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search category, description&hellip;"
                class="w-full max-w-[220px] rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <select name="edition_id" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All editions</option>
                @foreach($editions as $edition)
                    <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>
                        {{ $edition->name }}
                    </option>
                @endforeach
            </select>

            <select name="type" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                <option value="">All types</option>
                @foreach(\App\Models\EditionTransaction::TYPES as $type)
                    <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>
                        {{ ucfirst($type) }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                Filter
            </button>

            @if(array_filter($filters))
                <a href="{{ route('admin.edition-transactions.index') }}" class="text-[13px] text-neutral-400 hover:text-neutral-600">
                    Clear filters
                </a>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.edition-transactions.export', $filters) }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-neutral-200 px-3 py-1.5 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="document-chart" class="h-4 w-4" />
                Export
            </a>
            <a
                href="{{ route('admin.edition-transactions.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
            >
                + New transaction
            </a>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Total Income" :value="money($summary['income'])" icon="currency" />
        <x-stat-card label="Total Expense" :value="money($summary['expense'])" icon="currency" />
        <x-stat-card label="Balance" :value="money($summary['balance'])" icon="currency" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Date</th>
                    <th class="px-4 py-2 font-medium">Edition</th>
                    <th class="px-4 py-2 font-medium">Type</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Category</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Description</th>
                    <th class="px-4 py-2 text-right font-medium">Amount</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($transactions as $transaction)
                    <tr class="hover:bg-neutral-50">
                        <td class="whitespace-nowrap px-4 py-2 text-neutral-600">
                            {{ $transaction->transaction_date->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.edition-transactions.show', $transaction) }}" class="hover:underline">
                                {{ $transaction->edition->name }}
                            </a>
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-1.5">
                                <x-status-badge :status="$transaction->type" />
                                @if($transaction->contribution_exists)
                                    <span class="text-[10px] text-neutral-400" title="Managed by a committee contribution">&#128274;</span>
                                @endif
                            </div>
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $transaction->category ?? '—' }}</td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">{{ $transaction->description ?? '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium text-neutral-800">
                            {{ money($transaction->amount) }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.edition-transactions.show', $transaction) }}"
                                    title="View"
                                    aria-label="View transaction"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                @unless($transaction->contribution_exists)
                                    <a
                                        href="{{ route('admin.edition-transactions.edit', $transaction) }}"
                                        title="Edit"
                                        aria-label="Edit transaction"
                                        class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                    >
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.edition-transactions.destroy', $transaction) }}"
                                        data-confirm-delete
                                        data-confirm-title="Delete this transaction?"
                                        data-confirm-text="This cannot be undone."
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            title="Delete"
                                            aria-label="Delete transaction"
                                            class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                        >
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
                            No transactions found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $transactions->links() }}
    </div>
@endsection
