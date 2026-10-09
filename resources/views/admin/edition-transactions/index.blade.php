@extends('layouts.admin')

@section('title', 'Finance — Ledger')

@section('subtitle', number_format($transactions->total()).' '.\Illuminate\Support\Str::plural('entry', $transactions->total()).(array_filter($filters) ? ' match your filters.' : ' of money coming in and going out.'))

@section('actions')
    <a href="{{ route('admin.edition-transactions.export', $filters) }}" class="btn btn-secondary btn-sm max-sm:hidden">
        <x-icon name="document-chart" class="h-4 w-4" /> Export
    </a>
    @can('create', \App\Models\EditionTransaction::class)
        <x-admin.button :href="route('admin.edition-transactions.create', array_filter(['type' => 'expense', 'edition_id' => $filters['edition_id'] ?? null]))" variant="secondary" size="sm">
            <x-crud.glyph name="expense" class="h-4 w-4 text-red-600" /> Add expense
        </x-admin.button>
        <x-admin.button :href="route('admin.edition-transactions.create', array_filter(['type' => 'income', 'edition_id' => $filters['edition_id'] ?? null]))" variant="primary" size="sm">
            <x-crud.glyph name="income" class="h-4 w-4" /> Add income
        </x-admin.button>
    @endcan
@endsection

@section('content')
    @include('admin.finance._tabs')

    <div class="crud-kpis sm:grid-cols-3! max-sm:[&>:last-child]:col-span-2">
        <x-crud.kpi label="Total Income" :value="money($summary['income'])" tone="in" icon="income" />
        <x-crud.kpi label="Total Expense" :value="money($summary['expense'])" tone="out" icon="expense" />
        <x-crud.kpi label="Balance" :value="money($summary['balance'])" :tone="$summary['balance'] < 0 ? 'out' : 'brand'" icon="currency" :sub="isset($filters['edition_id']) ? 'For the chosen edition' : 'All editions'" />
    </div>

    {{-- Quick filter: money in, money out, or both. --}}
    <div class="crud-chips">
        <x-crud.chip :href="request()->fullUrlWithQuery(['type' => null, 'page' => null])" :active="empty($filters['type'])">All</x-crud.chip>
        <x-crud.chip :href="request()->fullUrlWithQuery(['type' => 'income', 'page' => null])" :active="($filters['type'] ?? '') === 'income'">
            <x-crud.glyph name="income" class="h-4 w-4 text-green-600" /> Income
        </x-crud.chip>
        <x-crud.chip :href="request()->fullUrlWithQuery(['type' => 'expense', 'page' => null])" :active="($filters['type'] ?? '') === 'expense'">
            <x-crud.glyph name="expense" class="h-4 w-4 text-red-600" /> Expense
        </x-crud.chip>
    </div>

    <div class="crud-toolbar">
        <x-table-filters :action="route('admin.edition-transactions.index')" :filters="$filters" :date-range="true" :per-page="$perPage">
            <x-crud.search :value="$filters['search'] ?? ''" placeholder="Search category, description&hellip;" />
            <x-crud.select name="edition_id" all="All editions" :value="$filters['edition_id'] ?? ''" :options="$editions->pluck('name', 'id')->all()" />
            @if(! empty($filters['type']))
                <input type="hidden" name="type" value="{{ $filters['type'] }}" />
            @endif
        </x-table-filters>
    </div>

    <div class="mb-3 flex items-center justify-end gap-2">
        <x-selected-report-action
            id="transactions-selected-export"
            :action="route('admin.edition-transactions.export-selected')"
            label="Export Selected ({count})"
        />
    </div>

    <div class="crud-table-wrap" data-row-selection="#transactions-selected-export-button">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-10">
                            <input type="checkbox" data-select-all aria-label="Select all transactions on this page" class="rounded border-slate-300" />
                        </th>
                        <th><x-sortable-header column="transaction_date" :sort="$sort" :direction="$direction">Date</x-sortable-header></th>
                        <th>Edition</th>
                        <th><x-sortable-header column="type" :sort="$sort" :direction="$direction">Type</x-sortable-header></th>
                        <th class="hidden md:table-cell">Category</th>
                        <th class="hidden xl:table-cell">Description</th>
                        <th class="text-right"><x-sortable-header column="amount" :sort="$sort" :direction="$direction">Amount</x-sortable-header></th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        @php $isIncome = $transaction->type === 'income'; @endphp
                        <tr class="crud-row">
                            <td class="c-check w-10">
                                <input
                                    type="checkbox"
                                    data-row-checkbox
                                    form="transactions-selected-export"
                                    name="selected_ids[]"
                                    value="{{ $transaction->id }}"
                                    aria-label="Select transaction {{ $transaction->id }}"
                                    class="rounded border-slate-300"
                                />
                            </td>
                            <td class="whitespace-nowrap text-slate-600 max-md:hidden">{{ $transaction->transaction_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap max-md:hidden">
                                <a href="{{ route('admin.edition-transactions.show', $transaction) }}" class="crud-row-link font-medium">{{ $transaction->edition->name }}</a>
                            </td>
                            <td class="c-media">
                                <span class="flex h-9 w-9 items-center justify-center rounded-full md:hidden {{ $isIncome ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600' }}"><x-crud.glyph :name="$isIncome ? 'income' : 'expense'" class="h-5 w-5" /></span>
                                <span class="inline-flex items-center gap-1.5 max-md:hidden">
                                    <x-status-badge :status="$transaction->type" />
                                    @if($transaction->contribution_exists)
                                        <span class="text-slate-400" title="Managed by a committee contribution"><x-crud.glyph name="lock" class="h-3.5 w-3.5" /></span>
                                    @endif
                                </span>
                            </td>
                            <td class="c-title md:hidden!">
                                <a href="{{ route('admin.edition-transactions.show', $transaction) }}" class="crud-row-link">{{ $transaction->category ?: ($transaction->description ?: ucfirst($transaction->type)) }}</a>
                                <span class="crud-meta">
                                    {{ $transaction->transaction_date->format('d M Y') }} &middot; {{ $transaction->edition->name }}
                                    @if($transaction->contribution_exists) &middot; <x-crud.glyph name="lock" class="inline h-3 w-3" /> contribution @endif
                                </span>
                            </td>
                            <td class="hidden md:table-cell">{{ $transaction->category ?? '—' }}</td>
                            <td class="hidden max-w-xs truncate xl:table-cell">{{ $transaction->description ?? '—' }}</td>
                            <td class="c-amount c-num whitespace-nowrap">
                                <span class="crud-money {{ $isIncome ? 'crud-money-in' : 'crud-money-out' }}">{{ $isIncome ? '+' : '−' }}{{ money($transaction->amount) }}</span>
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.edition-transactions.show', $transaction)"
                                    :edit="$transaction->contribution_exists ? null : route('admin.edition-transactions.edit', $transaction)"
                                    :delete="$transaction->contribution_exists ? null : route('admin.edition-transactions.destroy', $transaction)"
                                    name="transaction"
                                    confirm-title="Delete this transaction?"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="8" icon="currency">
                            {{ array_filter($filters) ? 'No transactions match these filters.' : 'No transactions found.' }}
                            <x-slot:action>
                                @if(array_filter($filters))
                                    <x-admin.button :href="route('admin.edition-transactions.index')" variant="secondary" size="sm">Clear filters</x-admin.button>
                                @else
                                    <x-admin.button :href="route('admin.edition-transactions.create')" size="sm">+ New transaction</x-admin.button>
                                @endif
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $transactions->links() }}
    </div>
@endsection
