@extends('layouts.admin')

@section('title', 'Finance — Overview')

@section('subtitle', $edition ? 'Money in, money out and committee dues for '.$edition->name.'.' : 'Money in, money out and committee dues.')

@section('actions')
    @if($edition)
        @can('create', \App\Models\EditionTransaction::class)
            <x-admin.button :href="route('admin.edition-transactions.create', ['type' => 'expense', 'edition_id' => $edition->id])" variant="secondary" size="sm">
                <x-crud.glyph name="expense" class="h-4 w-4 text-red-600" /> Add expense
            </x-admin.button>
            <x-admin.button :href="route('admin.edition-transactions.create', ['type' => 'income', 'edition_id' => $edition->id])" variant="primary" size="sm">
                <x-crud.glyph name="income" class="h-4 w-4" /> Add income
            </x-admin.button>
        @endcan
    @endif
@endsection

@section('content')
    @include('admin.finance._tabs')

    <form method="GET" action="{{ route('admin.finance.overview') }}" class="mb-4 flex items-center gap-2">
        <label for="finance-edition" class="crud-chip-label">Edition</label>
        <select id="finance-edition" name="edition_id" onchange="this.form.submit()" class="crud-field crud-select">
            @foreach($editions as $option)
                <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>

    @if(! $edition)
        <div class="crud-card">
            <x-admin.empty icon="currency" class="py-12!">No editions exist yet.</x-admin.empty>
        </div>
    @else
        <div class="crud-kpis sm:grid-cols-3! max-sm:[&>:last-child]:col-span-2">
            <x-crud.kpi label="Income" :value="money($financeSummary['income'])" tone="in" icon="income" />
            <x-crud.kpi label="Expense" :value="money($financeSummary['expense'])" tone="out" icon="expense" />
            <x-crud.kpi label="Balance" :value="money($financeSummary['balance'])" :tone="$financeSummary['balance'] < 0 ? 'out' : 'brand'" icon="currency" />
        </div>

        <div class="crud-card mb-4 flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
            <p class="text-[13px] text-slate-600">
                <span class="font-semibold tabular-nums text-slate-900">{{ money($contributionTotal) }}</span>
                <span class="text-slate-500">Total Contributions (already included in Income above)</span>
            </p>
            @can('viewAny', \App\Models\EditionContribution::class)
                <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="crud-link text-[13px]">See contributions &rarr;</a>
            @endcan
        </div>

        @if($duesSummary)
            <h3 class="crud-kicker mb-2 mt-6">Committee Dues Summary</h3>
            <div class="crud-kpis">
                <x-crud.kpi label="Committee Members" :value="$duesSummary['total_members']" icon="users" />
                <x-crud.kpi label="Paid in Full" :value="$duesSummary['paid_in_full']" icon="users" tone="in" />
                <x-crud.kpi label="Partially Paid" :value="$duesSummary['partially_paid']" icon="users" />
                <x-crud.kpi label="Not Paid" :value="$duesSummary['not_paid']" icon="users" tone="out" />
            </div>
            <div class="crud-kpis sm:grid-cols-3! max-sm:[&>:last-child]:col-span-2">
                <x-crud.kpi label="Total Target" :value="money($duesSummary['total_target'])" icon="currency" />
                <x-crud.kpi label="Total Contributed by Committee" :value="money($duesSummary['total_paid'])" icon="income" tone="in" />
                <x-crud.kpi label="Total Remaining" :value="money($duesSummary['total_remaining'])" icon="clock" />
            </div>

            @if(count($duesRows) > 0)
                <div class="crud-table-wrap">
                    <div class="crud-table-scroll">
                        <table class="crud-table">
                            <thead>
                                <tr>
                                    <th>Contributor</th>
                                    <th class="text-right">Target</th>
                                    <th class="text-right">Paid</th>
                                    <th class="text-right">Remaining</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($duesRows as $row)
                                    <tr>
                                        <td class="c-title whitespace-nowrap">
                                            @can('view', $row['contributor'])
                                                <a href="{{ route('admin.contributors.show', $row['contributor']) }}" class="hover:text-brand hover:underline">{{ $row['contributor']->name }}</a>
                                            @else
                                                {{ $row['contributor']->name }}
                                            @endcan
                                        </td>
                                        <td class="text-right tabular-nums">{{ money($row['target']) }}</td>
                                        <td class="text-right font-medium tabular-nums text-green-600">{{ money($row['paid']) }}</td>
                                        <td class="text-right tabular-nums">{{ money($row['remaining']) }}</td>
                                        <td><x-status-badge :status="$row['status']" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @can('viewAny', \App\Models\EditionCommitteeMember::class)
                <div class="mt-3">
                    <a href="{{ route('admin.finance.committee', ['edition_id' => $edition->id]) }}" class="crud-link text-[13px]">Manage committee &rarr;</a>
                </div>
            @endcan
        @endif
    @endif
@endsection
