@extends('layouts.admin')

@section('title', 'Edition Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.editions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to editions
        </a>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('admin.editions.report.pdf', $edition) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
            >
                Download Summary PDF
            </a>
            <a
                href="{{ route('admin.editions.edit', $edition) }}"
                class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
            >
                <x-icon name="pencil" class="h-3.5 w-3.5" />
                Edit
            </a>
        </div>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">{{ $edition->name }}</h2>
            <x-status-badge :status="$edition->status" />
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3">
            <div>
                <dt class="text-neutral-400">Year</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $edition->year }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Created</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $edition->created_at->format('d M Y') }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Last updated</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $edition->updated_at->format('d M Y') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 grid grid-cols-3 gap-3">
        <x-stat-card label="Registrations" :value="$edition->player_registrations_count" icon="clipboard" />
        <x-stat-card label="Teams" :value="$edition->edition_teams_count" icon="shield" />
        <x-stat-card label="Matches" :value="$edition->matches_count" icon="trophy" />
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Finance</h3>
            <a
                href="{{ route('admin.edition-transactions.index', ['edition_id' => $edition->id]) }}"
                class="text-xs font-medium theme-link hover:underline"
            >
                View Transactions
            </a>
        </div>
        <dl class="grid grid-cols-3 gap-3 text-xs">
            <div>
                <dt class="text-neutral-400">Income</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ money($financeSummary['income']) }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Expense</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ money($financeSummary['expense']) }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Balance</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ money($financeSummary['balance']) }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Contributions</h3>
            <a
                href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}"
                class="text-xs font-medium theme-link hover:underline"
            >
                View Contributions
            </a>
        </div>
        <dl class="grid grid-cols-2 gap-3 text-xs">
            <div>
                <dt class="text-neutral-400">Contributors</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $contributionSummary['contributors'] }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Total Contributions</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ money($contributionSummary['total']) }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4">
        @include('shared.standings._table', ['standings' => $standings['standings'], 'ignoredMatchesCount' => $standings['ignored_matches_count']])
    </div>

    <div class="mt-4">
        @include('shared.statistics._leaderboard', ['leaderboard' => $leaderboard])
    </div>

    <div class="mt-4">
        @include('shared.statistics._records', ['records' => $records])
    </div>
@endsection
