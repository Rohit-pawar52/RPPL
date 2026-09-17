@extends('layouts.admin')

@section('title', 'Reports')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 class="text-base font-semibold text-neutral-900">Reports</h1>

        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-center gap-2">
            <select
                name="edition_id"
                onchange="this.form.submit()"
                class="rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100"
            >
                @foreach($editions as $option)
                    <option value="{{ $option->id }}" @selected($edition && $edition->id === $option->id)>
                        {{ $option->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-neutral-900">No editions available yet</h2>
            <p class="text-xs text-neutral-500">Create a tournament edition to generate reports here.</p>
        </div>
    @else
        {{-- A. Tournament Reports --}}
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Tournament Reports</h3>
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                <div class="min-w-0">
                    <p class="font-medium text-neutral-800">Edition Summary</p>
                    <p class="text-[11px] text-neutral-500">Overview, registrations, teams, matches, standings, and top player statistics &middot; PDF</p>
                </div>
                <a
                    href="{{ route('admin.editions.report.pdf', $edition) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    Download PDF
                </a>
            </div>
        </div>

        {{-- B. Registration Reports --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Registration Reports</h3>
            <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                <div class="min-w-0">
                    <p class="font-medium text-neutral-800">Player Registrations</p>
                    <p class="text-[11px] text-neutral-500">Every registration for this edition, with payment status &middot; CSV</p>
                </div>
                <a
                    href="{{ route('admin.player-registrations.export', ['edition_id' => $edition->id]) }}"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                >
                    <x-icon name="document-chart" class="h-3.5 w-3.5" />
                    Export CSV
                </a>
            </div>
        </div>

        {{-- C. Finance & Contributions --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Finance &amp; Contributions</h3>
            <div class="space-y-2">
                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Finance Transactions</p>
                        <p class="text-[11px] text-neutral-500">The full income/expense ledger for this edition &middot; CSV</p>
                    </div>
                    <a
                        href="{{ route('admin.edition-transactions.export', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Export CSV
                    </a>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Contributions</p>
                        <p class="text-[11px] text-neutral-500">
                            Individual committee/general contribution records for this edition &middot; CSV.
                            Individual receipts remain available from
                            <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="text-blue-600 hover:underline">Edition Contributions</a>.
                        </p>
                    </div>
                    <a
                        href="{{ route('admin.edition-contributions.export', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Export CSV
                    </a>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-md border border-neutral-100 px-3 py-2.5 text-[13px]">
                    <div class="min-w-0">
                        <p class="font-medium text-neutral-800">Financial Summary</p>
                        <p class="text-[11px] text-neutral-500">Income, expenses, balance, registration payments, and contributions in one printable page &middot; HTML</p>
                    </div>
                    <a
                        href="{{ route('admin.reports.financial-summary', ['edition_id' => $edition->id]) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        View
                    </a>
                </div>
            </div>
        </div>

        {{-- D. Match Reports --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Match Reports</h3>
                <a href="{{ route('admin.matches.index', ['edition_id' => $edition->id]) }}" class="text-[11px] font-medium text-blue-600 hover:underline">
                    View all matches &rarr;
                </a>
            </div>

            @forelse($recentMatches as $match)
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <div class="min-w-0">
                        <a href="{{ route('admin.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                        </a>
                        <p class="text-[11px] text-neutral-500">{{ $match->scheduled_at->format('d M Y') }}</p>
                    </div>
                    <a
                        href="{{ route('public.matches.scorecard.pdf', $match) }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <x-icon name="document-chart" class="h-3.5 w-3.5" />
                        Scorecard PDF
                    </a>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
            @endforelse
        </div>
    @endif
@endsection
