@extends('layouts.admin')

@section('title', 'Finance — Overview')

@section('content')
    @include('admin.finance._tabs')

    <div class="mb-4">
        <form method="GET" action="{{ route('admin.finance.overview') }}" class="flex items-center gap-2">
            <select name="edition_id" onchange="this.form.submit()" class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring">
                @foreach($editions as $option)
                    <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-6 text-center text-sm text-neutral-400">
            No editions exist yet.
        </div>
    @else
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-stat-card label="Income" :value="money($financeSummary['income'])" icon="currency" />
            <x-stat-card label="Expense" :value="money($financeSummary['expense'])" icon="currency" />
            <x-stat-card label="Balance" :value="money($financeSummary['balance'])" icon="currency" />
        </div>

        <div class="mt-3">
            <x-stat-card label="Total Contributions (already included in Income above)" :value="money($contributionTotal)" icon="currency" />
        </div>

        @if($duesSummary)
            <h3 class="mb-2 mt-6 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Committee Dues Summary</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-stat-card label="Committee Members" :value="$duesSummary['total_members']" icon="users" />
                <x-stat-card label="Paid in Full" :value="$duesSummary['paid_in_full']" icon="users" />
                <x-stat-card label="Partially Paid" :value="$duesSummary['partially_paid']" icon="users" />
                <x-stat-card label="Not Paid" :value="$duesSummary['not_paid']" icon="users" />
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-stat-card label="Total Target" :value="money($duesSummary['total_target'])" icon="currency" />
                <x-stat-card label="Total Contributed by Committee" :value="money($duesSummary['total_paid'])" icon="currency" />
                <x-stat-card label="Total Remaining" :value="money($duesSummary['total_remaining'])" icon="currency" />
            </div>

            @if(count($duesRows) > 0)
                <div class="mt-4 overflow-x-auto rounded-lg border border-neutral-200 bg-white">
                    <table class="w-full min-w-[520px] text-left text-[13px]">
                        <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">Contributor</th>
                                <th class="px-4 py-2 text-right font-medium">Target</th>
                                <th class="px-4 py-2 text-right font-medium">Paid</th>
                                <th class="px-4 py-2 text-right font-medium">Remaining</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @foreach($duesRows as $row)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-2 font-medium text-neutral-800">
                                        <a href="{{ route('admin.contributors.show', $row['contributor']) }}" class="hover:underline">{{ $row['contributor']->name }}</a>
                                    </td>
                                    <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['target']) }}</td>
                                    <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['paid']) }}</td>
                                    <td class="px-4 py-2 text-right text-neutral-600">{{ money($row['remaining']) }}</td>
                                    <td class="px-4 py-2"><x-status-badge :status="$row['status']" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-3">
                <a href="{{ route('admin.finance.committee', ['edition_id' => $edition->id]) }}" class="text-xs theme-link hover:underline">
                    Manage committee &rarr;
                </a>
            </div>
        @endif
    @endif
@endsection
